<style>
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10px;
        color: #1a1a1a;
        margin: 0;
        padding: 16px 20px 42px;
    }
    .header {
        display: table;
        width: 100%;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 2px solid {{ $colorPrimario }};
    }
    .header-left { display: table-cell; vertical-align: middle; width: 56px; }
    .header-mid { display: table-cell; vertical-align: middle; padding-left: 10px; }
    .header-right {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
        width: 38%;
        font-size: 8.5px;
        color: #444;
        line-height: 1.35;
    }
    .logo { max-width: 52px; max-height: 52px; }
    .clinic-name { font-size: 15px; font-weight: bold; color: {{ $colorPrimario }}; margin: 0 0 2px; }
    .doc-title { font-size: 11px; margin: 0; color: #333; }
    .doc-sub { font-size: 9px; margin: 2px 0 0; color: #666; }
    .meta-row {
        display: table;
        width: 100%;
        margin-bottom: 8px;
        border-collapse: separate;
        border-spacing: 0;
    }
    .meta-row .card {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        margin-bottom: 0;
    }
    .meta-row .card + .card {
        border-left: none;
    }
    .card {
        border: 1px solid #ddd;
        border-radius: 3px;
        padding: 7px 9px;
        margin-bottom: 8px;
        background: {{ $colorSecundario }};
    }
    .card-white { background: #fff; }
    .card h2 {
        margin: 0 0 5px;
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: {{ $colorPrimario }};
    }
    .grid { width: 100%; border-collapse: collapse; }
    .grid td { padding: 2px 6px 2px 0; vertical-align: top; line-height: 1.3; }
    .grid .k { font-weight: bold; color: #555; width: 30%; }
    .entry {
        border: 1px solid #ddd;
        border-left: 3px solid {{ $colorPrimario }};
        border-radius: 3px;
        padding: 8px 10px;
        margin-bottom: 8px;
        /* Evita huecos enormes: DomPDF no debe empujar el bloque entero a la pág. 2 */
        page-break-inside: auto;
        background: #fff;
    }
    .entry-head { margin-bottom: 4px; }
    .entry-badges { margin-bottom: 3px; }
    .badge {
        display: inline-block;
        font-size: 7.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 1px 5px;
        border-radius: 2px;
        margin-right: 3px;
        background: {{ $colorSecundario }};
        color: {{ $colorPrimario }};
    }
    .entry-title { font-size: 11px; font-weight: bold; margin: 0 0 2px; color: #111; }
    .entry-meta { font-size: 8.5px; color: #666; margin: 0; }
    .section-title {
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: {{ $colorPrimario }};
        margin: 6px 0 2px;
    }
    .soap-block {
        margin-bottom: 4px;
        page-break-inside: avoid;
    }
    .soap-label {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        color: #666;
        margin: 0 0 1px;
    }
    .soap-text {
        margin: 0;
        white-space: pre-wrap;
        line-height: 1.3;
        font-size: 9.5px;
    }
    .vinculos {
        margin: 0;
        padding-left: 12px;
        font-size: 9px;
        color: #444;
        line-height: 1.35;
    }
    .vinculos li { margin-bottom: 1px; }
    .footer {
        position: fixed;
        bottom: 14px;
        left: 20px;
        right: 20px;
        font-size: 7.5px;
        color: #777;
        border-top: 1px solid #ddd;
        padding-top: 5px;
    }
    .muted { color: #888; }
</style>
