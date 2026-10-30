<?php

namespace App\Support;

use App\Domain\Showcase\Profile;
use App\Support\Settings\SettingsRepository;

/**
 * Business facts used by the contact page, footer, structured data (schema.org), llms.txt and
 * the app. The owner changes them in Admin › Settings (contact, address, hours) and Admin › About
 * & awards (profile); the defaults are the confirmed facts of the practice (28 Oct 2026).
 */
class Business
{
    public const DEFAULTS = [
        'street' => 'B-Block, 1st Floor, Chanakya Plaza, Near Chandan Automobile, Kankarbagh Main Road',
        'locality' => 'Kankarbagh, Patna', 'region' => 'Bihar', 'postal_code' => '800020', 'country' => 'IN',
        // Kankarbagh Main Road, Patna (approximate; correct it in Admin › Settings for the map pin)
        'lat' => 25.5958, 'lng' => 85.1601,
        'phone2' => '+919835882652',
    ];

    /** Default opening hours (Thursday closed). Edited in Admin › Settings › Opening hours. */
    public const DEFAULT_HOURS = [
        'mon' => ['08:00-12:00', '15:00-19:00'], 'tue' => ['08:00-12:00', '15:00-19:00'], 'wed' => ['08:00-12:00', '15:00-19:00'], 'thu' => [],
        'fri' => ['08:00-12:00', '15:00-19:00'], 'sat' => ['08:00-12:00', '15:00-19:00'], 'sun' => ['08:00-12:00', '15:00-19:00'],
    ];

    public const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    public function __construct(private SettingsRepository $settings) {}

    private function s(string $key, mixed $default = null): mixed
    {
        $v = $this->settings->get($key);

        return $v === null || $v === '' ? ($default ?? config('brand')[$key] ?? null) : $v;
    }

    public function name(): string
    {
        return (string) $this->s('brand.name');
    }

    public function practitioner(): string
    {
        return (string) $this->s('brand.practitioner_name');
    }

    public function phone(): ?string
    {
        return $this->s('contact.phone');
    }

    public function phone2(): ?string
    {
        return $this->s('contact.phone2', self::DEFAULTS['phone2']);
    }

    public function whatsapp(): ?string
    {
        return $this->s('contact.whatsapp');
    }

    public function email(): ?string
    {
        return $this->s('contact.email');
    }

    /** @return array{street: string, locality: string, region: string, postal_code: string, country: string} */
    public function address(): array
    {
        return ['street' => (string) $this->s('contact.street', self::DEFAULTS['street']), 'locality' => (string) $this->s('contact.locality', self::DEFAULTS['locality']),
            'region' => (string) $this->s('contact.region', self::DEFAULTS['region']), 'postal_code' => (string) $this->s('contact.postal_code', self::DEFAULTS['postal_code']),
            'country' => self::DEFAULTS['country']];
    }

    public function addressLine(): string
    {
        $a = $this->address();

        return $a['street'].', '.$a['locality'].', '.$a['region'].' '.$a['postal_code'];
    }

    /** @return array{lat: float, lng: float} */
    public function geo(): array
    {
        return ['lat' => (float) $this->s('contact.lat', self::DEFAULTS['lat']), 'lng' => (float) $this->s('contact.lng', self::DEFAULTS['lng'])];
    }

    public function mapUrl(): string
    {
        $g = $this->geo();

        return 'https://www.openstreetmap.org/?mlat='.$g['lat'].'&mlon='.$g['lng'].'#map=18/'.$g['lat'].'/'.$g['lng'];
    }

    /** @return list<string> public profile URLs */
    public function social(): array
    {
        return array_values(array_filter([$this->s('social.youtube'), $this->s('social.instagram'), $this->s('social.facebook'), $this->s('social.x')]));
    }

    /** @return list<string> languages spoken (English names, for structured data) */
    public function languages(): array
    {
        return app(Profile::class)->languages(true);
    }

    /** @return array<string, list<array{0: string, 1: string}>> day key => [[opens, closes], …] */
    public function hours(): array
    {
        $raw = $this->settings->get('hours.week');
        $week = is_array($raw) ? $raw : self::DEFAULT_HOURS;
        $out = [];
        foreach (array_keys(self::DAYS) as $d) {
            $out[$d] = array_values(array_map(fn ($r) => explode('-', $r, 2), array_filter((array) ($week[$d] ?? []), fn ($r) => is_string($r) && preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $r))));
        }

        return $out;
    }

    /** schema.org OpeningHoursSpecification rows: [days, opens, closes]. */
    public function hoursSpec(): array
    {
        $byRange = [];
        foreach ($this->hours() as $d => $ranges) {
            foreach ($ranges as [$o, $c]) {
                $byRange["$o-$c"][] = self::DAYS[$d];
            }
        }

        return array_map(fn ($k, $days) => [$days, ...explode('-', $k)], array_keys($byRange), array_values($byRange));
    }

    private static function time12(string $t): string
    {
        [$h, $m] = array_map('intval', explode(':', $t));

        return (($h % 12) ?: 12).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).' '.($h < 12 ? 'AM' : 'PM');
    }

    /** Consecutive days with the same hours, e.g. [["Mon – Wed", "8:00 AM – 12:00 PM, 3:00 PM – 7:00 PM"], ["Thursday", "Closed"]]. */
    public function hoursRows(): array
    {
        $rows = [];
        foreach ($this->hours() as $d => $ranges) {
            $text = $ranges ? implode(', ', array_map(fn ($r) => self::time12($r[0]).' – '.self::time12($r[1]), $ranges)) : 'Closed';
            $last = array_key_last($rows);
            if ($last !== null && $rows[$last]['text'] === $text) {
                $rows[$last]['to'] = $d;
            } else {
                $rows[] = ['from' => $d, 'to' => $d, 'text' => $text];
            }
        }

        return array_map(fn ($r) => [$r['from'] === $r['to'] ? self::DAYS[$r['from']] : ucfirst($r['from']).' – '.ucfirst($r['to']), $r['text']], $rows);
    }

    /** One line for the home page and llms.txt. */
    public function hoursLine(): string
    {
        if ($this->hours() === array_map(fn ($r) => array_map(fn ($x) => explode('-', $x, 2), $r), self::DEFAULT_HOURS)) {
            return __('Mon–Wed and Fri–Sun: 8 AM–12 PM and 3 PM–7 PM · Thursday closed.');
        }

        return implode(' · ', array_map(fn ($r) => __($r[0]).': '.__($r[1]), $this->hoursRows()));
    }

    /** For the app (GET /api/v1/site). */
    public function toApi(): array
    {
        return ['name' => $this->name(), 'practitioner' => $this->practitioner(), 'tagline' => $this->s('brand.tagline'),
            'phone' => $this->phone(), 'phone2' => $this->phone2(), 'whatsapp' => $this->whatsapp(), 'email' => $this->email(),
            'address' => $this->address() + ['line' => $this->addressLine()], 'geo' => $this->geo(), 'map_url' => $this->mapUrl(),
            'hours' => array_map(fn ($r) => ['days' => $r[0], 'time' => $r[1]], $this->hoursRows()), 'languages' => $this->languages(), 'social' => $this->social(),
            'about' => app(Profile::class)->toApi(), 'web' => ['about' => url('/about'), 'terms' => url('/terms'), 'privacy' => url('/privacy'), 'contact' => url('/contact')]];
    }
}
