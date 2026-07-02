<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fine-grained billing action permission for a role.
 *
 * Extends the existing role -> module (view/edit/delete) system with per-action
 * rules that some billing operations need — e.g. who may apply a manual discount
 * and up to what limit, who may refund a wallet, edit a posted invoice, etc.
 */
class RoleBillingPermission extends Model
{
    protected $fillable = [
        'role_id',
        'action_key',
        'is_allowed',
        'limit_value',
        'limit_type',
    ];

    protected $casts = [
        'is_allowed'  => 'boolean',
        'limit_value' => 'decimal:2',
    ];

    // Canonical action keys
    const MANUAL_DISCOUNT    = 'manual_discount';
    const WALLET_ADJUSTMENT  = 'wallet_adjustment';
    const WALLET_REFUND      = 'wallet_refund';
    const INVOICE_EDIT       = 'invoice_edit';
    const ADVANCE_ADJUSTMENT = 'advance_adjustment';

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
