<?php

namespace App\Domain\Payments;

use App\Support\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GST tax invoices and credit notes (FI-08), issued only when the owner turns GST on in
 * Admin › Payments setup. Prices are GST-inclusive (B2C retail): the tax is worked out of the
 * amount charged, so customers pay the same price either way.
 * - Number: PREFIX/YY-YY/NNNNNN per financial year (April–March, IST), at most 16 characters as
 *   GST rules require; credit notes use PREFIXC/YY-YY/NNNNN. Sequences are gap-free (row lock).
 * - Place of supply: the delivery state for gem orders; the supplier's state for services
 *   (no customer address is collected). Same state → CGST + SGST, otherwise IGST.
 * - Wallet recharges are advances, not supplies: no invoice. A service paid from the wallet is
 *   invoiced in full when bought.
 */
class Invoices
{
    /** GST state codes. */
    public const STATES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi',
        '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
        '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha',
        '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat', '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra',
        '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
    ];

    private const ALIASES = ['orissa' => '21', 'up' => '09', 'mp' => '23', 'newdelhi' => '07', 'delhincr' => '07', 'nctofdelhi' => '07', 'pondicherry' => '34',
        'jk' => '01', 'jammukashmir' => '01', 'andamannicobar' => '35', 'damananddiu' => '26', 'dadraandnagarhaveli' => '26', 'uttaranchal' => '05', 'tn' => '33', 'ap' => '37'];

    public function __construct(private SettingsRepository $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('tax.gst_enabled') && preg_match('/^\d{2}[A-Z0-9]{13}$/', (string) $this->settings->get('tax.gstin'));
    }

    /** Settings with defaults. */
    public function config(): array
    {
        $g = fn (string $k, $d = null) => $this->settings->get('tax.'.$k, $d);
        $gstin = (string) $g('gstin', '');

        return [
            'gstin' => $gstin, 'legal_name' => (string) $g('legal_name', config('brand')['brand.name'] ?? ''), 'address' => (string) $g('address', ''),
            'state_code' => substr($gstin, 0, 2), 'state' => self::STATES[substr($gstin, 0, 2)] ?? '',
            'service_rate_bps' => (int) $g('service_rate_bps', 1800), 'product_rate_bps' => (int) $g('product_rate_bps', 300),
            'service_sac' => (string) $g('service_sac', '999799'), 'product_hsn' => (string) $g('product_hsn', '7103'),
            'prefix' => strtoupper((string) $g('invoice_prefix', 'ARJ')),
        ];
    }

    public function rateFor(string $kind): int
    {
        return $kind === 'product' ? $this->config()['product_rate_bps'] : $this->config()['service_rate_bps'];
    }

    /** GST included in a GST-inclusive amount. */
    public static function taxIncluded(int $grossMinor, int $rateBps): int
    {
        return $rateBps <= 0 ? 0 : $grossMinor - (int) round($grossMinor * 10000 / (10000 + $rateBps));
    }

    /** GST share of part of an order (refunds), proportional to the tax in the order. */
    public static function taxShare(object $order, int $amountMinor): int
    {
        if ((int) $order->tax_minor <= 0 || (int) $order->subtotal_minor <= 0) {
            return 0;
        }

        return min($amountMinor, (int) round($amountMinor * (int) $order->tax_minor / (int) $order->subtotal_minor));
    }

    /** GST state code for a free-text state name, or null. */
    public static function stateCode(?string $name): ?string
    {
        $n = preg_replace('/[^a-z]/', '', strtolower((string) $name));
        $n = str_replace('and', '', $n);
        foreach (self::STATES as $code => $s) {
            if (str_replace('and', '', preg_replace('/[^a-z]/', '', strtolower($s))) === $n) {
                return $code;
            }
        }

        return self::ALIASES[$n] ?? self::ALIASES[str_replace('and', '', $n)] ?? null;
    }

    /** Financial-year label for a date, e.g. "26-27". */
    public static function financialYear(CarbonImmutable $at): string
    {
        $at = $at->setTimezone('Asia/Kolkata');
        $start = $at->month >= 4 ? $at->year : $at->year - 1;

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    /** Issues the tax invoice for a paid order (idempotent). Call inside the payment transaction. */
    public function issueForOrder(object $order): ?object
    {
        if (! $this->enabled() || ! in_array($order->kind, ['service', 'product'], true) || (int) $order->subtotal_minor <= 0) {
            return null;
        }
        if ($existing = DB::table('invoices')->where('order_id', $order->id)->where('type', 'invoice')->first()) {
            return $existing;
        }
        $cfg = $this->config();
        $snap = json_decode($order->snapshot, true);
        $user = DB::table('users')->find($order->user_id);
        $buyer = ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone_e164, 'state_code' => $cfg['state_code'], 'state' => $cfg['state'], 'address' => null];
        if ($order->kind === 'product' && ($s = DB::table('shipments')->where('order_id', $order->id)->first())) {
            $a = json_decode(Crypt::decryptString($s->address), true);
            $code = self::stateCode($a['state'] ?? null);
            $buyer = ['name' => $a['name'] ?? $user->name, 'email' => $user->email, 'phone' => $a['phone'] ?? $user->phone_e164,
                'state_code' => $code ?? $cfg['state_code'], 'state' => $code ? self::STATES[$code] : ($a['state'] ?? $cfg['state']),
                'address' => implode(', ', array_filter([$a['line1'] ?? null, $a['line2'] ?? null, $a['city'] ?? null, $a['state'] ?? null, $a['pincode'] ?? null]))];
        }
        $rate = (int) $order->tax_minor > 0 ? $this->rateFor($order->kind) : 0;
        $line = [
            'description' => Checkout::label($order->kind, $snap),
            'code' => $order->kind === 'product' ? $cfg['product_hsn'] : $cfg['service_sac'], 'code_type' => $order->kind === 'product' ? 'HSN' : 'SAC',
            'quantity' => (int) ($snap['quantity'] ?? 1), 'list_minor' => (int) ($order->list_minor ?? $order->subtotal_minor), 'discount_minor' => (int) $order->discount_minor,
            'total_minor' => (int) $order->subtotal_minor,
        ];

        return $this->write('invoice', $order, null, null, $cfg, $buyer, [$line], $rate, (int) $order->subtotal_minor, (int) $order->tax_minor);
    }

    /** Issues a credit note for a processed refund on an invoiced order (idempotent). */
    public function issueCreditNote(object $order, object $refund): ?object
    {
        $inv = DB::table('invoices')->where('order_id', $order->id)->where('type', 'invoice')->first();
        if (! $inv) {
            return null;
        }
        if ($existing = DB::table('invoices')->where('refund_id', $refund->id)->first()) {
            return $existing;
        }
        $total = (int) $refund->amount_minor;
        $tax = self::taxShare($order, $total);
        $line = ['description' => 'Refund against invoice '.$inv->number, 'code' => json_decode($inv->lines, true)[0]['code'] ?? null,
            'code_type' => json_decode($inv->lines, true)[0]['code_type'] ?? null, 'quantity' => 1, 'list_minor' => $total, 'discount_minor' => 0, 'total_minor' => $total];

        return $this->write('credit_note', $order, $refund->id, $inv, json_decode($inv->supplier, true), json_decode($inv->buyer, true), [$line], (int) $inv->rate_bps, $total, $tax);
    }

    private function write(string $type, object $order, ?int $refundId, ?object $inv, array $supplier, array $buyer, array $lines, int $rate, int $total, int $tax): object
    {
        $issued = CarbonImmutable::now();
        $prefix = $type === 'invoice' ? $supplier['prefix'] ?? 'ARJ' : ($supplier['prefix'] ?? 'ARJ').'C';
        $series = $prefix.'/'.self::financialYear($issued);
        DB::table('invoice_sequences')->insertOrIgnore(['series' => $series, 'last' => 0]);
        $last = (int) DB::table('invoice_sequences')->where('series', $series)->lockForUpdate()->value('last') + 1;
        DB::table('invoice_sequences')->where('series', $series)->update(['last' => $last]);
        $number = $series.'/'.str_pad((string) $last, 16 - strlen($series) - 1, '0', STR_PAD_LEFT);
        $intra = ($buyer['state_code'] ?? null) === ($supplier['state_code'] ?? null);
        $cgst = $intra ? intdiv($tax, 2) : 0;
        $id = DB::table('invoices')->insertGetId([
            'ref' => (string) Str::ulid(), 'type' => $type, 'number' => $number, 'series' => $series, 'order_id' => $order->id, 'refund_id' => $refundId, 'invoice_id' => $inv?->id,
            'issued_at' => $issued, 'supplier' => json_encode(array_intersect_key($supplier, array_flip(['gstin', 'legal_name', 'address', 'state_code', 'state', 'prefix']))), 'buyer' => json_encode($buyer),
            'place_of_supply' => trim(($buyer['state_code'] ?? '').' '.($buyer['state'] ?? '')), 'lines' => json_encode($lines), 'rate_bps' => $rate,
            'taxable_minor' => $total - $tax, 'cgst_minor' => $cgst, 'sgst_minor' => $intra ? $tax - $cgst : 0, 'igst_minor' => $intra ? 0 : $tax,
            'total_minor' => $total, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('invoices')->find($id);
    }
}
