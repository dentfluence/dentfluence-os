@extends('layouts.app')

@section('page-title', 'Marketing')

@section('head-extra')
{{-- FullCalendar for Calendar tab --}}
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
@endsection

@section('content')
<div
    x-data="marketingModule('{{ $activeTab }}')"
    class="flex flex-col h-full"
    style="min-height: calc(100vh - 56px);"
>

{{-- ══════════════════════════════════════════════════════
     TOP BAR — Module title + tab navigation
══════════════════════════════════════════════════════ --}}
<div class="bg-white border-b border-[#e8d5f0] sticky top-0 z-20">
    <div class="px-6 pt-5 pb-0 flex items-end justify-between">

        {{-- Title --}}
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">Dentfluence OS</p>
            <h1 class="text-2xl font-semibold text-[#380740] font-[Cormorant_Garamond] leading-tight">
                Marketing
            </h1>
        </div>

        {{-- Connection status pills --}}
        <div class="flex items-center gap-2 mb-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-[DM_Sans] px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>
                WordPress — Not connected
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-[DM_Sans] px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>
                Meta — Not connected
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-[DM_Sans] px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>
                Google — Not connected
            </span>
            <a href="#" class="inline-flex items-center gap-1 text-xs font-[DM_Sans] px-3 py-1.5 bg-[#6a0f70] text-white rounded hover:bg-[#380740] transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Connect
            </a>
        </div>
    </div>

    {{-- Tab navigation --}}
    <nav class="flex gap-0 px-6 mt-2" role="tablist">
        <template x-for="tab in tabs" :key="tab.id">
            <button
                @click="tab.id === 'library' ? window.location.href='{{ route('marketing.library') }}' : activeTab = tab.id"
                :class="activeTab === tab.id
                    ? 'border-b-2 border-[#6a0f70] text-[#380740] font-medium'
                    : 'border-b-2 border-transparent text-gray-400 hover:text-gray-600'"
                class="flex items-center gap-2 px-4 py-3 text-sm font-[DM_Sans] transition whitespace-nowrap"
                role="tab"
            >
                <span x-html="tab.icon" class="w-4 h-4 flex-shrink-0"></span>
                <span x-text="tab.label"></span>
                <span
                    x-show="tab.badge"
                    x-text="tab.badge"
                    class="text-xs px-1.5 py-0.5 rounded-full bg-[#f3e8f4] text-[#6a0f70] font-medium"
                ></span>
            </button>
        </template>
    </nav>
</div>

{{-- ══════════════════════════════════════════════════════
     TAB PANELS
══════════════════════════════════════════════════════ --}}
<div class="flex-1 overflow-auto bg-[#faf8fc]">

    {{-- ─────────────────────────────────────────
         TAB 1: PUBLISH
    ───────────────────────────────────────── --}}
    <div x-show="activeTab === 'publish'" class="p-6 space-y-6">

        {{-- Channel picker --}}
        <div class="flex items-center gap-3">
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">Publish to</p>
            <div class="flex gap-2">
                <button @click="publishChannel = 'blog'" :class="publishChannel==='blog' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-[#e8d5f0] hover:border-blue-400'" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border rounded transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Blog (WordPress)
                </button>
                <button @click="publishChannel = 'instagram'" :class="publishChannel==='instagram' ? 'bg-pink-600 text-white border-pink-600' : 'bg-white text-gray-600 border-[#e8d5f0] hover:border-pink-400'" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border rounded transition">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    Instagram
                </button>
                <button @click="publishChannel = 'facebook'" :class="publishChannel==='facebook' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-[#e8d5f0] hover:border-indigo-400'" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border rounded transition">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </button>
                <button @click="publishChannel = 'gbp'" :class="publishChannel==='gbp' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-[#e8d5f0] hover:border-green-400'" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border rounded transition">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
                    Google Business
                </button>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6">

            {{-- ── Composer (left 2/3) --}}
            <div class="col-span-2 space-y-4">

                {{-- Blog Composer --}}
                <div x-show="publishChannel === 'blog'" class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-[#f3e8f4] bg-[#faf8fc]">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Blog Post Composer</span>
                        <div class="flex gap-1">
                            <button class="px-2 py-1 text-xs font-[DM_Sans] border border-[#e8d5f0] rounded hover:bg-[#f3e8f4] text-gray-600">B</button>
                            <button class="px-2 py-1 text-xs font-[DM_Sans] border border-[#e8d5f0] rounded hover:bg-[#f3e8f4] text-gray-600 italic">I</button>
                            <button class="px-2 py-1 text-xs font-[DM_Sans] border border-[#e8d5f0] rounded hover:bg-[#f3e8f4] text-gray-600">H1</button>
                            <button class="px-2 py-1 text-xs font-[DM_Sans] border border-[#e8d5f0] rounded hover:bg-[#f3e8f4] text-gray-600">H2</button>
                            <button class="px-2 py-1 text-xs font-[DM_Sans] border border-[#e8d5f0] rounded hover:bg-[#f3e8f4] text-gray-600"></button>
                            <button class="px-2 py-1 text-xs font-[DM_Sans] border border-[#e8d5f0] rounded hover:bg-[#f3e8f4] text-gray-600"></button>
                        </div>
                    </div>
                    <input type="text" placeholder="Post title..." class="w-full px-4 py-3 text-lg font-[Cormorant_Garamond] text-[#380740] border-b border-[#f3e8f4] focus:outline-none bg-white placeholder-gray-300">
                    <div contenteditable="true" class="w-full px-4 py-4 min-h-[180px] text-sm font-[DM_Sans] text-gray-600 focus:outline-none" style="line-height:1.7">
                        <p class="text-gray-400">Start writing your blog post here...</p>
                    </div>
                    {{-- SEO Fields --}}
                    <div class="border-t border-[#f3e8f4] bg-[#faf8fc] px-4 py-3 space-y-2">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-widest font-[DM_Sans]">SEO Settings</p>
                        <input type="text" placeholder="Title tag (for Google search)" class="w-full px-3 py-2 text-sm font-[DM_Sans] border border-[#e8d5f0] rounded focus:outline-none focus:border-[#6a0f70] bg-white">
                        <input type="text" placeholder="Meta description (155 characters max)" class="w-full px-3 py-2 text-sm font-[DM_Sans] border border-[#e8d5f0] rounded focus:outline-none focus:border-[#6a0f70] bg-white">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 font-[DM_Sans]">yoursite.com/blog/</span>
                            <input type="text" placeholder="url-slug" class="flex-1 px-3 py-2 text-sm font-[DM_Sans] border border-[#e8d5f0] rounded focus:outline-none focus:border-[#6a0f70] bg-white">
                        </div>
                    </div>
                </div>

                {{-- Instagram / Facebook Composer --}}
                <div x-show="publishChannel === 'instagram' || publishChannel === 'facebook'" class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-[#f3e8f4] bg-[#faf8fc]">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]" x-text="publishChannel === 'instagram' ? 'Instagram Post Composer' : 'Facebook Post Composer'"></span>
                        <div class="flex gap-2">
                            <button x-show="publishChannel === 'facebook'" class="px-2 py-1 text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 rounded font-[DM_Sans]">FB only</button>
                            <button x-show="publishChannel === 'instagram'" class="px-2 py-1 text-xs bg-pink-50 text-pink-700 border border-pink-200 rounded font-[DM_Sans]">IG only</button>
                            <button class="px-2 py-1 text-xs bg-[#f3e8f4] text-[#6a0f70] border border-[#dfc5e1] rounded font-[DM_Sans]">Both</button>
                        </div>
                    </div>
                    {{-- Image zone --}}
                    <div class="mx-4 mt-4 h-36 border-2 border-dashed border-[#e8d5f0] rounded-lg flex items-center justify-center bg-[#faf8fc] cursor-pointer hover:border-[#b95cb7] transition group">
                        <div class="text-center">
                            <svg class="w-8 h-8 text-gray-300 mx-auto mb-2 group-hover:text-[#b95cb7] transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M13.5 12h.008v.008H13.5V12zm2.25 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                            <p class="text-xs text-gray-400 font-[DM_Sans]">Click to select from <span class="text-[#6a0f70] font-medium">Marketing Library</span></p>
                        </div>
                    </div>
                    <div class="px-4 mt-3">
                        <textarea placeholder="Write your caption..." rows="4" class="w-full px-3 py-2 text-sm font-[DM_Sans] border border-[#e8d5f0] rounded focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                        <div class="flex items-center justify-between mt-1">
                            <div class="flex gap-1.5 flex-wrap">
                                <span class="text-xs px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full font-[DM_Sans] cursor-pointer hover:bg-blue-100">#dentalimplants</span>
                                <span class="text-xs px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full font-[DM_Sans] cursor-pointer hover:bg-blue-100">#smilemakeover</span>
                                <span class="text-xs px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full font-[DM_Sans] cursor-pointer hover:bg-blue-100">#dentist</span>
                                <span class="text-xs px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full font-[DM_Sans] cursor-pointer hover:bg-blue-100">#oralhealth</span>
                                <span class="text-xs text-gray-400 font-[DM_Sans]">+ more</span>
                            </div>
                            <span class="text-xs text-gray-400 font-[DM_Sans]">0 / 2200</span>
                        </div>
                    </div>
                    <div class="px-4 pb-4 mt-2"></div>
                </div>

                {{-- GBP Composer --}}
                <div x-show="publishChannel === 'gbp'" class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-[#f3e8f4] bg-[#faf8fc]">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Google Business Post</span>
                        <div class="flex gap-2">
                            <button class="px-2 py-1 text-xs bg-green-50 text-green-700 border border-green-200 rounded font-[DM_Sans]">What's New</button>
                            <button class="px-2 py-1 text-xs bg-white text-gray-600 border border-[#e8d5f0] rounded font-[DM_Sans]">Offer</button>
                            <button class="px-2 py-1 text-xs bg-white text-gray-600 border border-[#e8d5f0] rounded font-[DM_Sans]">Event</button>
                        </div>
                    </div>
                    <div class="mx-4 mt-4 h-28 border-2 border-dashed border-[#e8d5f0] rounded-lg flex items-center justify-center bg-[#faf8fc] cursor-pointer hover:border-[#b95cb7] transition">
                        <p class="text-xs text-gray-400 font-[DM_Sans]">Select image from <span class="text-[#6a0f70] font-medium">Marketing Library</span></p>
                    </div>
                    <div class="px-4 mt-3 space-y-2">
                        <textarea placeholder="Write your GBP post here (300 characters)..." rows="3" class="w-full px-3 py-2 text-sm font-[DM_Sans] border border-[#e8d5f0] rounded focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                        <div class="flex gap-2">
                            <select class="flex-1 px-3 py-2 text-sm font-[DM_Sans] border border-[#e8d5f0] rounded focus:outline-none focus:border-[#6a0f70] bg-white text-gray-500">
                                <option>CTA Button (optional)</option>
                                <option>Call Now</option>
                                <option>Book</option>
                                <option>Learn More</option>
                            </select>
                        </div>
                    </div>
                    {{-- Fallback notice --}}
                    <div class="mx-4 mb-4 mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg flex gap-2">
                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <p class="text-xs text-amber-700 font-[DM_Sans]">Google Business not connected. Post will be formatted for manual copy-paste into your GBP dashboard.</p>
                    </div>
                </div>

                {{-- Action bar --}}
                <div class="flex items-center justify-between bg-white border border-[#e8d5f0] rounded-lg px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button class="flex items-center gap-1.5 text-sm font-[DM_Sans] text-gray-500 hover:text-[#380740] transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Schedule
                        </button>
                        <input type="datetime-local" class="text-sm font-[DM_Sans] border border-[#e8d5f0] rounded px-2 py-1 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div class="flex gap-2">
                        <button class="px-4 py-2 text-sm font-[DM_Sans] border border-[#e8d5f0] rounded text-gray-600 hover:bg-[#f3e8f4] transition">Save Draft</button>
                        <button class="px-4 py-2 text-sm font-[DM_Sans] bg-[#6a0f70] text-white rounded hover:bg-[#380740] transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Publish Now
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Right panel: Recent posts + Marketing Library preview --}}
            <div class="space-y-4">
                {{-- Posts History --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4] flex items-center justify-between">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Recent Posts</span>
                        <a href="#" class="text-xs text-[#6a0f70] font-[DM_Sans] hover:underline">View all</a>
                    </div>
                    <div class="divide-y divide-[#f3e8f4]">
                        @foreach([
                            ['Before/After Smile Makeover','Instagram','Published','2d ago','pink'],
                            ['5 Reasons to Get Implants','Blog','Published','5d ago','blue'],
                            ['Festive Whitening Offer','GBP','Published','1w ago','green'],
                            ['Aligner Journey — Patient Story','Facebook','Draft','–','indigo'],
                        ] as $post)
                        <div class="px-4 py-3 flex items-start gap-3 hover:bg-[#faf8fc]">
                            <span class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0 {{ $post[4] === 'pink' ? 'bg-pink-400' : ($post[4] === 'blue' ? 'bg-blue-400' : ($post[4] === 'green' ? 'bg-green-400' : 'bg-indigo-400')) }}"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-[#380740] font-[DM_Sans] truncate">{{ $post[0] }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-gray-400 font-[DM_Sans]">{{ $post[1] }}</span>
                                    <span class="text-xs font-[DM_Sans] {{ $post[2] === 'Published' ? 'text-green-600' : 'text-amber-600' }}">{{ $post[2] }}</span>
                                    <span class="text-xs text-gray-300 font-[DM_Sans]">{{ $post[3] }}</span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Marketing Library mini --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4] flex items-center justify-between">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Marketing Library</span>
                        <span class="text-xs text-gray-400 font-[DM_Sans]">0 photos</span>
                    </div>
                    <div class="p-4">
                        <div class="h-24 border-2 border-dashed border-[#e8d5f0] rounded-lg flex flex-col items-center justify-center text-center bg-[#faf8fc]">
                            <svg class="w-6 h-6 text-gray-300 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M13.5 12h.008v.008H13.5V12z"/></svg>
                            <p class="text-xs text-gray-400 font-[DM_Sans]">No approved photos yet</p>
                            <a href="{{ route('cms.marketing') }}" class="text-xs text-[#6a0f70] font-[DM_Sans] mt-1 hover:underline">Go to Content Management →</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ─────────────────────────────────────────
         TAB 2: CONTENT CALENDAR
    ───────────────────────────────────────── --}}
    <div x-show="activeTab === 'calendar'" class="p-6">

        {{-- Calendar header --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[#380740] font-[Cormorant_Garamond]">June 2026</h2>
                <button class="p-1 hover:bg-[#f3e8f4] rounded"><svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
                <button class="p-1 hover:bg-[#f3e8f4] rounded"><svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
            </div>
            <div class="flex items-center gap-3">
                {{-- Channel filters --}}
                <div class="flex gap-1.5">
                    <span class="flex items-center gap-1 text-xs font-[DM_Sans] px-2 py-1 bg-blue-50 text-blue-700 rounded-full border border-blue-200 cursor-pointer">
                        <span class="w-2 h-2 bg-blue-500 rounded-full inline-block"></span> Blog
                    </span>
                    <span class="flex items-center gap-1 text-xs font-[DM_Sans] px-2 py-1 bg-pink-50 text-pink-700 rounded-full border border-pink-200 cursor-pointer">
                        <span class="w-2 h-2 bg-pink-500 rounded-full inline-block"></span> Instagram
                    </span>
                    <span class="flex items-center gap-1 text-xs font-[DM_Sans] px-2 py-1 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-200 cursor-pointer">
                        <span class="w-2 h-2 bg-indigo-500 rounded-full inline-block"></span> Facebook
                    </span>
                    <span class="flex items-center gap-1 text-xs font-[DM_Sans] px-2 py-1 bg-green-50 text-green-700 rounded-full border border-green-200 cursor-pointer">
                        <span class="w-2 h-2 bg-green-500 rounded-full inline-block"></span> GBP
                    </span>
                </div>
                <button class="flex items-center gap-1.5 px-3 py-1.5 bg-[#6a0f70] text-white text-xs font-[DM_Sans] rounded hover:bg-[#380740] transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New Post
                </button>
            </div>
        </div>

        {{-- Calendar grid --}}
        <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
            {{-- Day labels --}}
            <div class="grid grid-cols-7 border-b border-[#e8d5f0]">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                <div class="px-3 py-2 text-xs font-medium text-gray-400 uppercase tracking-widest font-[DM_Sans] text-center border-r last:border-r-0 border-[#f3e8f4]">{{ $day }}</div>
                @endforeach
            </div>

            {{-- Calendar cells (June 2026 — starts Monday June 1) --}}
            @php
            $calendarData = [
                // Week 1
                ['date'=>'', 'posts'=>[], 'gap'=>false],
                ['date'=>'1', 'posts'=>[['label'=>'Implant Blog','color'=>'blue']], 'gap'=>false],
                ['date'=>'2', 'posts'=>[], 'gap'=>false],
                ['date'=>'3', 'posts'=>[['label'=>'Before/After IG','color'=>'pink']], 'gap'=>false],
                ['date'=>'4', 'posts'=>[], 'gap'=>false],
                ['date'=>'5', 'posts'=>[], 'gap'=>false],
                ['date'=>'6', 'posts'=>[], 'gap'=>false],
                // Week 2
                ['date'=>'7', 'posts'=>[], 'gap'=>true],
                ['date'=>'8', 'posts'=>[], 'gap'=>true],
                ['date'=>'9', 'posts'=>[], 'gap'=>true],
                ['date'=>'10', 'posts'=>[], 'gap'=>true],
                ['date'=>'11', 'posts'=>[], 'gap'=>true],
                ['date'=>'12', 'posts'=>[['label'=>'GBP: Whitening','color'=>'green']], 'gap'=>false],
                ['date'=>'13', 'posts'=>[], 'gap'=>false],
                // Week 3
                ['date'=>'14', 'posts'=>[], 'gap'=>false],
                ['date'=>'15', 'posts'=>[['label'=>'FB: Testimonial','color'=>'indigo'],['label'=>'IG: Crown','color'=>'pink']], 'gap'=>false],
                ['date'=>'16', 'posts'=>[], 'gap'=>false],
                ['date'=>'17', 'posts'=>[], 'gap'=>false],
                ['date'=>'18', 'posts'=>[], 'gap'=>false],
                ['date'=>'19', 'posts'=>[['label'=>'Blog: RCT Guide','color'=>'blue']], 'gap'=>false],
                ['date'=>'20', 'posts'=>[], 'gap'=>false],
                // Week 4
                ['date'=>'21', 'posts'=>[], 'gap'=>false],
                ['date'=>'22', 'posts'=>[], 'gap'=>false],
                ['date'=>'23', 'posts'=>[], 'gap'=>false],
                ['date'=>'24', 'posts'=>[], 'gap'=>false],
                ['date'=>'25', 'posts'=>[['label'=>'GBP: Offer','color'=>'green']], 'gap'=>false],
                ['date'=>'26', 'posts'=>[], 'gap'=>false],
                ['date'=>'27', 'posts'=>[], 'gap'=>false],
                // Week 5
                ['date'=>'28', 'posts'=>[['label'=>'IG: Team Photo','color'=>'pink']], 'gap'=>false],
                ['date'=>'29', 'posts'=>[], 'gap'=>false],
                ['date'=>'30', 'posts'=>[], 'gap'=>false],
                ['date'=>'', 'posts'=>[], 'gap'=>false],
                ['date'=>'', 'posts'=>[], 'gap'=>false],
                ['date'=>'', 'posts'=>[], 'gap'=>false],
                ['date'=>'', 'posts'=>[], 'gap'=>false],
            ];
            $colorMap = ['blue'=>'bg-blue-100 text-blue-700 border-blue-200','pink'=>'bg-pink-100 text-pink-700 border-pink-200','indigo'=>'bg-indigo-100 text-indigo-700 border-indigo-200','green'=>'bg-green-100 text-green-700 border-green-200'];
            @endphp

            <div class="grid grid-cols-7" style="grid-auto-rows: 100px;">
                @foreach($calendarData as $cell)
                <div class="border-r border-b last-of-type:border-r-0 border-[#f3e8f4] p-1.5 {{ $cell['gap'] ? 'bg-amber-50' : ($cell['date'] === '' ? 'bg-gray-50' : 'bg-white') }} relative group hover:bg-[#faf8fc] cursor-pointer transition">
                    @if($cell['date'])
                    <div class="flex items-start justify-between">
                        <span class="text-xs font-medium font-[DM_Sans] {{ $cell['date'] == '3' ? 'text-white bg-[#6a0f70] w-5 h-5 flex items-center justify-center rounded-full text-center' : 'text-gray-400' }}">{{ $cell['date'] }}</span>
                        @if($cell['gap'])
                        <span title="Content gap — no post in 5+ days" class="text-amber-400">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        </span>
                        @endif
                    </div>
                    <div class="mt-1 space-y-0.5">
                        @foreach($cell['posts'] as $post)
                        <div class="text-xs px-1.5 py-0.5 rounded border font-[DM_Sans] truncate {{ $colorMap[$post['color']] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">{{ $post['label'] }}</div>
                        @endforeach
                    </div>
                    {{-- Add post on hover --}}
                    <button class="absolute bottom-1 right-1 opacity-0 group-hover:opacity-100 transition w-5 h-5 bg-[#6a0f70] text-white rounded-full flex items-center justify-center text-xs">+</button>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- Gap warning banner --}}
        <div class="mt-4 flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            <p class="text-sm text-amber-700 font-[DM_Sans]"><strong>Content gap detected:</strong> June 7–11 has no scheduled posts (5 days). <a href="#" class="underline font-medium">Fill the gap →</a></p>
        </div>
    </div>

    {{-- ─────────────────────────────────────────
         TAB 3: IDEAS & BRAINSTORM
    ───────────────────────────────────────── --}}
    <div x-show="activeTab === 'ideas'" class="p-6 space-y-6">

        {{-- Seasonal prompt banner --}}
        <div class="flex items-center gap-3 p-4 bg-gradient-to-r from-orange-50 to-yellow-50 border border-orange-200 rounded-lg">
            <span class="text-2xl"></span>
            <div class="flex-1">
                <p class="text-sm font-medium text-orange-800 font-[DM_Sans]">Diwali is 30 days away</p>
                <p class="text-xs text-orange-600 font-[DM_Sans]">Plan festive smile content, whitening offers, and before/after posts for the festive season.</p>
            </div>
            <button class="px-3 py-1.5 bg-orange-600 text-white text-xs font-[DM_Sans] rounded hover:bg-orange-700 transition">Plan Content</button>
        </div>

        <div class="grid grid-cols-3 gap-6">

            {{-- Idea bank --}}
            <div class="col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-[#380740] font-[DM_Sans]">Idea Bank</h3>
                    <div class="flex gap-2">
                        <select class="text-xs font-[DM_Sans] border border-[#e8d5f0] rounded px-2 py-1 focus:outline-none bg-white text-gray-600">
                            <option>All Treatments</option>
                            <option>Implants</option>
                            <option>Aligners</option>
                            <option>Whitening</option>
                            <option>RCT</option>
                            <option>Crown</option>
                        </select>
                        <select class="text-xs font-[DM_Sans] border border-[#e8d5f0] rounded px-2 py-1 focus:outline-none bg-white text-gray-600">
                            <option>All Formats</option>
                            <option>Reel Idea</option>
                            <option>Carousel</option>
                            <option>Blog Topic</option>
                            <option>GBP Post</option>
                        </select>
                        <button class="px-3 py-1 bg-[#6a0f70] text-white text-xs font-[DM_Sans] rounded hover:bg-[#380740] transition">+ Add Idea</button>
                    </div>
                </div>

                <div class="space-y-2">
                    @foreach([
                        ['','Reel Idea','Implants','Before & after smile transformation — timelapse over treatment','Saved','amber'],
                        ['','Carousel','Aligners','5 myths about aligners debunked — swipeable carousel post','Saved','amber'],
                        ['','Blog Topic','Whitening','Is teeth whitening safe? What dentists want you to know','Draft','blue'],
                        ['','GBP Post','Crown','Same-day crown technology — now available at our clinic','Saved','amber'],
                        ['','Reel Idea','RCT','Does root canal hurt? Reality vs myths — 30-second reel','Not started','gray'],
                        ['','Carousel','Smile Makeover','Patient journey: from consultation to final smile reveal','Draft','blue'],
                    ] as $idea)
                    <div class="bg-white border border-[#e8d5f0] rounded-lg px-4 py-3 flex items-start gap-3 hover:border-[#b95cb7] transition group">
                        <span class="text-lg">{{ $idea[0] }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-xs px-1.5 py-0.5 bg-[#f3e8f4] text-[#6a0f70] rounded font-[DM_Sans]">{{ $idea[1] }}</span>
                                <span class="text-xs text-gray-400 font-[DM_Sans]">{{ $idea[2] }}</span>
                            </div>
                            <p class="text-sm text-gray-700 font-[DM_Sans]">{{ $idea[3] }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-xs font-[DM_Sans] {{ $idea[5]==='amber' ? 'text-amber-600' : ($idea[5]==='blue' ? 'text-blue-600' : 'text-gray-400') }}">{{ $idea[4] }}</span>
                            <button class="opacity-0 group-hover:opacity-100 transition px-2.5 py-1 text-xs bg-[#6a0f70] text-white rounded font-[DM_Sans]">Convert to Draft</button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Seasonal prompts sidebar --}}
            <div class="space-y-4">
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4]">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Upcoming Moments</span>
                    </div>
                    <div class="divide-y divide-[#f3e8f4]">
                        @foreach([
                            ['','Diwali','Oct 20','30d'],
                            ['','World Oral Health Day','Mar 20','—'],
                            ['','Christmas','Dec 25','—'],
                            ['','New Year','Jan 1','—'],
                            ['',"Valentine's Day",'Feb 14','—'],
                        ] as $event)
                        <div class="px-4 py-3 flex items-center gap-3">
                            <span class="text-base">{{ $event[0] }}</span>
                            <div class="flex-1">
                                <p class="text-xs font-medium text-gray-700 font-[DM_Sans]">{{ $event[1] }}</p>
                                <p class="text-xs text-gray-400 font-[DM_Sans]">{{ $event[2] }}</p>
                            </div>
                            @if($event[3] !== '—')
                            <span class="text-xs text-orange-600 bg-orange-50 border border-orange-200 px-1.5 py-0.5 rounded-full font-[DM_Sans]">{{ $event[3] }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────
         TAB 4: ANALYTICS
    ───────────────────────────────────────── --}}
    <div x-show="activeTab === 'analytics'" class="p-6 space-y-6">

        {{-- Date range + refresh --}}
        <div class="flex items-center justify-between">
            <div class="flex gap-1">
                @foreach(['This Week','This Month','Last 30 Days','Custom'] as $range)
                <button class="px-3 py-1.5 text-xs font-[DM_Sans] rounded border {{ $loop->index === 1 ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-600 border-[#e8d5f0] hover:border-[#b95cb7]' }} transition">{{ $range }}</button>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 font-[DM_Sans]">Last refreshed: — (not connected)</span>
                <button class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border border-[#e8d5f0] rounded hover:bg-[#f3e8f4] text-gray-600 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    Refresh
                </button>
            </div>
        </div>

        {{-- Summary KPI cards --}}
        <div class="grid grid-cols-4 gap-4">
            @foreach([
                ['Total Reach','—','All channels','#6a0f70'],
                ['Website Visitors','—','via GA4','blue'],
                ['GBP Calls','—','this month','green'],
                ['Posts Published','3','this month','#380740'],
            ] as $kpi)
            <div class="bg-white border border-[#e8d5f0] rounded-lg p-4">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">{{ $kpi[0] }}</p>
                <p class="text-3xl font-semibold font-[Cormorant_Garamond] mt-1 {{ $kpi[3]==='blue' ? 'text-blue-700' : ($kpi[3]==='green' ? 'text-green-700' : 'text-[#380740]') }}">{{ $kpi[1] }}</p>
                <p class="text-xs text-gray-400 font-[DM_Sans] mt-0.5">{{ $kpi[2] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Channel breakdowns + Top post --}}
        <div class="grid grid-cols-3 gap-6">

            {{-- Channel cards (2/3) --}}
            <div class="col-span-2 space-y-4">

                {{-- Meta Insights --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-pink-400"></span>
                            <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Instagram + Facebook</span>
                        </div>
                        <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full font-[DM_Sans]">Not connected</span>
                    </div>
                    <div class="grid grid-cols-4 divide-x divide-[#f3e8f4] p-0">
                        @foreach([['Followers','—'],['Reach','—'],['Impressions','—'],['Top Post Likes','—']] as $m)
                        <div class="p-4 text-center">
                            <p class="text-2xl font-semibold font-[Cormorant_Garamond] text-[#380740]">{{ $m[1] }}</p>
                            <p class="text-xs text-gray-400 font-[DM_Sans] mt-0.5">{{ $m[0] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- GA4 --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                            <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Website (Google Analytics)</span>
                        </div>
                        <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full font-[DM_Sans]">Not connected</span>
                    </div>
                    <div class="grid grid-cols-4 divide-x divide-[#f3e8f4]">
                        @foreach([['Sessions','—'],['Blog Views','—'],['Bounce Rate','—'],['Top Page','—']] as $m)
                        <div class="p-4 text-center">
                            <p class="text-2xl font-semibold font-[Cormorant_Garamond] text-[#380740]">{{ $m[1] }}</p>
                            <p class="text-xs text-gray-400 font-[DM_Sans] mt-0.5">{{ $m[0] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- GBP --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                            <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Google Business Profile</span>
                        </div>
                        <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full font-[DM_Sans]">Not connected</span>
                    </div>
                    <div class="grid grid-cols-4 divide-x divide-[#f3e8f4]">
                        @foreach([['Search Views','—'],['Map Views','—'],['Website Clicks','—'],['Calls','—']] as $m)
                        <div class="p-4 text-center">
                            <p class="text-2xl font-semibold font-[Cormorant_Garamond] text-[#380740]">{{ $m[1] }}</p>
                            <p class="text-xs text-gray-400 font-[DM_Sans] mt-0.5">{{ $m[0] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Top performing post (1/3) --}}
            <div class="space-y-4">
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4]">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Top Performing Post</span>
                    </div>
                    <div class="p-4">
                        <div class="h-32 bg-gradient-to-br from-[#f3e8f4] to-[#e8d5f0] rounded-lg flex items-center justify-center mb-3">
                            <p class="text-xs text-gray-400 font-[DM_Sans]">No data yet</p>
                        </div>
                        <p class="text-sm font-medium text-gray-400 font-[DM_Sans]">Connect your channels to see top performing content</p>
                    </div>
                </div>

                {{-- Connect CTA --}}
                <div class="bg-gradient-to-br from-[#380740] to-[#6a0f70] rounded-lg p-4 text-white">
                    <p class="text-sm font-medium font-[DM_Sans] mb-1">Connect your channels</p>
                    <p class="text-xs text-[#dfc5e1] font-[DM_Sans] mb-3">Link Instagram, GA4, and GBP to see real data here.</p>
                    <a href="#" class="block text-center px-3 py-2 bg-white text-[#380740] text-xs font-medium font-[DM_Sans] rounded hover:bg-[#f3e8f4] transition">Go to Integrations →</a>
                </div>
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────
         TAB 5: ACCOUNTABILITY
    ───────────────────────────────────────── --}}
    <div x-show="activeTab === 'accountability'" class="p-6 space-y-6">

        <div class="grid grid-cols-3 gap-6">

            {{-- Left: Weekly Score + Planned vs Published --}}
            <div class="col-span-2 space-y-6">

                {{-- Weekly Marketing Score --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4] flex items-center justify-between">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Weekly Marketing Score</span>
                        <span class="text-xs text-gray-400 font-[DM_Sans]">Auto-calculated every Monday</span>
                    </div>
                    <div class="p-4 flex gap-6 items-start">
                        {{-- Score circle --}}
                        <div class="flex flex-col items-center justify-center w-24 h-24 rounded-full border-4 border-[#6a0f70] flex-shrink-0">
                            <span class="text-3xl font-bold text-[#380740] font-[Cormorant_Garamond]">72</span>
                            <span class="text-xs text-[#6a0f70] font-[DM_Sans]">Good</span>
                        </div>
                        <div class="flex-1 space-y-2">
                            <div>
                                <div class="flex justify-between text-xs font-[DM_Sans] mb-1">
                                    <span class="text-gray-500">Consistency <span class="text-gray-400">(40%)</span></span>
                                    <span class="text-[#380740] font-medium">30/40</span>
                                </div>
                                <div class="h-2 bg-[#f3e8f4] rounded-full"><div class="h-2 bg-[#6a0f70] rounded-full" style="width:75%"></div></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-[DM_Sans] mb-1">
                                    <span class="text-gray-500">Engagement <span class="text-gray-400">(30%)</span></span>
                                    <span class="text-[#380740] font-medium">22/30</span>
                                </div>
                                <div class="h-2 bg-[#f3e8f4] rounded-full"><div class="h-2 bg-[#b95cb7] rounded-full" style="width:73%"></div></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-[DM_Sans] mb-1">
                                    <span class="text-gray-500">GBP Activity <span class="text-gray-400">(15%)</span></span>
                                    <span class="text-[#380740] font-medium">12/15</span>
                                </div>
                                <div class="h-2 bg-[#f3e8f4] rounded-full"><div class="h-2 bg-green-400 rounded-full" style="width:80%"></div></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-[DM_Sans] mb-1">
                                    <span class="text-gray-500">Website Traffic <span class="text-gray-400">(15%)</span></span>
                                    <span class="text-[#380740] font-medium">8/15</span>
                                </div>
                                <div class="h-2 bg-[#f3e8f4] rounded-full"><div class="h-2 bg-blue-400 rounded-full" style="width:53%"></div></div>
                            </div>
                            <p class="text-xs text-gray-500 font-[DM_Sans] pt-1 border-t border-[#f3e8f4]">Good consistency this week. Engagement above average. Website traffic dipped — consider publishing a new blog post.</p>
                        </div>
                    </div>
                </div>

                {{-- Planned vs Published --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4] flex items-center justify-between">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Posts Planned vs Published — June 2026</span>
                    </div>
                    <table class="w-full text-sm font-[DM_Sans]">
                        <thead>
                            <tr class="border-b border-[#f3e8f4] bg-[#faf8fc]">
                                <th class="text-left px-4 py-2 text-xs text-gray-400 uppercase tracking-widest font-medium">Post</th>
                                <th class="text-left px-4 py-2 text-xs text-gray-400 uppercase tracking-widest font-medium">Channel</th>
                                <th class="text-left px-4 py-2 text-xs text-gray-400 uppercase tracking-widest font-medium">Planned</th>
                                <th class="text-left px-4 py-2 text-xs text-gray-400 uppercase tracking-widest font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f3e8f4]">
                            @foreach([
                                ['Implant Blog Post','Blog','Jun 1','On Time','green'],
                                ['Before/After IG','Instagram','Jun 3','On Time','green'],
                                ['Whitening GBP Post','GBP','Jun 12','On Time','green'],
                                ['Aligner Reel','Instagram','Jun 10','Missed','red'],
                                ['RCT Blog','Blog','Jun 19','Scheduled','blue'],
                                ['Team Photo IG','Instagram','Jun 28','Scheduled','blue'],
                            ] as $row)
                            <tr class="hover:bg-[#faf8fc]">
                                <td class="px-4 py-2.5 text-gray-700">{{ $row[0] }}</td>
                                <td class="px-4 py-2.5 text-gray-500">{{ $row[1] }}</td>
                                <td class="px-4 py-2.5 text-gray-500">{{ $row[2] }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="text-xs {{ $row[4]==='green' ? 'text-green-700 bg-green-50' : ($row[4]==='red' ? 'text-red-700 bg-red-50' : 'text-blue-700 bg-blue-50') }} px-2 py-0.5 rounded-full">{{ $row[3] }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Right: Monthly Goals + Report --}}
            <div class="space-y-4">

                {{-- Monthly Goals --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4] flex items-center justify-between">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">June Goals</span>
                        <button class="text-xs text-[#6a0f70] font-[DM_Sans] hover:underline">Edit</button>
                    </div>
                    <div class="p-4 space-y-4">
                        @foreach([
                            ['Posts Published','3','8','38'],
                            ['GBP Views','—','500','0'],
                            ['Website Visitors','—','2000','0'],
                            ['Follower Growth','—','100','0'],
                        ] as $goal)
                        <div>
                            <div class="flex justify-between text-xs font-[DM_Sans] mb-1">
                                <span class="text-gray-600">{{ $goal[0] }}</span>
                                <span class="text-gray-400">{{ $goal[1] }} / {{ $goal[2] }}</span>
                            </div>
                            <div class="h-2 bg-[#f3e8f4] rounded-full">
                                <div class="h-2 bg-[#6a0f70] rounded-full transition-all" style="width:{{ $goal[3] }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Monthly Report --}}
                <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#f3e8f4]">
                        <span class="text-sm font-medium text-[#380740] font-[DM_Sans]">Monthly Report</span>
                    </div>
                    <div class="p-4">
                        <p class="text-xs text-gray-500 font-[DM_Sans] mb-3">Auto-generate a PDF report with all KPIs, top posts, and goal achievement for the month. Share directly with your client.</p>
                        <button class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-[#6a0f70] text-white text-sm font-[DM_Sans] rounded hover:bg-[#380740] transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Generate June Report
                        </button>
                        <p class="text-xs text-gray-400 font-[DM_Sans] mt-2 text-center">Available at month end</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>{{-- end tab panels --}}

</div>{{-- end x-data --}}

<script>
function marketingModule(initialTab) {
    return {
        activeTab: initialTab || 'publish',
        publishChannel: 'blog',
        tabs: [
            {
                id: 'publish',
                label: 'Publish',
                icon: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
                badge: null,
            },
            {
                id: 'calendar',
                label: 'Content Calendar',
                icon: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>',
                badge: '1 gap',
            },
            {
                id: 'ideas',
                label: 'Ideas',
                icon: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg>',
                badge: '6',
            },
            {
                id: 'analytics',
                label: 'Analytics',
                icon: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>',
                badge: null,
            },
            {
                id: 'accountability',
                label: 'Accountability',
                icon: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                badge: null,
            },
            {
                id: 'library',
                label: 'Library',
                icon: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 21h18M3 10.5h18M3 7.5h18M12 3v3"/></svg>',
                badge: null,
            },
        ],
    }
}
</script>
@endsection
