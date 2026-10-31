<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* DejaVu Sans ships with dompdf and covers currency symbols such as ₹. */
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #1f2937; }
        h1 { font-size: 18pt; margin: 0 0 4pt; }
        .muted { color: #6b7280; }
        .row { width: 100%; }
        .row td { vertical-align: top; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 16pt; }
        table.lines th { text-align: left; border-bottom: 1pt solid #d1d5db; padding: 4pt 0; font-size: 9pt; color: #6b7280; }
        table.lines td { border-bottom: 1pt solid #e5e7eb; padding: 5pt 0; }
        .num, table.lines th.num { text-align: right; white-space: nowrap; }
        table.totals { margin-top: 10pt; margin-left: auto; border-collapse: collapse; }
        table.totals td { padding: 2pt 0 2pt 24pt; }
        .total td { font-weight: bold; font-size: 12pt; border-top: 1pt solid #d1d5db; padding-top: 5pt; }
        .footer { margin-top: 28pt; font-size: 9pt; color: #6b7280; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
