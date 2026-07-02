{{-- partials/_cip-checklist.blade.php --}}
{{-- Used by the CIP Clinical Checklists section.                              --}}
{{-- Props: $items (array of string checklist items)                           --}}
{{-- Parent component must have cipChecklist() Alpine data bound via x-data.   --}}
<ul class="space-y-1.5">
    @foreach($items as $item)
    <li class="flex items-center gap-2 cursor-pointer group" @click="toggle('{{ addslashes($item) }}')">
        <span :class="isDone('{{ addslashes($item) }}')
                ? 'bg-emerald-500 border-emerald-500'
                : 'border-gray-300 group-hover:border-emerald-400'"
              class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-colors">
            <svg x-show="isDone('{{ addslashes($item) }}')" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 12 12">
                <path d="M10 3L5 8.5 2 5.5 1 6.5 5 10.5 11 4z"/>
            </svg>
        </span>
        <span :class="isDone('{{ addslashes($item) }}') ? 'line-through text-gray-400' : 'text-gray-700'"
              class="text-xs transition-colors">
            {{ $item }}
        </span>
    </li>
    @endforeach
</ul>
