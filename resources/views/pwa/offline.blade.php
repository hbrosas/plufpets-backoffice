<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#1f6f68">
        <title>Offline · {{ $appName }}</title>
        <style>
            :root {
                color-scheme: light dark;
                --ink: #243330;
                --foam: #f7f8f6;
                --pine: #1f6f68;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --ink: #f7f8f6;
                    --foam: #243330;
                }
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
                background: var(--foam);
                color: var(--ink);
                font-family: ui-sans-serif, system-ui, sans-serif;
            }

            main {
                display: flex;
                width: min(100%, 24rem);
                flex-direction: column;
                align-items: center;
                gap: 12px;
                text-align: center;
            }

            .mark {
                display: flex;
                height: 3rem;
                width: 3rem;
                align-items: center;
                justify-content: center;
                border-radius: 0.75rem;
                background: var(--pine);
                color: #f4f7f4;
                font-weight: 600;
            }

            h1 {
                margin: 0;
                font-size: 1.5rem;
                font-weight: 500;
            }

            p {
                margin: 0;
                font-size: 0.95rem;
                line-height: 1.5;
            }

            button {
                height: 2.75rem;
                margin-top: 8px;
                border: 0;
                border-radius: 0.75rem;
                background: var(--pine);
                color: #f4f7f4;
                padding: 0 1rem;
                font: inherit;
                font-size: 0.95rem;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        <main>
            <span class="mark">PP</span>
            <h1>You're offline</h1>
            <p>{{ $appName }} needs a connection to sign in and use the register. Reconnect, then try again.</p>
            <button type="button" onclick="location.reload()">Try again</button>
        </main>
    </body>
</html>
