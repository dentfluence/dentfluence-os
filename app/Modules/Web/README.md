# Web Module — structure contract

The Website Client Portal and the Website Command Centre. Two products, one module, one database.

**Read `docs/04_Modules/Web/WEB_PLATFORM_SSOT.md` before touching anything here.** It is the single source of truth for vision, architecture, the nine `web_` tables, and the locked decisions. This file only says *where code goes*.

Namespace root: `App\Modules\Web`
Structural precedent: `app/Modules/Hq` — controllers sit directly under `Controllers/`, **not** `Http/Controllers/`.

---

## Where things go

| Folder | Holds | Rule |
|---|---|---|
| `Controllers/CommandCentre/` | staff screens, `web` guard, `/web` | may read and write across sites |
| `Controllers/Portal/` | dentist screens, `portal` guard, `/portal` | read-mostly; the only writes are requests and approvals |
| `Models/` | the nine Eloquent models | flat, no subfolders |
| `Enums/` | every enum column, as a PHP 8.3 backed enum | **no enum value is ever written as a bare string outside this folder** |
| `Services/` | build, render, deploy, verify, health | all business logic; controllers stay thin |
| `Jobs/` | queued work | deployments and health runs live here, never in a request |
| `Policies/` | authorisation | one per model that needs it |
| `Scopes/` | `SiteScope` | fails closed — see below |
| `Middleware/` | portal site resolution, client-user state | register aliases in `bootstrap/app.php` |
| `Routes/` | `web.php`, `portal.php` | registered in `bootstrap/app.php`, one line each |

Migrations go in the normal `database/migrations/`, flat — Laravel convention, not module-local.

Views are **not** in this folder (Hq convention — its views live in `resources/views/hq`):

| Path | Holds |
|---|---|
| `resources/views/web/` | Command Centre UI |
| `resources/views/portal/` | Client Portal UI |
| `resources/views/client-sites/` | templates rendered into static HTML for client websites — **not** our app UI, never share a layout with the two above |

Tests: `tests/Feature/Web/`, `tests/Unit/Web/`.

---

## The four rules that keep this clean

**1. The guard boundary is visible in the filesystem.**
A file under `Controllers/Portal/` runs as a dentist. If it references `User`, `Role`, a clinical model, or an unscoped query, that is a bug you can see in the diff without reading the logic. Keep it that way — never put a shared controller above the two folders.

**2. `SiteScope` fails closed.**
No resolved site means an empty result set and a logged error. Never an unscoped query. Do **not** copy `app/Models/Scopes/BranchScope.php` — it deliberately fails open in three cases and says so in its own docblock.

**3. Approvals are insert-only.**
`web_approvals` has `created_at` and no `updated_at`, no `SoftDeletes`, and no update or delete path reachable from any controller, job, or command — including for Super Admin. A correction is a new row. This is evidence, not a record.

**4. Check the reuse map before creating a model.**
Clinics, plans, subscriptions, invoices, blog posts, media assets, audit logs and roles already exist. The reuse map is in the SSOT. Duplicating one of them is the main technical risk in this project.

---

## Naming gotcha

The change-request model is **`SiteRequest`**, not `Request`. `App\Modules\Web\Models\Request` would collide with `Illuminate\Http\Request` in every controller that imports both. The table stays `web_requests`; set `$table` explicitly on the model.

---

## After adding any PHP file here

```
composer dump-autoload
```

Then `php artisan migrate` for new migrations, and `php artisan view:clear` after Blade changes.
