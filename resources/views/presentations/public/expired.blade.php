@extends('layouts.public-presentation')
@section('title', 'Link No Longer Active')

@section('content')
    <div class="flex flex-col items-center justify-center min-h-[70vh] text-center">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 max-w-sm">
            <h1 class="text-lg font-semibold text-gray-700">This link is no longer active</h1>
            <p class="text-sm text-gray-400 mt-2">
                It may have expired or been replaced with a newer link. Please contact your clinic and they can resend it.
            </p>
        </div>
    </div>
@endsection
