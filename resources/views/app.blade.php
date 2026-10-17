<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Employee sign-in">
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
