{{--
|=============================================================
| Two-Factor Authentication — Login Challenge
| resources/views/two-factor/challenge.blade.php
| Standalone page (user has passed the password step but is
| not yet logged in).
|=============================================================
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify it's you — Dentfluence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style> body { font-family: 'Inter', system-ui, sans-serif; } </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 px-4">

    <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl border border-gray-100 p-8">
        <div class="text-center mb-6">
            <div class="mx-auto mb-3 w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-indigo-600" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 0h10.5a2.25 2.25 0 012.25 2.25v6A2.25 2.25 0 0116.5 21H7.5a2.25 2.25 0 01-2.25-2.25v-6a2.25 2.25 0 012.25-2.25z"/>
                </svg>
            </div>
            <h1 class="text-xl font-semibold text-gray-800">Verify it's you</h1>
            <p class="text-sm text-gray-500 mt-1">Enter the 6-digit code from your authenticator app.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.verify') }}">
            @csrf
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                   required autofocus
                   class="w-full rounded-lg border-gray-300 focus:border-indigo-400 focus:ring-indigo-400 text-center text-lg tracking-[0.4em] font-mono py-3"
                   placeholder="••••••">

            <button type="submit"
                    class="mt-4 w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5">
                Verify
            </button>
        </form>

        <p class="text-xs text-gray-400 text-center mt-4">
            Lost your phone? Enter one of your <strong>recovery codes</strong> above instead.
        </p>

        <div class="text-center mt-5">
            <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to login</a>
        </div>
    </div>

</body>
</html>
