<?php

namespace App\Support\Pdf;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Server-side PDFs printed by headless Chromium from self-contained HTML (inline CSS and SVG).
 * Chromium shapes Devanagari correctly, so Hindi reports and invoices print as on screen.
 * The HTML carries a Content-Security-Policy that blocks every network and file load, and
 * the browser runs with a throw-away profile; the temporary files are always deleted.
 */
class Pdf
{
    private const CSP = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:";

    public function available(): bool
    {
        return is_executable((string) config('pdf.binary'));
    }

    /** @param  string  $html  full HTML document (from resources/views/pdf/*) */
    public function render(string $html): string
    {
        $bin = (string) config('pdf.binary');
        if (! is_executable($bin)) {
            throw new RuntimeException('PDF printing is not installed (set PDF_CHROME_BINARY).');
        }
        $meta = '<meta http-equiv="Content-Security-Policy" content="'.self::CSP.'">';
        $html = preg_replace('/<head>/i', '<head>'.$meta, $html, 1) ?? $html;
        $dir = storage_path('app/pdf-tmp/'.Str::random(24));
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create PDF work folder.');
        }
        try {
            file_put_contents($dir.'/in.html', $html);
            $p = new Process([$bin, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--no-zygote', '--single-process',
                '--disable-extensions', '--disable-background-networking', '--disable-sync', '--no-first-run', '--hide-scrollbars',
                '--user-data-dir='.$dir.'/profile', '--no-pdf-header-footer', '--print-to-pdf='.$dir.'/out.pdf', 'file://'.$dir.'/in.html'],
                null, ['HOME' => $dir], null, (float) config('pdf.timeout'));
            $p->run();
            $pdf = is_file($dir.'/out.pdf') ? (string) file_get_contents($dir.'/out.pdf') : '';
            if (! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('PDF printing failed: '.mb_substr(trim($p->getErrorOutput()), -300));
            }

            return $pdf;
        } finally {
            (new Filesystem)->deleteDirectory($dir);
        }
    }
}
