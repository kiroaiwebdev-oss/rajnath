<?php

namespace App\Support\Seo;

use App\Http\Middleware\SetLocale;
use App\Models\Offering;
use App\Models\OfferingVariant;
use App\Support\Business;
use App\Support\Money;

/**
 * schema.org structured data (JSON-LD) for search engines and AI answer engines.
 * One @graph per page: the business, its founder and the website on every public page,
 * plus page-specific nodes (Service, Product, FAQPage, BreadcrumbList, ProfilePage…).
 */
class JsonLd
{
    public static function id(string $frag): string
    {
        return url('/').'#'.$frag;
    }

    /** Business, founder and website (every public page). */
    public static function site(): array
    {
        $b = app(Business::class);
        $a = $b->address();
        $g = $b->geo();
        $hours = array_map(fn ($h) => ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $h[0], 'opens' => $h[1], 'closes' => $h[2]], Business::HOURS);
        $phones = array_values(array_filter([$b->phone(), $b->phone2()]));

        return [
            [
                '@type' => ['LocalBusiness', 'ProfessionalService'], '@id' => self::id('business'), 'name' => $b->name(), 'url' => url('/'),
                'description' => 'Vedic astrology consultations, kundali reading and matching, Vastu, numerology, palmistry, muhurat, puja and gemstone guidance by '.$b->practitioner().' in Patna, Bihar, and online by video and audio call.',
                'image' => [asset('images/owner/portrait-navy-960.jpg'), asset('images/owner/office-wide-960.jpg')], 'logo' => asset('icons/icon-512.png'),
                'telephone' => $phones[0] ?? null, 'email' => $b->email(),
                'address' => ['@type' => 'PostalAddress', 'streetAddress' => $a['street'], 'addressLocality' => $a['locality'], 'addressRegion' => $a['region'], 'postalCode' => $a['postal_code'], 'addressCountry' => $a['country']],
                'geo' => ['@type' => 'GeoCoordinates', 'latitude' => $g['lat'], 'longitude' => $g['lng']], 'hasMap' => $b->mapUrl(),
                'openingHoursSpecification' => $hours, 'areaServed' => [['@type' => 'City', 'name' => 'Patna'], ['@type' => 'State', 'name' => 'Bihar'], ['@type' => 'Country', 'name' => 'India'], 'Worldwide (online)'],
                'availableLanguage' => Business::LANGUAGES, 'priceRange' => '₹₹', 'currenciesAccepted' => 'INR', 'paymentAccepted' => 'UPI, Credit Card, Debit Card, Net Banking, Wallet',
                'founder' => ['@id' => self::id('founder')], 'employee' => ['@id' => self::id('founder')],
                'contactPoint' => array_map(fn ($p) => ['@type' => 'ContactPoint', 'telephone' => $p, 'contactType' => 'customer service', 'areaServed' => 'IN', 'availableLanguage' => Business::LANGUAGES], $phones),
                'sameAs' => $b->social() ?: null,
                'knowsAbout' => Business::PERSON['knows'],
            ],
            self::person(),
            [
                '@type' => 'WebSite', '@id' => self::id('website'), 'url' => url('/'), 'name' => $b->name(), 'publisher' => ['@id' => self::id('business')],
                'inLanguage' => SetLocale::enabled(),
                'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/services').'?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
            ],
        ];
    }

    public static function person(): array
    {
        $p = Business::PERSON;

        return ['@type' => 'Person', '@id' => self::id('founder'), 'name' => $p['name'], 'url' => url('/about'), 'image' => asset('images/owner/portrait-red-480.jpg'),
            'jobTitle' => $p['job'], 'description' => $p['summary'], 'honorificPrefix' => 'Acharya Dr.',
            'worksFor' => ['@id' => self::id('business')], 'alumniOf' => ['@type' => 'CollegeOrUniversity', 'name' => $p['alumni']],
            'hasCredential' => array_map(fn ($e) => ['@type' => 'EducationalOccupationalCredential', 'name' => $e[0], 'dateCreated' => (string) $e[1], 'recognizedBy' => ['@type' => 'Organization', 'name' => $e[2]]], $p['education']),
            'award' => $p['awards'], 'knowsAbout' => $p['knows'], 'knowsLanguage' => Business::LANGUAGES,
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Patna', 'addressRegion' => 'Bihar', 'addressCountry' => 'IN']];
    }

    /** @param list<array{0: string, 1: string}> $items [name, url] from home to the current page */
    public static function breadcrumbs(array $items): array
    {
        $list = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')]];
        foreach (array_values($items) as $i => [$name, $u]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $name, 'item' => $u];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    /** @param iterable<array{0: string, 1: string}> $qa */
    public static function faq(iterable $qa): array
    {
        $out = [];
        foreach ($qa as [$q, $a]) {
            $out[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($a)]];
        }

        return ['@type' => 'FAQPage', 'mainEntity' => $out];
    }

    public static function service(Offering $o): array
    {
        $t = $o->t();
        $offers = $o->variants->where('active', true)->whereNotNull('price_minor')->values()
            ->map(fn ($v) => ['@type' => 'Offer', 'name' => $v->label(), 'price' => number_format($v->price_minor / 100, 2, '.', ''), 'priceCurrency' => 'INR',
                'availability' => 'https://schema.org/InStock', 'url' => route('services.show', $o->slug), 'seller' => ['@id' => self::id('business')]])->all();

        return array_filter(['@type' => 'Service', '@id' => route('services.show', $o->slug).'#service', 'name' => $t?->title, 'description' => $t?->summary,
            'serviceType' => $o->family?->name, 'category' => $o->family?->name, 'provider' => ['@id' => self::id('business')], 'areaServed' => ['Patna', 'India', 'Worldwide (online)'],
            'image' => $o->image_path ? asset($o->image_path) : null, 'url' => route('services.show', $o->slug), 'availableLanguage' => Business::LANGUAGES,
            'offers' => $offers ?: null]);
    }

    /** Answer-first questions for a service page (shown on the page and as FAQPage). */
    public static function serviceQuestions(Offering $o): array
    {
        $t = $o->t();
        $title = $t?->title ?? $o->slug;
        $priced = $o->variants->where('active', true)->whereNotNull('price_minor');
        $out = [];
        if ($priced->isNotEmpty()) {
            $min = (int) $priced->min('price_minor');
            $list = $priced->take(6)->map(fn ($v) => $v->label().' '.Money::format((int) $v->price_minor))->implode('; ');
            $out[] = [__('How much does :title cost?', ['title' => $title]), __(':title starts at :price. Options: :list.', ['title' => $title, 'price' => Money::format($min), 'list' => $list])];
        }
        $modes = $o->variants->where('active', true)->pluck('mode')->filter()->unique()->map(fn ($m) => mb_strtolower(__(OfferingVariant::MODES[$m] ?? $m)))->values();
        if ($modes->isNotEmpty()) {
            $out[] = [__('How is :title given?', ['title' => $title]), __('Available as: :modes. Book online on this page or call the office.', ['modes' => $modes->implode(', ')])];
        }
        $out[] = [__('Who provides :title?', ['title' => $title]), __(':name, Ph.D. in Astrology, with more than 30 years of practice in Patna, or an approved astrologer of his team where stated.', ['name' => Business::PERSON['name']])];

        return $out;
    }

    public static function product(object $p): array
    {
        return ['@type' => 'Product', 'name' => $p->name, 'description' => $p->summary, 'image' => $p->image_path ? asset($p->image_path) : null, 'sku' => $p->ref,
            'category' => 'Gemstone', 'brand' => ['@id' => self::id('business')],
            'additionalProperty' => array_values(array_filter([$p->weight ? ['@type' => 'PropertyValue', 'name' => 'Weight', 'value' => $p->weight] : null,
                $p->origin ? ['@type' => 'PropertyValue', 'name' => 'Origin', 'value' => $p->origin] : null, $p->certification ? ['@type' => 'PropertyValue', 'name' => 'Certification', 'value' => $p->certification] : null])),
            'offers' => ['@type' => 'Offer', 'price' => number_format($p->price_minor / 100, 2, '.', ''), 'priceCurrency' => 'INR', 'url' => route('store.show', $p->slug),
                'availability' => $p->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => self::id('business')], 'shippingDetails' => ['@type' => 'OfferShippingDetails', 'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'IN'],
                    'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => 0, 'currency' => 'INR']]]];
    }

    /** A free web tool (calculators, Panchang, muhurat finder…). */
    public static function webApp(string $name, string $description, string $url): array
    {
        return ['@type' => 'WebApplication', 'name' => $name, 'description' => $description, 'url' => $url, 'applicationCategory' => 'LifestyleApplication', 'operatingSystem' => 'Any',
            'isAccessibleForFree' => true, 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'], 'provider' => ['@id' => self::id('business')]];
    }

    /** Renders the page graph as a script tag. */
    public static function render(array $nodes): string
    {
        $graph = array_values(array_map(fn ($n) => self::clean($n), array_merge(self::site(), $nodes)));

        return '<script type="application/ld+json">'.json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG).'</script>';
    }

    private static function clean(array $a): array
    {
        $list = array_is_list($a);
        $out = array_map(fn ($v) => is_array($v) ? self::clean($v) : $v, array_filter($a, fn ($v) => $v !== null && $v !== [] && $v !== ''));

        return $list ? array_values($out) : $out;
    }
}
