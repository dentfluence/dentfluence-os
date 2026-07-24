<?php

/*
 * RETIRED 2026-07-18 (Product Audit — critical fix #2).
 * ---------------------------------------------------------------------------
 * This file previously declared `namespace App\Http\Controllers;` and
 * `class TreatmentVisitController`, a DEAD DUPLICATE of the canonical
 * controller at app/Http/Controllers/TreatmentVisitController.php. Two files
 * declaring the same fully-qualified class name is a namespace-collision
 * landmine: it is masked by the current compiled classmap but breaks on the
 * next `composer dump-autoload` (duplicate class in classmap).
 *
 * The class body has been removed so no duplicate FQCN exists. This file is
 * unreferenced by any route or code (verified) and can be safely git-deleted
 * from Windows (the build sandbox could not remove it). The canonical
 * controller and the shared TreatmentVisitService are unaffected.
 */
