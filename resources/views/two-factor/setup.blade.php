{{--
|=============================================================
| Two-Factor Authentication — Setup / Manage
| resources/views/two-factor/setup.blade.php
|=============================================================
--}}
@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">

    <h1 class="text-2xl font-semibold text-gray-800 mb-1">Two-Factor Authentication</h1>
    <p class="text-gray-500 mb-6">Add a second step at login using an authenticator app (Google Authenticator, Authy, etc.).</p>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    {{-- Recovery codes (shown once, right after enabling) --}}
    @if (session('recovery_codes'))
        <div class="mb-6 rounded-lg bg-amber-50 border border-amber-300 px-4 py-4">
            <h2 class="font-semibold text-amber-900 mb-2">Save your recovery codes</h2>
            <p class="text-sm text-amber-800 mb-3">
                Store these somewhere safe. Each one can be used <strong>once</strong> to log in if you lose your phone.
                You won't see them again.
            </p>
            <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                @foreach (session('recovery_codes') as $rc)
                    <div class="bg-white border border-amber-200 rounded px-3 py-1.5 text-center">{{ $rc }}</div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($enabled)
        {{-- Already on --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500"></span>
                <span class="font-medium text-gray-800">Two-factor authentication is ON for your account.</span>
            </div>

            <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-2">
                @csrf
                <label class="block text-sm text-gray-600 mb-1">Confirm your password to turn it off</label>
                <div class="flex gap-2">
                    <input type="password" name="password" required
                           class="flex-1 rounded-lg border-gray-300 focus:border-red-400 focus:ring-red-400"
                           placeholder="Your password">
                    <button type="submit"
                            class="rounded-lg bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm font-medium">
                        Turn off
                    </button>
                </div>
                @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </form>
        </div>
    @else
        {{-- Setup flow --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <ol class="space-y-6">
                <li>
                    <p class="font-medium text-gray-800 mb-2">1. Scan this QR code with your authenticator app</p>
                    <div class="inline-block p-3 bg-white border border-gray-200 rounded-lg">
                        <img src="{{ $qr }}" alt="Two-factor QR code" class="w-48 h-48">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        Can't scan? Enter this key manually:
                        <span class="font-mono bg-gray-100 px-2 py-0.5 rounded select-all">{{ $secret }}</span>
                    </p>
                </li>

                <li>
                    <p class="font-medium text-gray-800 mb-2">2. Enter the 6-digit code from the app</p>
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="flex gap-2 max-w-xs">
                        @csrf
                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                               required autofocus
                               class="flex-1 rounded-lg border-gray-300 focus:border-indigo-400 focus:ring-indigo-400 tracking-widest text-center font-mono"
                               placeholder="123456">
                        <button type="submit"
                                class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium">
                            Turn on
                        </button>
                    </form>
                    @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </li>
            </ol>
        </div>
    @endif

</div>
@endsection
