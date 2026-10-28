<?php

return [
    'binary' => env('ARJ_EPHEM_BIN', base_path('../engine/bin/arj-ephem')),
    'ephe_path' => env('ARJ_EPHE_PATH', base_path('../engine/ephe')),
    // Supported range of the bundled Swiss Ephemeris files (sepl/semo/seas_18): 1800–2399 CE.
    'min_year' => 1800,
    'max_year' => 2399,
];
