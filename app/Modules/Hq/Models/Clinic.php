<?php

namespace App\Modules\Hq\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    protected $fillable = [
        'name', 'city', 'contact_name', 'contact_phone', 'contact_email',
        'status', 'onboarded_at', 'notes',
    ];

    protected $casts = [
        'onboarded_at' => 'date',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->whereDate('expires_at', '>=', now());
    }

    /**
     * The clinic's "pass": every store/module code its live subscriptions unlock.
     * Derived, never stored — a pass cannot disagree with the subscription that pays for it.
     */
    public function passes(): \Illuminate\Support\Collection
    {
        return $this->activeSubscriptions()->with('plan')->get()
            ->flatMap(fn ($s) => $s->plan->unlocks ?? [])
            ->unique()->values();
    }

    /** True if the clinic may enter this store/module. '*' (pro pass) opens every door. */
    public function hasPass(string $code): bool
    {
        $passes = $this->passes();

        return $passes->contains('*') || $passes->contains($code);
    }
}
