{{--
    DEPRECATED — do not @include this file.

    Blade's @include renders a separate view instance, so @php variables set
    inside it never leak back into the parent view — every print view that
    included this caused "Undefined variable $printMarginTop".

    Use App\Models\AppSetting::printMargins() directly instead, e.g.:

        @php $pm = \App\Models\AppSetting::printMargins(['top'=>'16mm','bottom'=>'16mm','left'=>'14mm','right'=>'14mm']); @endphp
        body { padding: {{ $pm['top'] }} {{ $pm['right'] }} {{ $pm['bottom'] }} {{ $pm['left'] }}; }
        @page { margin: 0; }

    This file is intentionally left as a dead stub (cannot be deleted from
    this session) — safe to delete manually.
--}}
