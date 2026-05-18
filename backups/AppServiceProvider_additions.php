<?php

/**
 * SESSION 3 — AppServiceProvider additions
 *
 * Add these bindings to the register() method of
 * app/Providers/AppServiceProvider.php
 *
 * DO NOT paste this whole file — only the $this->app->bind() lines
 * go inside your existing register() method.
 */

// ── Paste inside register() ──────────────────────────────────────────────────

$this->app->bind(
    \App\Modules\Huddle\Repositories\HuddleBoardRepository::class,
    \App\Modules\Huddle\Repositories\HuddleBoardRepository::class,
);

$this->app->bind(
    \App\Modules\Huddle\Repositories\HuddleCardRepository::class,
    \App\Modules\Huddle\Repositories\HuddleCardRepository::class,
);

$this->app->bind(
    \App\Modules\Huddle\Transformers\AppointmentToCardTransformer::class,
    \App\Modules\Huddle\Transformers\AppointmentToCardTransformer::class,
);

$this->app->bind(
    \App\Modules\Huddle\Transformers\TaskToCardTransformer::class,
    \App\Modules\Huddle\Transformers\TaskToCardTransformer::class,
);

$this->app->bind(
    \App\Modules\Huddle\Services\HuddleAggregationService::class,
    \App\Modules\Huddle\Services\HuddleAggregationService::class,
);

// ── End paste ────────────────────────────────────────────────────────────────
