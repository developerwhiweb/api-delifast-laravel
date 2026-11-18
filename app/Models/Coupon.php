<?php

namespace App\Models;

use App\Models\CouponUsage;
use App\Models\Product;
use App\Services\ActivityLogService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Coupon extends Model
{
    use SoftDeletes, Notifiable, HasFactory, LogsActivity, BelongsToTenant;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'applies_to',
        'fixed_scope',
        'min_order_value',
        'max_discount',
        'usage_limit',
        'usage_per_customer',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'coupon_products',
            'coupon_id',
            'product_id'
        );
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'coupon_categories',
            'coupon_id',
            'category_id'
        );
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function totalUsageCount(): int
    {
        $orderCount = Order::query()
            ->whereRaw('UPPER(coupon_code) = ?', [strtoupper($this->code)])
            ->count();

        return max(
            (int) $this->usage_count,
            $this->usages()->count(),
            $orderCount
        );
    }

    public function usageCountForClient(int|string $clientId): int
    {
        $orderCount = Order::query()
            ->whereRaw('UPPER(coupon_code) = ?', [strtoupper($this->code)])
            ->where('client_id', $clientId)
            ->count();

        return max(
            $this->usages()->where('client_id', $clientId)->count(),
            $orderCount
        );
    }

    public function isValid(): bool
    {
        $now = now();

        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        if (
            $this->usage_limit !== null &&
            $this->totalUsageCount() >= $this->usage_limit
        ) {
            return false;
        }

        return true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        $activityLogService = new ActivityLogService($this);
        
        return LogOptions::defaults()
            ->logOnly($activityLogService->getLoggableAttributes());
    }
}