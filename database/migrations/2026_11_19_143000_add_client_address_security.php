<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('address_storage_enabled')->default(false);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable()->after('customer_phone');
            $table->string('delivery_method', 20)->nullable()->after('delivery_address');
        });

        Schema::create('client_verification_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('email');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'purpose', 'created_at']);
        });

        Schema::create('client_sensitive_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_sensitive_access_grants');
        Schema::dropIfExists('client_verification_challenges');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_email', 'delivery_method']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('address_storage_enabled');
        });
    }
};
