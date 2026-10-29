<?php

return [
    // Headless Chromium used to print PDFs (Debian package "chromium"; see deploy/Dockerfile).
    // Any Chromium or chrome-headless-shell binary that supports --print-to-pdf works.
    'binary' => env('PDF_CHROME_BINARY', '/usr/bin/chromium'),
    'timeout' => (int) env('PDF_TIMEOUT', 45),
];
