<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('idempotency_key');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'status', 'paid_at']);
            $table->index(['tenant_id', 'order_id', 'status']);
        });

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_payment_id')->constrained('order_payments')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->uuid('idempotency_key');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'status', 'processed_at']);
            $table->index(['tenant_id', 'order_payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('order_payments');
    }
};
