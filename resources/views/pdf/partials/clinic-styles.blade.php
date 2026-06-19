<style>
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #1a1a1a;
        margin: 0;
        padding: 28px 32px 40px;
    }
    .header {
        display: table;
        width: 100%;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 3px solid {{ $colorPrimario }};
    }
    .header-left { display: table-cell; vertical-align: middle; width: 72px; }
    .header-mid { display: table-cell; vertical-align: middle; padding-left: 14px; }
    .header-right {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
        width: 38%;
        font-size: 9px;
        color: #444;
    }
    .logo { max-width: 64px; max-height: 64px; }
    .clinic-name { font-size: 18px; font-weight: bold; color: {{ $colorPrimario }}; margin: 0 0 4px; }
    .doc-title { font-size: 13px; margin: 0; color: #333; }
    .doc-sub { font-size: 10px; margin: 4px 0 0; color: #666; }
    .card {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 12px 14px;
        margin-bottom: 16px;
        background: {{ $colorSecundario }};
    }
    .card-white { background: #fff; }
    .card h2 {
        margin: 0 0 10px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: {{ $colorPrimario }};
    }
    .grid { width: 100%; border-collapse: collapse; }
    .grid td { padding: 4px 8px 4px 0; vertical-align: top; }
    .grid .k { font-weight: bold; color: #555; width: 28%; }
    .entry {
        border: 1px solid #ddd;
        border-left: 4px solid {{ $colorPrimario }};
        border-radius: 4px;
        padding: 12px 14px;
        margin-bottom: 14px;
        page-break-inside: avoid;
        background: #fff;
    }
    .entry-head { margin-bottom: 8px; }
    .entry-badges { margin-bottom: 6px; }
    .badge {
        display: inline-block;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 2px 6px;
        border-radius: 3px;
        margin-right: 4px;
        background: {{ $colorSecundario }};
        color: {{ $colorPrimario }};
    }
    .entry-title { font-size: 12px; font-weight: bold; margin: 0 0 4px; color: #111; }
    .entry-meta { font-size: 9.5px; color: #666; margin: 0; }
    .section-title {
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: {{ $colorPrimario }};
        margin: 10px 0 6px;
    }
    .soap-block { margin-bottom: 8px; }
    .soap-label {
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        color: #666;
        margin: 0 0 2px;
    }
    .soap-text {
        margin: 0;
        white-space: pre-wrap;
        line-height: 1.45;
        font-size: 10px;
    }
    .vinculos { margin: 0; padding-left: 14px; font-size: 9.5px; color: #444; }
    .footer {
        position: fixed;
        bottom: 22px;
        left: 32px;
        right: 32px;
        font-size: 8.5px;
        color: #777;
        border-top: 1px solid #ddd;
        padding-top: 8px;
    }
    .muted { color: #888; }
</style>
