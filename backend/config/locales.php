<?php

return [
    // Languages the site and API know about (RP-24). A language is offered to visitors only when
    // it is listed in SITE_LOCALES, which the owner should do only after the translations
    // (lang/{code}.json and the catalogue texts in Admin › Catalogue) are reviewed.
    'available' => [
        'en' => 'English', 'hi' => 'हिन्दी', 'bn' => 'বাংলা', 'gu' => 'ગુજરાતી', 'kn' => 'ಕನ್ನಡ',
        'ml' => 'മലയാളം', 'mr' => 'मराठी', 'pa' => 'ਪੰਜਾਬੀ', 'ta' => 'தமிழ்', 'te' => 'తెలుగు',
    ],
    'enabled' => array_values(array_filter(array_map('trim', explode(',', (string) env('SITE_LOCALES', 'en,hi'))))),
];
