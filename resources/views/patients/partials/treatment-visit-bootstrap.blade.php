{{--
    DEAD FILE — DO NOT USE (flagged in Treatment Visit V1 closure sprint, 08-05).

    This is an orphaned duplicate of _treatment-visit-bootstrap.php (note the
    leading underscore on the real file). Confirmed via repo-wide grep: zero
    @include()/include() references to this file anywhere in resources/,
    app/, or routes/. The live bootstrap — included by both
    treatment-visits-tab.blade.php and patients/treatment-visit-form.blade.php
    via a native PHP `include` (not Blade @include, deliberately — see that
    file's own header comment for why) — is:

        resources/views/patients/partials/_treatment-visit-bootstrap.php

    This stub is left in place (not deleted) because deleting files without
    explicit sign-off is against the project's standing rule. Safe to delete
    manually — it has no callers.
--}}
