{{-- Self-contained print layout for server-side PDFs (App\Support\Pdf\Pdf). No external assets. --}}
<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { size: A4; margin: 16mm 14mm 18mm; }
    * { box-sizing: border-box; }
    body { font-family: "Noto Sans", "Noto Sans Devanagari", "DejaVu Sans", Arial, sans-serif; color: #141a3c; font-size: 11pt; line-height: 1.55; margin: 0; }
    h1 { font-size: 19pt; margin: 0 0 4pt; } h2 { font-size: 13.5pt; margin: 18pt 0 6pt; } h3 { font-size: 12pt; margin: 14pt 0 4pt; }
    p { margin: 0 0 7pt; } .muted { color: #555c7a; font-size: 9.5pt; } .small { font-size: 9pt; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #b8891d; padding-bottom: 8pt; margin-bottom: 12pt; }
    .brand { font-weight: 700; font-size: 13pt; } .right { text-align: right; }
    table { width: 100%; border-collapse: collapse; margin: 6pt 0; } th, td { border: 1px solid #c9cde0; padding: 5pt 6pt; text-align: left; vertical-align: top; font-size: 10pt; }
    th { background: #f3efe4; } td.num, th.num { text-align: right; white-space: nowrap; }
    .grid2 { display: flex; gap: 16pt; } .grid2 > div { flex: 1; }
    .charts { display: flex; gap: 18pt; justify-content: center; flex-wrap: wrap; } .charts figure { width: 230pt; margin: 0; text-align: center; } .charts svg { width: 230pt; height: auto; }
    section { break-inside: avoid; } .note { background: #f7f4ea; border: 1px solid #e6d9b3; padding: 7pt 9pt; font-size: 9pt; margin-top: 16pt; }
    .stamp { margin-top: 22pt; font-size: 9pt; color: #555c7a; }
</style>
</head>
<body>
    <div class="head">
        <div><div class="brand">{{ $brand }}</div><div class="muted">{{ $tagline ?? '' }}</div></div>
        <div class="right muted">{!! $headRight ?? '' !!}</div>
    </div>
    @yield('content')
</body>
</html>
