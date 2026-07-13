{{--
    partials/shade-select-assets.blade.php
    Shared shade-guide data + Alpine mixin for partials.shade-select.
    Include this ONCE per page, outside any loop, before any component
    that uses the shade selector initializes.

    Any Alpine component embedding partials.shade-select must spread
    shadeSelectMixin() into its x-data, e.g.:
        function myForm() {
            return { ...toothChartMixin(), ...shadeSelectMixin(), ... };
        }
--}}
@once
<script>
    // { vita_classical: { label, shades: [...] }, vita_3d_master: { label, shades: [...] } }
    window.SHADE_GUIDES = @json(collect(\App\Models\LabCase::SHADE_GUIDE_LABELS)->mapWithKeys(
        fn ($label, $key) => [$key => ['label' => $label, 'shades' => \App\Models\LabCase::shadesForGuide($key)]]
    ));

    // Spread into any Alpine x-data that embeds partials.shade-select.
    function shadeSelectMixin() {
        return {
            shadesForGuide(guide) {
                return (window.SHADE_GUIDES[guide] || window.SHADE_GUIDES.vita_classical).shades;
            },
            guideLabel(guide) {
                return (window.SHADE_GUIDES[guide] || {}).label || '';
            },
            // Lazily creates and returns { shade_guide, shade } for one tooth,
            // defaulting to the case-level guide/shade the first time it's touched.
            toothShade(target, tooth) {
                if (!target.tooth_shades) target.tooth_shades = {};
                if (!target.tooth_shades[tooth]) {
                    target.tooth_shades[tooth] = {
                        shade_guide: target.shade_guide || 'vita_classical',
                        shade: target.shade || '',
                    };
                }
                return target.tooth_shades[tooth];
            },
        };
    }

    // Rebuilds a tooth-chart + shade-select target object from an existing
    // lab case's items[] (LabCaseItem rows) — used when opening the edit
    // form for a case that already has teeth/shades on file.
    function itemStateFromCaseItems(items) {
        items = items || [];
        const teeth = items.map(i => i.tooth_number).filter(Boolean);
        const shades = new Set(items.map(i => i.shade || ''));
        const guides = new Set(items.map(i => i.shade_guide || 'vita_classical'));
        const uniform = shades.size <= 1 && guides.size <= 1;
        const toothShades = {};
        items.forEach(i => {
            if (i.tooth_number) {
                toothShades[i.tooth_number] = {
                    shade: i.shade || '',
                    shade_guide: i.shade_guide || 'vita_classical',
                };
            }
        });
        return {
            teeth,
            tooth_number: teeth.join(', '),
            shade: uniform ? (items[0]?.shade || '') : '',
            shade_guide: uniform ? (items[0]?.shade_guide || 'vita_classical') : 'vita_classical',
            per_tooth_shade: teeth.length > 1 && !uniform,
            tooth_shades: toothShades,
        };
    }

    // Flattens a tooth-chart + shade-select target object into the items[]
    // payload the backend expects (one row per selected tooth). workType/
    // material default from the case's own work category/subtype — this
    // form doesn't expose a separate per-tooth work-type picker (MVP).
    function itemsPayloadFromState(item, workType, material) {
        const teeth = (item.teeth && item.teeth.length) ? item.teeth : [null];
        return teeth.map(t => {
            const shadeState = (item.per_tooth_shade && t && item.tooth_shades?.[t])
                ? item.tooth_shades[t]
                : { shade: item.shade, shade_guide: item.shade_guide };
            return {
                tooth_number: t,
                work_type: workType || null,
                material: material || null,
                shade: shadeState.shade || null,
                shade_guide: shadeState.shade ? (shadeState.shade_guide || 'vita_classical') : null,
            };
        }).filter(row => row.tooth_number || row.shade || row.work_type);
    }
</script>
@endonce
