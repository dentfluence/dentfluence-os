# Feature Flags

A small, dependency-free flag system. **Every flag is declared in `config/features.php`** — there are no hard-coded flags anywhere else.

## Resolution order

1. **Per-clinic override** — a `feature_flags` row for `(key, branch_id)`.
2. **Global override** — a `feature_flags` row for `(key, branch_id = null)`.
3. **Config default** — `config('features.flags.KEY.default')` (may read an env var).
4. **false**.

Per-clinic wins over global; global wins over the config default.

## Usage

```php
use App\Support\Features\Feature;

Feature::enabled('guard.fail_closed');              // global scope
Feature::for($branch->id)->enabled('today.projection'); // per-clinic
```

## Flipping a flag

```php
Feature::set('guard.fail_closed', true);                 // global on
Feature::set('today.projection', true, branchId: 7);     // clinic 7 on
Feature::set('today.projection', null, branchId: 7);     // remove override → back to default
```

Overrides are cached for `config('features.cache_ttl')` seconds; `set()` busts the cache automatically.

## Rules

- **Default = legacy behaviour.** Every flag ships OFF in Phase 0.
- **Add a flag by declaring it** in `config/features.php` (key + default + description). An undeclared flag resolves to `false` and logs a warning.
- Flags are **per-environment** (env-driven defaults), **per-clinic** (`branch_id`), and **per-feature** (key).

## The Phase 0 flags

See `config/features.php`. All default OFF. The only two that gate Phase 0 code paths are `guard.fail_closed` and `guard.consent_required`; the rest are declared ahead of their phases so later work only flips a switch.
