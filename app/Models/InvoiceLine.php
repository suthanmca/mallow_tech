<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_segment_id',
        'usage_units',
        'included_units',
        'overage_units',
        'base_amount_cents',
        'overage_amount_cents',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionSegment::class, 'subscription_segment_id');
    }
}
