{{--
    partials/tooth-chart-assets.blade.php
    Shared CSS + Alpine mixin for the FDI multi-select tooth chart popup
    (partials.tooth-chart). Include this ONCE per page, outside any
    x-for/loop template, before any component that uses the tooth chart
    initializes. Used by Treatment Plan and the Lab Case tooth picker so
    both stay visually and behaviorally in sync.

    Any Alpine component embedding partials.tooth-chart must spread
    toothChartMixin() into its x-data, e.g.:
        function myForm() {
            return { ...toothChartMixin(), /* component-specific state */ };
        }
--}}
@once
<style>
    .tp-tooth-btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tp-tooth-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; padding-top: 6px; border-top: 1px solid #f3e8ff; }
    .tp-tooth-count { font-size: 11px; color: #6a0f70; font-weight: 700; }
    .tp-tooth-foot button { font-size: 11px; font-weight: 700; font-family: 'Inter', sans-serif; border-radius: 5px; padding: 4px 12px; cursor: pointer; border: none; }
    .tp-tooth-done { background: #6a0f70; color: #fff; }
    .tp-tooth-clear { background: #fff; color: #6b7280; border: 1px solid #e5e7eb !important; }
    .tp-tooth-badge { display: inline-block; font-size: 10px; font-weight: 700; color: #6a0f70; background: #f3e8ff; border-radius: 4px; padding: 1px 5px; margin-right: 4px; }
    .tp-tooth-btn {
        width: 100%; height: 34px; border: 1px solid #e5e7eb; border-radius: 6px;
        background: #fff; cursor: pointer; font-size: 11px; font-weight: 700;
        color: #6a0f70; font-family: 'Inter', sans-serif;
        display: flex; align-items: center; justify-content: center; gap: 4px;
        transition: border-color .15s, background .15s;
    }
    .tp-tooth-btn:hover { border-color: #6a0f70; background: #fdf4ff; }
    .tp-tooth-popup {
        position: absolute; top: calc(100% + 4px); left: 0; z-index: 9999;
        background: #fff; border: 1.5px solid #e9d5ff; border-radius: 10px;
        box-shadow: 0 8px 24px rgba(106,15,112,.13);
        padding: 10px 12px; width: 340px;
    }
    .tp-tooth-grid {
        display: grid; grid-template-columns: repeat(16, 1fr); gap: 2px; margin-bottom: 4px;
    }
    .tp-tooth-grid-lower { margin-bottom: 0; margin-top: 4px; }
    .tp-tooth-cell {
        position: relative;
        font-size: 9px; font-weight: 700; font-family: 'Inter', sans-serif;
        border: 1px solid #e5e7eb; border-radius: 3px; padding: 3px 0;
        text-align: center; cursor: pointer; color: #374151; background: #fff;
        transition: background .1s, color .1s;
    }
    .tp-tooth-cell:hover { background: #f3e8ff; color: #6a0f70; border-color: #6a0f70; }
    .tp-tooth-cell.selected { background: #6a0f70; color: #fff; border-color: #6a0f70; }
    .tp-tooth-midline { grid-column: span 16; height: 1px; background: #e9d5ff; margin: 2px 0; }
    .tp-tooth-dchip {
        position: absolute; top: -4px; right: -3px; width: 10px; height: 10px;
        border-radius: 2px; border: 1px solid #e5e7eb; background: #fff;
        font-size: 6px; font-weight: 800; line-height: 1; color: #9ca3af;
        display: flex; align-items: center; justify-content: center; cursor: pointer;
    }
    .tp-tooth-dchip.is-child { background: #fce7f3; border-color: #db2777; color: #db2777; }
    .tp-tooth-labels { display: flex; justify-content: space-between; font-size: 8px; color: #9ca3af; margin-bottom: 3px; font-family: 'Inter', sans-serif; }
</style>
<script>
    // Spread into any Alpine x-data that embeds partials.tooth-chart.
    // Depends on window.DentalNotation (partials.dental-notation, loaded globally).
    function toothChartMixin() {
        return {
            activeToothPicker: null,
            dentitionMode: {},

            codeAt(pos) {
                return window.DentalNotation.displayCode(pos, this.dentitionMode[pos] || 'permanent');
            },
            isChildPos(pos) {
                return this.dentitionMode[pos] === 'primary';
            },
            canToggleDentition(pos) {
                return window.DentalNotation.hasPrimary(pos);
            },
            toggleDentitionMode(pos) {
                this.dentitionMode = { ...this.dentitionMode, [pos]: this.isChildPos(pos) ? 'permanent' : 'primary' };
            },

            isToothSelected(item, t) {
                return Array.isArray(item.teeth) && item.teeth.includes(String(t));
            },
            toggleTooth(item, t) {
                t = String(t);
                if (!Array.isArray(item.teeth)) item.teeth = [];
                const i = item.teeth.indexOf(t);
                if (i > -1) item.teeth.splice(i, 1);
                else item.teeth.push(t);
                this.syncTeeth(item);
            },
            clearTeeth(item) {
                item.teeth = [];
                this.syncTeeth(item);
            },
            // Keep tooth_number string (+ units, if present) in sync with the
            // selected teeth. Numeric teeth are sorted; region labels
            // (UL, Full Arch…) are kept as-is.
            syncTeeth(item) {
                const nums = item.teeth.filter(t => /^\d+$/.test(t)).sort((a, b) => parseInt(a) - parseInt(b));
                const labels = item.teeth.filter(t => !/^\d+$/.test(t));
                item.teeth = [...nums, ...labels];
                item.tooth_number = item.teeth.join(', ');
                if ('units' in item) {
                    item.units = Math.max(item.teeth.length, 1);
                }
            },
        };
    }
</script>
@endonce
