<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();
                
            $table->string('code', 50)->unique();
            $table->string('description')->nullable();

            // PERCENTAGE ou FIXED
            $table->string('discount_type', 20);

            // Valor percentual ou valor monetário
            $table->decimal('discount_value', 10, 2);

            // ALL, SPECIFIC_PRODUCTS ou SPECIFIC_CATEGORIES
            $table->string('applies_to', 30)->default('ALL');

            // ORDER ou UNIT (para desconto fixo)
            $table->string('fixed_scope', 20)->default('ORDER');

            $table->decimal('min_order_value', 10, 2)->default(0);
            $table->decimal('max_discount', 10, 2)->nullable();

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_per_customer')->nullable();
            $table->unsignedInteger('usage_count')->default(0);

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};