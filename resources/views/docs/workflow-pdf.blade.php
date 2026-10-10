<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket System Workflow</title>
    <style>
        @page {
            margin: 28px 32px;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.5;
        }

        .header-wrap {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 10px 14px;
            margin-bottom: 18px;
        }

        .brand-title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .brand-subtitle {
            margin: 2px 0 0;
            color: #475569;
            font-size: 10px;
        }

        h1 {
            font-size: 17px;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 22px;
        }

        h2 {
            font-size: 14px;
            color: #0f172a;
            margin-top: 18px;
        }

        h3 {
            font-size: 12px;
            color: #0f172a;
            margin-top: 14px;
        }

        p, li {
            font-size: 11px;
            color: #1e293b;
        }

        ul, ol {
            margin: 6px 0;
            padding-left: 18px;
        }

        code {
            font-family: DejaVu Sans Mono, monospace;
            background: #f1f5f9;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            text-align: left;
            font-size: 10px;
            vertical-align: top;
        }

        th {
            background: #f1f5f9;
            color: #0f172a;
        }

        hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 16px 0;
        }

        .footer {
            margin-top: 20px;
            color: #94a3b8;
            font-size: 9px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header-wrap">
        <p class="brand-title">{{ config('app.name') }}</p>
        <p class="brand-subtitle">Ticket System Workflow — generated {{ $generatedAt->format('F j, Y') }}</p>
    </div>

    {!! $bodyHtml !!}

    <p class="footer">Generated from docs/ticket-system-workflow.md via `php artisan helpdesk:build-workflow-pdf`.</p>
</body>
</html>
