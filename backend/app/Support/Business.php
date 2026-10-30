<?php

namespace App\Support;

use App\Support\Settings\SettingsRepository;

/**
 * Business facts used by the contact page, footer, structured data (schema.org), llms.txt and
 * the app. Values the owner can change live in Admin › Settings; the rest are the confirmed
 * facts of the practice (owner confirmation, 28 Oct 2026).
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

    /** Opening hours: [days, opens, closes]. Thursday closed. */
    public const HOURS = [
        [['Monday', 'Tuesday', 'Wednesday', 'Friday', 'Saturday', 'Sunday'], '08:00', '12:00'],
        [['Monday', 'Tuesday', 'Wednesday', 'Friday', 'Saturday', 'Sunday'], '15:00', '19:00'],
    ];

    public const LANGUAGES = ['Hindi', 'Maithili', 'Sanskrit', 'English'];

    public const PERSON = [
        'name' => 'Acharya Dr. Rajnath Jha',
        'job' => 'Vedic astrologer; Director, Jyotirveda Vigyan Sansthan, Patna',
        'summary' => 'Acharya Dr. Rajnath Jha is a Vedic astrologer from Patna, Bihar, with a Ph.D. in Astrology (K.S.D.S.U., Darbhanga, 2014) and more than 30 years of practice in Jyotish, horoscope analysis, Vastu Shastra, numerology and palmistry. He is Director of Jyotirveda Vigyan Sansthan, served 17 years as Jyotish and Karmkand consultant at Jyotish Mandap, Mahavir Mandir Trust, Patna, and has guided more than 1,00,000 clients.',
        'alumni' => 'Kameshwar Singh Darbhanga Sanskrit University',
        'knows' => ['Vedic astrology (Jyotish)', 'Kundali analysis', 'Kundali matching', 'Vastu Shastra', 'Numerology', 'Palmistry', 'Muhurat', 'Gemstone recommendation', 'Karmkand and puja'],
        'awards' => [
            'Professor Award 2026 — IVAF International Vedic Astrology Federation, Kandy, Sri Lanka',
            'Global Astrological Award and Lifetime Achievement Honour — Malaysia, 2025',
            'US Global Excellence Award — New Delhi, 2024',
            'Maharshi Gyan Ratan Award — Vietnam, 2024',
            'Jyotish Siromani International Award — Chennai, 2024',
            'Hind Gaurav Award — Indore, 2024',
            'Jyotish Samridhi Award — Tamil Nadu, 2024',
            'Mithila Ratna Award — Madhubani, 2024',
            'Jyotish Gyan Ratna Award — Tirupati, 2023',
            'Jyotish Chakravarthy Award — Mumbai, 2023',
            'Golden Achievement Award — Kolkata, 2005',
            'Vishwabandhu Award — Kolkata, 2004',
            'Jyotish Shri Award — Madhubani, 2002',
            'Gold Medal, Acharya (Ganit Jyotish) — K.S.D.S.U., 1999',
        ],
        'education' => [
            ['Ph.D. in Astrology', 2014, 'K.S.D.S.U., Darbhanga'], ['Acharya (Phalit Jyotish)', 2003, 'K.S.D.S.U., Darbhanga'],
            ['Acharya (Ganit Jyotish), Gold Medalist', 1999, 'K.S.D.S.U., Darbhanga'], ['Shastri (Hons.)', 1996, 'K.S.D.S.U., Darbhanga'],
            ['Upashastri', 1993, 'K.S.D.S.U., Darbhanga'], ['Madhyama', 1991, 'B.S.S.B., Patna'],
        ],
        'positions' => [
            'Director — Jyotirveda Vigyan Sansthan (2011 – present)', 'Founder & Director — Sanatan Vedic Gurukul, Madhubani (2024 – present)',
            'International Chief Consultant — International Astrology Federation (USA)', 'Vice Chairman — World Climate Change Foundation, Bihar',
            'Astrologer — Jyotish Mandap, Mahavir Mandir, Patna (2002 – 2016)',
        ],
        'media' => ['Dainik Bhaskar (columnist)', 'Dainik Jagran (columnist)', 'Hindustan (weekly column)', 'Radio Mirchi 98.3 FM — "Kaal Dhamal"', 'ETV Bihar-Jharkhand', 'Maurya TV', 'News18', 'Kashish News'],
        'stats' => ['years' => '30+', 'clients' => '1,00,000+', 'awards' => '13+', 'languages' => '4'],
    ];

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
        return ['street' => (string) $this->s('contact.street', self::DEFAULTS['street']), 'locality' => self::DEFAULTS['locality'],
            'region' => self::DEFAULTS['region'], 'postal_code' => self::DEFAULTS['postal_code'], 'country' => self::DEFAULTS['country']];
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

    /** Hours as readable rows, e.g. ["Mon – Wed, Fri – Sun", "8:00 AM – 12:00 PM, 3:00 PM – 7:00 PM"]. */
    public function hoursRows(): array
    {
        return [['Mon – Wed', '8:00 AM – 12:00 PM, 3:00 PM – 7:00 PM'], ['Thursday', 'Closed'], ['Fri – Sun', '8:00 AM – 12:00 PM, 3:00 PM – 7:00 PM']];
    }

    /** For the app (GET /api/v1/site). */
    public function toApi(): array
    {
        return ['name' => $this->name(), 'practitioner' => $this->practitioner(), 'tagline' => $this->s('brand.tagline'),
            'phone' => $this->phone(), 'phone2' => $this->phone2(), 'whatsapp' => $this->whatsapp(), 'email' => $this->email(),
            'address' => $this->address() + ['line' => $this->addressLine()], 'geo' => $this->geo(), 'map_url' => $this->mapUrl(),
            'hours' => array_map(fn ($r) => ['days' => $r[0], 'time' => $r[1]], $this->hoursRows()), 'languages' => self::LANGUAGES, 'social' => $this->social(),
            'about' => self::PERSON, 'web' => ['about' => url('/about'), 'terms' => url('/terms'), 'privacy' => url('/privacy'), 'contact' => url('/contact')]];
    }
}
