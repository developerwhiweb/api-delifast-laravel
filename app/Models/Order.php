<?php

namespace App\Models;

use App\Services\ActivityLogService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, Notifiable;

    protected $fillable = [
        'number',
        'client_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
        'delivery_method',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'delivery_fee',
        'discount',
        'coupon_code',
        'coupon_discount',
        'total',
        'notes',
        'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_address' => 'array',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        $activityLogService = new ActivityLogService($this);

        return LogOptions::defaults()
            ->logOnly($activityLogService->getLoggableAttributes());
    }
}
