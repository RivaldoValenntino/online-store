<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>

    <script>
        function applyStoreTheme() {
            const theme = localStorage.getItem('theme') || 'light';

            document.documentElement.setAttribute('data-theme', theme);

            if (document.body) {
                document.body.setAttribute('data-theme', theme);
            }
        }

        applyStoreTheme();

        document.addEventListener('DOMContentLoaded', applyStoreTheme);
        document.addEventListener('livewire:navigated', applyStoreTheme);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-base-200 text-base-content">
    <main class="relative mx-auto min-h-screen max-w-md bg-base-100 pb-20 shadow-xl">
        {{ $slot }}

        <x-store.bottom-nav />
    </main>

    <x-store.flash-message />

    @livewireScripts
</body>

</html>
