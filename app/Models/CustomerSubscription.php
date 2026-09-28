<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'billing_cycle',
        'started_at',
        'current_period_start',
        'next_billing_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'current_period_start' => 'datetime',
            'next_billing_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(SubscriptionSegment::class);
    }
}
