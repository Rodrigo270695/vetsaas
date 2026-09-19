<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">
        <meta name="theme-color" content="#008064" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0A1F18" media="(prefers-color-scheme: dark)">
        <meta name="application-name" content="{{ config('app.name', 'VetSaaS') }}">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'VetSaaS') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="mobile-web-app-capable" content="yes">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/pwa/icon-180.png">
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/pwa/icon-192.png">
        <link rel="manifest" href="/manifest.json">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])

        @if (! empty($clinicBrandCss))
            <style id="clinic-brand-theme">{!! $clinicBrandCss !!}</style>
        @endif

        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div id="vetsaas-boot" role="status" aria-live="polite">
            <img src="/icons/pwa/icon-192.png" width="72" height="72" alt="">
            <p>VetSaaS</p>
            <button type="button" id="vetsaas-boot-retry" hidden>Toca para continuar</button>
        </div>
        <style>
            #vetsaas-boot {
                position: fixed;
                inset: 0;
                z-index: 2147483646;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                margin: 0;
                background: #FAFAF9;
                color: #57534e;
                font-family: system-ui, sans-serif;
            }
            html.dark #vetsaas-boot {
                background: #0A1F18;
                color: #d6d3d1;
            }
            #vetsaas-boot img { width: 72px; height: 72px; }
            #vetsaas-boot p { margin: 28px 0 0; font-size: 15px; }
            #vetsaas-boot button {
                margin-top: 28px;
                padding: 10px 16px;
                border: 0;
                border-radius: 8px;
                background: #008064;
                color: #fff;
                font-size: 14px;
            }
        </style>
        <script>
            (function () {
                var boot = document.getElementById('vetsaas-boot');
                var retry = document.getElementById('vetsaas-boot-retry');
                if (!boot) {
                    return;
                }
                var gone = false;
                function hide() {
                    if (gone) {
                        return;
                    }
                    gone = true;
                    boot.remove();
                }
                window.__vetsaasHideBoot = hide;
                window.setTimeout(function () {
                    if (!gone && retry) {
                        retry.hidden = false;
                    }
                }, 10000);
                if (retry) {
                    retry.addEventListener('click', function () {
                        retry.disabled = true;
                        function reload() {
                            window.location.reload();
                        }
                        if (!('caches' in window) || !('serviceWorker' in navigator)) {
                            reload();
                            return;
                        }
                        Promise.all([
                            caches.keys().then(function (keys) {
                                return Promise.all(keys.map(function (key) {
                                    return caches.delete(key);
                                }));
                            }),
                            navigator.serviceWorker.getRegistrations().then(function (regs) {
                                return Promise.all(regs.map(function (reg) {
                                    return reg.unregister();
                                }));
                            }),
                        ]).then(reload).catch(reload);
                    });
                }
            })();
        </script>
        <x-inertia::app />
    </body>
</html>
