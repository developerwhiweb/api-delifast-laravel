<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefund extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'order_payment_id',
        'amount',
        'status',
        'reason',
        'idempotency_key',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }
}
