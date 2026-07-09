{{--
    resources/views/layouts/public-presentation.blade.php
    Standalone, unauthenticated layout for the patient-facing Smart
    Presentation page. No sidebar, no topbar, no session — mirrors the
    same "no login" spirit as layouts/print.blade.php, but for a screen
    (mobile-first) rather than a printed page.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Your Treatment Plan') — {{ \App\Models\AppSetting::get('clinic_name', 'Dentfluence') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f9f3fa', 100: '#f3e8f4', 200: '#dfc5e1', 300: '#b95cb7',
                            400: '#8e24aa', 500: '#6a0f70', 600: '#4e0a53', 700: '#380740',
                        },
                    },
                },
            },
        };
    </script>
</head>
<body class="h-full bg-gray-50">
    <div class="min-h-screen max-w-lg mx-auto px-4 py-6">
        @yield('content')
    </div>
</body>
</html>
