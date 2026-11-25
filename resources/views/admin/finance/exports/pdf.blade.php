<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #263238; font-size: 9px; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        p { color: #667085; margin: 0 0 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #dfe3e8; padding: 6px; text-align: left; }
        th { background: #f2f4f7; font-weight: bold; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>Período: {{ $range['start']->format('d/m/Y') }} a {{ $range['end']->format('d/m/Y') }}</p>
    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headers) }}">Nenhum registro encontrado no período.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
