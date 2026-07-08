{{--
    partials/dental-notation.blade.php
    Global — included once from layouts.app.

    Exposes window.DentalNotation: the permanent<->primary FDI tooth map
    (config/dental_notation.php) plus small helpers every tooth-chart screen
    uses to implement the adult/child per-position toggle.
--}}
@once
<script>
    window.DentalNotation = (function (cfg) {
        const P2D = cfg.permanent_to_primary; // permanent -> primary (object, string keys)
        const D2P = cfg.primary_to_permanent; // primary -> permanent
        const MOLAR_ONLY = cfg.molar_only;
        const PERMANENT_ARCHES = cfg.permanent_arches;
        const PRIMARY_ARCHES = cfg.primary_arches;

        function hasPrimary(code) { return Object.prototype.hasOwnProperty.call(P2D, code); }
        function isPrimary(code)  { return Object.prototype.hasOwnProperty.call(D2P, code); }
        function isMolarOnly(code) { return MOLAR_ONLY.includes(Number(code)); }

        // Given ANY code (permanent or primary), return the permanent code that
        // identifies this chart *position* — used as the stable key for toggle state.
        function positionKey(code) {
            code = Number(code);
            return isPrimary(code) ? D2P[code] : code;
        }

        // Given the permanent position code + a mode ('permanent'|'primary'),
        // return the code that should actually be displayed / stored.
        function displayCode(permanentCode, mode) {
            if (mode === 'primary' && hasPrimary(permanentCode)) return P2D[permanentCode];
            return permanentCode;
        }

        return {
            P2D, D2P, MOLAR_ONLY, PERMANENT_ARCHES, PRIMARY_ARCHES,
            hasPrimary, isPrimary, isMolarOnly, positionKey, displayCode,
        };
    })(@json(config('dental_notation')));
</script>
@endonce
