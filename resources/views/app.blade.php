<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Pluf Pets — shop, grooming, and hotel for dogs and cats.">
        <meta name="theme-color" content="#1f6f68">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Employee Portal') }}">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/png" href="/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <title>{{ config('app.name', 'Employee Portal') }}</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <x-inertia::head />
    </head>
    <body class="min-h-screen bg-foam font-sans text-ink antialiased dark:bg-ink dark:text-foam">
        <x-inertia::app />
    </body>
</html>
