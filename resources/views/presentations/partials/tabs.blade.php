{{-- Internal nav for the Smart Presentation module. $active = 'index'|'links'|'settings' --}}
<div class="flex items-center gap-2 border-b border-gray-200 pb-0 mb-2">
    <a href="{{ route('presentations.index') }}"
       class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ ($active ?? '') === 'index' ? 'text-brand-700 border-brand-600' : 'text-gray-500 border-transparent hover:text-brand-700' }}">
        Presentations
    </a>
    <a href="{{ route('presentations.links') }}"
       class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ ($active ?? '') === 'links' ? 'text-brand-700 border-brand-600' : 'text-gray-500 border-transparent hover:text-brand-700' }}">
        Shared Links
    </a>
    <a href="{{ route('presentations.settings') }}"
       class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ ($active ?? '') === 'settings' ? 'text-brand-700 border-brand-600' : 'text-gray-500 border-transparent hover:text-brand-700' }}">
        Settings
    </a>
</div>
