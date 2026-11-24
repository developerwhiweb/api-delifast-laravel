<?php

namespace Tests\Feature;

use App\Mail\ClientVerificationCodeMail;
use App\Models\AdSlot;
use App\Models\Announcement;
use App\Models\AuditActivity;
use App\Models\Client;
use App\Models\Slide;
use App\Models\TemplateTheme;
use App\Models\Tenant;
use App\Repositories\AuditCountRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->unique();
            $table->unsignedBigInteger('template_theme_id')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('text_color')->nullable();
            $table->string('bg_header')->nullable();
            $table->string('path_image_logo_header')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->json('opening_hours')->nullable();
            $table->timestamps();
        });

        Schema::create('template_themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->string('layout_type')->nullable();
            $table->string('template_variation')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('phone', 15);
            $table->string('email')->unique();
            $table->boolean('active')->default(false);
            $table->string('password');
            $table->string('path_image')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
            $table->json('properties')->nullable();
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('path_image')->nullable();
            $table->boolean('active')->default(false);
            $table->integer('sorting')->default(0);
            $table->timestamps();
        });

        Schema::create('ad_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_theme_id');
            $table->string('name');
            $table->string('slug');
            $table->string('exhibition');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sorting')->default(0);
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->text('link')->nullable();
            $table->string('path_image')->nullable();
            $table->string('path_image_mobile')->nullable();
            $table->string('path_image_vertical')->nullable();
            $table->boolean('active')->default(false);
            $table->integer('sorting')->default(0);
            $table->string('target')->default('all');
            $table->string('display_location')->default('web');
            $table->string('type')->default('general');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->text('text')->nullable();
            $table->timestamps();
        });

        Schema::create('announcement_ad_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('ad_slot_id');
            $table->timestamps();
        });

        Schema::create('announcement_tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('tenant_id');
        });

        $migration = require database_path('migrations/2026_11_12_114000_allow_delivery_client_self_registration.php');
        $migration->up();

        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('address_storage_enabled')->default(false);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Casa');
            $table->string('recipient_name');
            $table->string('zip_code', 9);
            $table->string('state', 2);
            $table->string('city');
            $table->string('neighborhood');
            $table->string('street');
            $table->string('number');
            $table->string('complement')->nullable();
            $table->text('reference')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
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

        Schema::create('setting_emails', function (Blueprint $table) {
            $table->id();
            $table->string('mail_mailer')->nullable();
            $table->string('mail_host')->nullable();
            $table->string('mail_port')->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_encryption')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        activity()->disableLogging();
    }

    public function test_delivery_client_can_login_update_and_delete_their_account(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('client_id');
            $table->timestamps();
        });

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'path_image' => 'profiles/ana.jpg',
            'active' => true,
        ]);
        $client->email_verified_at = now();
        $client->save();
        $sensitiveAccessToken = $this->createSensitiveAccessGrant($client);
        DB::table('customer_addresses')->insert([
            'client_id' => $client->id,
            'title' => 'Casa',
            'recipient_name' => 'Ana Cliente',
            'zip_code' => '01000-000',
            'state' => 'SP',
            'city' => 'São Paulo',
            'neighborhood' => 'Centro',
            'street' => 'Rua A',
            'number' => '10',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $login = $this->postJson('/api/client/login', [
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
        ])->assertOk()
            ->assertJsonPath('data.client.name', 'Ana Cliente')
            ->assertJsonPath('data.client.path_image', 'profiles/ana.jpg')
            ->assertJsonPath('data.client.has_saved_address', true);

        $login->assertJsonMissingPath('data.client.password');
        $token = $login->json('data.token');

        $otherClient = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Outro Cliente',
            'phone' => '11777777777',
            'email' => 'outro@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => true,
        ]);
        $otherClient->email_verified_at = now();
        $otherClient->save();

        $this->withToken($otherClient->createToken('delivery')->plainTextToken)
            ->withHeader('X-Client-Verification', $this->createSensitiveAccessGrant($otherClient))
            ->patchJson('/api/client/me', ['phone' => '11999999999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
        Auth::forgetGuards();

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->patchJson('/api/client/me', ['email' => 'ana.nova@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->patchJson('/api/client/me', [
                'name' => 'Ana Silva',
                'phone' => '11988887777',
                'password' => 'new-password-123',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana Silva')
            ->assertJsonPath('data.phone', '11988887777')
            ->assertJsonPath('data.email', 'ana@example.com');

        $this->assertDatabaseHas('clients', ['email' => 'ana@example.com']);
        $this->assertTrue(Hash::check('new-password-123', $client->fresh()->password));

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->deleteJson('/api/client/me')
            ->assertOk()
            ->assertJsonPath('message', 'Conta excluída com sucesso.');

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $client->id,
        ]);
    }

    public function test_client_api_updates_are_audited_with_the_authenticated_client_as_causer(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => true,
        ]);
        $client->email_verified_at = now();
        $client->save();
        $sensitiveAccessToken = $this->createSensitiveAccessGrant($client);

        activity()->enableLogging();

        try {
            $this->withToken($client->createToken('delivery')->plainTextToken)
                ->withHeader('X-Client-Verification', $sensitiveAccessToken)
                ->patchJson('/api/client/me', ['name' => 'Ana Silva'])
                ->assertOk();

            $activity = AuditActivity::query()
                ->where('subject_type', Client::class)
                ->where('subject_id', $client->id)
                ->firstOrFail();

            $this->assertSame(Client::class, $activity->causer_type);
            $this->assertSame($client->id, $activity->causer_id);
            $this->assertSame('Ana Silva', $activity->causer->name);
            $this->assertSame(1, (new AuditCountRepository)->auditCount());
            $this->assertCount(1, (new AuditCountRepository)->allAudit());
        } finally {
            activity()->disableLogging();
        }
    }

    public function test_delivery_api_rejects_invalid_login_and_unauthenticated_profile_requests(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Conta inativa',
            'phone' => '11911111111',
            'email' => 'inactive@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => false,
        ]);

        $this->postJson('/api/client/login', ['name' => 'Ana Cliente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        $this->postJson('/api/client/login', [
            'name' => 'Conta inativa',
            'phone' => '11911111111',
        ])->assertForbidden();

        $this->get('/api/client/me')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJson(['message' => 'Não autenticado.']);
    }

    public function test_authenticated_client_can_manage_one_private_address(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => true,
        ]);
        $client->email_verified_at = now();
        $client->address_storage_enabled = true;
        $client->save();
        $sensitiveAccessToken = $this->createSensitiveAccessGrant($client);
        $otherClient = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Outro Cliente',
            'phone' => '11777777777',
            'email' => 'outro@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => true,
        ]);

        $token = $client->createToken('delivery')->plainTextToken;
        $payload = [
            'recipient_name' => 'Ana Cliente',
            'zip_code' => '12345-678',
            'state' => 'sp',
            'city' => 'São Paulo',
            'neighborhood' => 'Centro',
            'street' => 'Rua A',
            'number' => '10',
            'is_default' => false,
        ];

        $firstAddress = $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->postJson('/api/client/addresses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Casa')
            ->assertJsonPath('data.state', 'SP')
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->postJson('/api/client/addresses', array_merge($payload, [
                'title' => 'Trabalho',
                'is_default' => true,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O cliente pode cadastrar no máximo 1 endereço.');

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $firstAddress,
            'is_default' => true,
        ]);
        $this->assertDatabaseCount('customer_addresses', 1);

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->getJson('/api/client/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->postJson('/api/client/addresses', $payload)
            ->assertUnprocessable();
        $this->assertDatabaseCount('customer_addresses', 1);

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->patchJson("/api/client/addresses/{$firstAddress}", [
                'street' => 'Rua Nova',
            ])
            ->assertOk()
            ->assertJsonPath('data.street', 'Rua Nova')
            ->assertJsonPath('data.is_default', true);

        $foreignAddress = $otherClient->addresses()->create([
            'recipient_name' => 'Outro Cliente',
            'zip_code' => '87654-321',
            'state' => 'RJ',
            'city' => 'Rio de Janeiro',
            'neighborhood' => 'Centro',
            'street' => 'Rua B',
            'number' => '20',
        ]);

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->getJson("/api/client/addresses/{$foreignAddress->id}")
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->deleteJson("/api/client/addresses/{$firstAddress}")
            ->assertOk();
        $this->assertDatabaseMissing('customer_addresses', ['id' => $firstAddress]);

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveAccessToken)
            ->postJson('/api/client/addresses', array_merge($payload, [
                'title' => 'Trabalho',
            ]))
            ->assertCreated();
        $this->assertSame(1, $client->addresses()->count());
    }

    public function test_customer_address_routes_require_client_authentication(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('client_id');
            $table->timestamps();
        });

        $this->getJson('/api/client/addresses')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Não autenticado.']);

        $this->postJson('/api/client/addresses', [])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Não autenticado.']);

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Conta protegida',
            'phone' => '11922223333',
            'email' => 'protegida@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => true,
        ]);

        $token = $client->createToken('delivery')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/client/addresses')
            ->assertForbidden()
            ->assertJsonPath('code', 'client_email_verification_required');

        $this->withToken($token)
            ->putJson('/api/client/address-storage', ['address_storage_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.address_storage_enabled', false);
        $this->withToken($token)
            ->putJson('/api/client/address-storage', ['address_storage_enabled' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address_storage_enabled');
    }

    public function test_client_must_confirm_email_and_a_one_time_code_before_viewing_private_data(): void
    {
        Mail::fake();

        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('client_id');
            $table->timestamps();
        });

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => null,
            'password' => null,
            'active' => true,
        ]);
        $token = $client->createToken('delivery')->plainTextToken;
        RateLimiter::clear(sha1((string) $client->id));

        $this->withToken($token)
            ->getJson('/api/client/addresses')
            ->assertForbidden()
            ->assertJsonPath('code', 'client_email_verification_required');
        $this->withToken($token)
            ->getJson('/api/client/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'client_email_verification_required');
        $this->withToken($token)
            ->getJson('/api/client/orders')
            ->assertForbidden()
            ->assertJsonPath('code', 'client_email_verification_required');

        $this->withToken($token)
            ->postJson('/api/client/email-verification/request', [
                'email' => 'ana@example.com',
            ])
            ->assertOk();

        $emailCode = null;
        Mail::assertSent(ClientVerificationCodeMail::class, function ($mail) use (&$emailCode) {
            $emailCode = $mail->code;

            return true;
        });
        $storedCodeHash = DB::table('client_verification_challenges')
            ->where('client_id', $client->id)
            ->value('code_hash');
        $this->assertNotSame($emailCode, $storedCodeHash);
        $this->assertSame(
            hash_hmac('sha256', $emailCode, (string) config('app.key')),
            $storedCodeHash
        );

        $this->withToken($token)
            ->postJson('/api/client/email-verification/confirm', [
                'code' => $emailCode === '000000' ? '000001' : '000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertNull($client->fresh()->email);
        DB::table('client_verification_challenges')
            ->where('client_id', $client->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->withToken($token)
            ->postJson('/api/client/email-verification/confirm', [
                'code' => $emailCode,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertNull($client->fresh()->email);
        $this->withToken($token)
            ->getJson('/api/client/addresses')
            ->assertForbidden()
            ->assertJsonPath('code', 'client_email_verification_required');

        $this->withToken($token)
            ->postJson('/api/client/email-verification/request', [
                'email' => 'ana@example.com',
            ])
            ->assertOk();

        $codes = [];
        Mail::assertSent(ClientVerificationCodeMail::class, function ($mail) use (&$codes) {
            $codes[] = $mail->code;

            return true;
        });
        $emailCode = end($codes);

        $emailConfirmation = $this->withToken($token)
            ->postJson('/api/client/email-verification/confirm', [
                'code' => $emailCode,
            ])
            ->assertOk()
            ->assertJsonPath('data.has_email', true)
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.name')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.path_image')
            ->assertJsonStructure([
                'data' => [
                    'sensitive_access' => ['token', 'expires_at'],
                ],
            ]);

        Auth::forgetGuards();
        $sensitiveToken = $emailConfirmation->json('data.sensitive_access.token');
        $this->assertNotEmpty($sensitiveToken);
        $this->assertDatabaseCount('client_sensitive_access_grants', 1);

        $this->withToken($token)
            ->postJson('/api/client/email-verification/confirm', ['code' => $emailCode])
            ->assertUnprocessable();

        $this->withToken($token)
            ->getJson('/api/client/addresses')
            ->assertForbidden()
            ->assertJsonPath('code', 'client_verification_required');
        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveToken)
            ->getJson('/api/client/addresses')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withToken($token)
            ->withHeader('X-Client-Verification', $sensitiveToken)
            ->getJson('/api/client/me')
            ->assertOk();
    }

    public function test_verified_client_can_request_a_sensitive_access_code(): void
    {
        Mail::fake();

        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'password' => null,
            'active' => true,
        ]);
        $client->email_verified_at = now();
        $client->save();
        $token = $client->createToken('delivery')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/client/sensitive-access/request')
            ->assertOk();

        $code = null;
        Mail::assertSent(ClientVerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $grant = $this->withToken($token)
            ->postJson('/api/client/sensitive-access/confirm', ['code' => $code])
            ->assertOk()
            ->json('data.token');

        $this->withToken($token)
            ->withHeader('X-Client-Verification', $grant)
            ->getJson('/api/client/addresses')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_client_with_existing_order_cannot_bind_a_new_email_from_phone_login_alone(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('client_id');
            $table->timestamps();
        });

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => null,
            'password' => null,
            'active' => true,
        ]);
        DB::table('orders')->insert([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = $client->createToken('delivery')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/client/email-verification/request', [
                'email' => 'attacker@example.com',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('client_verification_challenges', 0);
        $this->assertNull($client->fresh()->email);
    }

    public function test_changing_a_verified_email_requires_sensitive_access_and_confirms_the_new_address(): void
    {
        Mail::fake();

        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'password' => null,
            'active' => true,
        ]);
        $client->email_verified_at = now();
        $client->save();
        $accessToken = $this->createSensitiveAccessGrant($client);
        $authToken = $client->createToken('delivery')->plainTextToken;

        $this->withToken($authToken)
            ->postJson('/api/client/email-verification/request', [
                'email' => 'ana.nova@example.com',
            ])
            ->assertForbidden();

        $this->withToken($authToken)
            ->withHeader('X-Client-Verification', $accessToken)
            ->postJson('/api/client/email-verification/request', [
                'email' => 'ana.nova@example.com',
            ])
            ->assertOk();

        $code = null;
        Mail::assertSent(ClientVerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $confirmation = $this->withToken($authToken)
            ->postJson('/api/client/email-verification/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.name')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.path_image')
            ->assertJsonStructure([
                'data' => [
                    'sensitive_access' => ['token', 'expires_at'],
                ],
            ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'email' => 'ana.nova@example.com',
        ]);
        $this->assertDatabaseCount('client_sensitive_access_grants', 1);
        $this->assertNotEmpty($confirmation->json('data.sensitive_access.token'));
    }

    public function test_authenticated_client_can_place_order_without_resending_profile_details(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->boolean('active');
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('path_image')->nullable();
            $table->string('product_type')->nullable();
            $table->decimal('price', 10, 2);
            $table->json('stock')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('client_id');
            $table->string('number');
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('customer_email')->nullable();
            $table->json('delivery_address')->nullable();
            $table->string('delivery_method', 20)->nullable();
            $table->string('status');
            $table->string('payment_status');
            $table->string('payment_method', 50)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('discount', 10, 2);
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('coupon_discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_title');
            $table->string('product_slug')->nullable();
            $table->string('product_image')->nullable();
            $table->string('product_type', 30)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->json('details')->nullable();
            $table->timestamps();
        });

        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();
        $this->createOpenContactHours($tenant->id);

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => null,
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => true,
        ]);

        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $tenant->id,
            'active' => true,
            'title' => 'Produto',
            'price' => 12.50,
            'stock' => json_encode(['control' => 1, 'quantity' => 3]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $client->createToken('delivery')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/client/orders', [
                'items' => [
                    [
                        'product_id' => $productId,
                        'quantity' => 2,
                        'unit_price' => 18.75,
                        'details' => [
                            'aditionals' => [
                                ['name' => 'Bacon extra', 'price' => 3.00, 'quantity' => 2],
                            ],
                        ],
                    ],
                    ['product_id' => $productId, 'quantity' => 1],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.customer_name', 'Ana Cliente')
            ->assertJsonPath('data.customer_phone', '11999999999')
            ->assertJsonPath('data.client_id', $client->id);

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'customer_name' => 'Ana Cliente',
            'customer_phone' => '11999999999',
            'subtotal' => 50,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $productId,
            'quantity' => 2,
            'unit_price' => 18.75,
            'total_price' => 37.5,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $productId,
            'quantity' => 1,
            'unit_price' => 12.5,
            'total_price' => 12.5,
        ]);

        $this->assertSame(
            ['control' => 1, 'quantity' => 0],
            json_decode(DB::table('products')->where('id', $productId)->value('stock'), true)
        );

        $this->withToken($token)
            ->postJson('/api/client/orders', [
                'items' => [
                    ['product_id' => $productId, 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.quantity');

        $this->assertDatabaseCount('orders', 1);

        $unlimitedProductId = DB::table('products')->insertGetId([
            'tenant_id' => $tenant->id,
            'active' => true,
            'title' => 'Produto sem controle de estoque',
            'price' => 5.00,
            'stock' => json_encode(['control' => 0, 'quantity' => 1]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($token)
            ->postJson('/api/client/orders', [
                'items' => [
                    ['product_id' => $unlimitedProductId, 'quantity' => 5],
                ],
            ])
            ->assertCreated();

        $deliveryAddress = [
            'recipient_name' => 'Ana Cliente',
            'zip_code' => '12345-678',
            'state' => 'SP',
            'city' => 'São Paulo',
            'neighborhood' => 'Centro',
            'street' => 'Rua sem salvar',
            'number' => '10',
        ];

        $notSavedOrderId = $this->withToken($token)
            ->postJson('/api/client/orders', [
                'delivery_method' => 'delivery',
                'save_address' => false,
                'customer_email' => 'pedido@example.com',
                'delivery_address' => $deliveryAddress,
                'items' => [['product_id' => $unlimitedProductId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('orders', [
            'id' => $notSavedOrderId,
            'customer_email' => null,
            'delivery_method' => 'delivery',
        ]);
        $this->assertDatabaseCount('customer_addresses', 0);
        $this->assertFalse((bool) $client->fresh()->address_storage_enabled);
        $this->assertNull($client->fresh()->email);
        $this->assertSame(
            'Rua sem salvar',
            json_decode(DB::table('orders')->where('id', $notSavedOrderId)->value('delivery_address'), true)['street']
        );

        $client->email = 'ana@example.com';
        $client->save();
        $unverifiedOrderId = $this->withToken($token)
            ->postJson('/api/client/orders', [
                'customer_email' => 'nao-associado@example.com',
                'items' => [['product_id' => $unlimitedProductId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');
        $this->assertDatabaseHas('orders', [
            'id' => $unverifiedOrderId,
            'client_id' => $client->id,
            'customer_email' => 'ana@example.com',
        ]);
        $this->assertNull($client->fresh()->email_verified_at);

        $client->email_verified_at = now();
        $client->save();
        $savedAddress = array_merge($deliveryAddress, ['street' => 'Rua Salva']);
        $savedOrderId = $this->withToken($token)
            ->postJson('/api/client/orders', [
                'delivery_method' => 'delivery',
                'save_address' => true,
                'delivery_address' => $savedAddress,
                'items' => [['product_id' => $unlimitedProductId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseCount('customer_addresses', 1);
        $this->assertTrue((bool) $client->fresh()->address_storage_enabled);
        DB::table('customer_addresses')->where('client_id', $client->id)
            ->update(['street' => 'Rua atualizada']);
        $this->assertSame(
            'Rua Salva',
            json_decode(DB::table('orders')->where('id', $savedOrderId)->value('delivery_address'), true)['street']
        );

        $this->withToken($token)
            ->postJson('/api/client/orders', [
                'delivery_method' => 'delivery',
                'save_address' => true,
                'delivery_address' => array_merge($savedAddress, ['street' => 'Outra Rua']),
                'items' => [['product_id' => $unlimitedProductId, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('save_address')
            ->assertJsonPath(
                'errors.save_address.0',
                'A conta pode manter no máximo 1 endereço salvo.'
            );
        $this->assertDatabaseCount('customer_addresses', 1);

        $this->assertSame(
            ['control' => 0, 'quantity' => 1],
            json_decode(DB::table('products')->where('id', $unlimitedProductId)->value('stock'), true)
        );

        DB::table('contacts')
            ->where('tenant_id', $tenant->id)
            ->update([
                'opening_hours' => json_encode($this->openingHoursThatHaveClosed()),
                'updated_at' => now(),
            ]);

        $this->withToken($token)
            ->postJson('/api/client/orders', [
                'items' => [
                    ['product_id' => $unlimitedProductId, 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('store_hours');

        $this->assertDatabaseCount('orders', 5);
        $this->assertSame(
            ['control' => 0, 'quantity' => 1],
            json_decode(DB::table('products')->where('id', $unlimitedProductId)->value('stock'), true)
        );
    }

    public function test_tenant_api_returns_public_branding_and_theme_configuration(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'primary_color' => '#123456',
            'path_image_logo_header' => 'uploads/logo.avif',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        $this->getJson('/api/tenant')
            ->assertOk()
            ->assertJsonPath('data.tenant.name', 'Delivery')
            ->assertJsonPath('data.tenant.primary_color', '#123456')
            ->assertJsonPath('data.tenant.path_image_logo_header', 'uploads/logo.avif')
            ->assertJsonPath('data.theme', null)
            ->assertJsonMissingPath('data.tenant.database')
            ->assertJsonMissingPath('data.tenant.plan_id');
    }

    public function test_appearance_api_returns_tenant_branding_with_a_short_public_cache(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'primary_color' => '#123456',
            'secondary_color' => null,
            'text_color' => '#222222',
            'bg_header' => '#fef3e5',
            'path_image_logo_header' => 'uploads/logo.avif',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        $this->getJson('/api/appearance')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertJsonPath('name', 'Delivery')
            ->assertJsonPath('logo', Storage::disk('public')->url('uploads/logo.avif'))
            ->assertJsonPath('colors.primary', '#123456')
            ->assertJsonPath('colors.secondary', '#24211f')
            ->assertJsonPath('colors.background', '#fef3e5')
            ->assertJsonPath('colors.text', '#222222');
    }

    public function test_slides_api_returns_only_the_image_of_the_first_active_slide(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        Slide::create([
            'path_image' => 'slides/inactive.avif',
            'active' => false,
            'sorting' => 0,
        ]);
        Slide::create([
            'path_image' => 'slides/second.avif',
            'active' => true,
            'sorting' => 2,
        ]);
        Slide::create([
            'path_image' => 'slides/first.avif',
            'active' => true,
            'sorting' => 1,
        ]);

        $this->getJson('/api/slides')
            ->assertOk()
            ->assertExactJson([
                'data' => ['path_image' => 'slides/first.avif'],
            ]);
    }

    public function test_service_locations_api_returns_active_locations_with_neighborhood_delivery_fees(): void
    {
        Schema::create('service_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('path_image')->nullable();
            $table->boolean('active')->default(false);
            $table->json('neighborhoods')->nullable();
            $table->timestamps();
        });

        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        DB::table('service_locations')->insert([
            [
                'tenant_id' => $tenant->id,
                'title' => 'Campinas',
                'description' => 'Área atendida',
                'path_image' => 'locations/campinas.avif',
                'active' => true,
                'neighborhoods' => json_encode([
                    ['name' => 'Centro', 'delivery_fee' => 5.5],
                    ['name' => 'Taquaral', 'delivery_fee' => 8.25],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'title' => 'Inativa',
                'description' => null,
                'path_image' => null,
                'active' => false,
                'neighborhoods' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/service-locations')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => 1,
                    'title' => 'Campinas',
                    'description' => 'Área atendida',
                    'path_image' => 'locations/campinas.avif',
                    'neighborhoods' => [
                        ['name' => 'Centro', 'delivery_fee' => 5.5],
                        ['name' => 'Taquaral', 'delivery_fee' => 8.25],
                    ],
                ]],
            ]);
    }

    public function test_announcements_api_returns_public_announcements_by_ad_slot(): void
    {
        $theme = TemplateTheme::create([
            'slug' => 'delivery',
            'name' => 'Delivery',
            'layout_type' => 'onepage',
            'template_variation' => 'tp-01',
        ]);
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'template_theme_id' => $theme->id,
            'active' => true,
        ]);
        $tenant->makeCurrent();

        $adSlot = AdSlot::create([
            'template_theme_id' => $theme->id,
            'name' => 'Banner delivery',
            'slug' => 'banner-delivery',
            'exhibition' => 'horizontal',
            'active' => true,
            'sorting' => 1,
        ]);

        $eligible = Announcement::create([
            'link' => 'https://example.com/oferta',
            'path_image' => 'announcements/oferta.webp',
            'active' => true,
            'target' => 'all',
            'display_location' => 'web',
            'type' => 'general',
        ]);
        $eligible->adSlots()->attach($adSlot->id);

        $panelOnly = Announcement::create([
            'path_image' => 'announcements/panel.webp',
            'active' => true,
            'target' => 'all',
            'display_location' => 'panel',
            'type' => 'general',
        ]);
        $panelOnly->adSlots()->attach($adSlot->id);

        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('data.banner-delivery.0.path_image', 'announcements/oferta.webp')
            ->assertJsonPath('data.banner-delivery.0.link', 'https://example.com/oferta')
            ->assertJsonMissing(['path_image' => 'announcements/panel.webp'])
            ->assertJsonMissingPath('data.banner-delivery.0.pivot');
    }

    public function test_login_creates_new_account_and_rejects_a_phone_with_a_different_name(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('client_id');
            $table->timestamps();
        });

        $this->postJson('/api/client/login', [
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('clients', 0);

        $newClientId = $this->postJson('/api/client/login', [
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => ' ANA@EXAMPLE.COM ',
        ])->assertCreated()
            ->assertJsonPath('message', 'Conta criada e login realizado com sucesso.')
            ->assertJsonPath('data.client.has_email', true)
            ->assertJsonPath('data.client.email_verified', false)
            ->json('data.client.id');

        $this->assertDatabaseHas('clients', [
            'id' => $newClientId,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/client/login', [
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
        ])->assertOk()
            ->assertJsonPath('message', 'Login realizado com sucesso.')
            ->assertJsonPath('data.client.id', $newClientId);

        $this->postJson('/api/client/login', [
            'name' => 'Outro Cliente',
            'phone' => '11777777777',
            'email' => 'ana@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/client/login', [
            'name' => 'Outro Nome',
            'phone' => '11999999999',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'O telefone informado já está associado a outro nome.');

        $legacyClient = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente antigo',
            'phone' => '11777777777',
            'email' => null,
            'password' => null,
            'active' => true,
        ]);
        DB::table('orders')->insert([
            'tenant_id' => $tenant->id,
            'client_id' => $legacyClient->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/client/login', [
            'name' => 'Cliente antigo',
            'phone' => '11777777777',
        ])->assertOk()
            ->assertJsonPath('data.client.id', $legacyClient->id)
            ->assertJsonPath('data.client.has_email', false);

        $this->assertDatabaseCount('clients', 2);
        $this->assertDatabaseHas('orders', [
            'client_id' => $legacyClient->id,
        ]);
        $this->assertDatabaseHas('clients', [
            'id' => $newClientId,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'password' => null,
            'active' => true,
        ]);
    }

    public function test_coupon_minimum_uses_selected_option_price_and_accepts_duplicate_product_lines(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->json('sizes')->nullable();
            $table->timestamps();
        });
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->string('title');
            $table->json('choices');
            $table->timestamps();
        });
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2);
            $table->string('applies_to');
            $table->string('fixed_scope')->default('ORDER');
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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
        });
        Schema::create('coupon_products', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('product_id');
        });
        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('category_id');
        });

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => true,
        ]);
        $token = $client->createToken('delivery')->plainTextToken;
        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Açaí no copo',
            'price' => 10,
        ]);
        $optionId = DB::table('options')->insertGetId([
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'title' => 'Tamanhos',
            'choices' => json_encode([
                ['id' => 'size-300', 'name' => '300ml', 'price' => '10.00'],
                ['id' => 'size-500', 'name' => '500ml', 'price' => '15.00'],
                ['id' => 'size-700', 'name' => '700ml', 'price' => '20.00'],
            ]),
        ]);
        DB::table('coupons')->insert([
            'tenant_id' => $tenant->id,
            'code' => 'SIZE15',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 10,
            'applies_to' => 'ALL',
            'min_order_value' => 15,
            'is_active' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/client/coupons/validate', [
                'code' => 'SIZE15',
                'items' => [[
                    'product_id' => $productId,
                    'quantity' => 1,
                    'selected_options' => [(string) $optionId => 'size-500'],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('subtotal', 15);

        $this->withToken($token)
            ->postJson('/api/client/coupons/validate', [
                'code' => 'SIZE15',
                'items' => [[
                    'product_id' => $productId,
                    'quantity' => 1,
                    'details' => [
                        'options' => [[
                            'optionName' => 'Tamanhos',
                            'choiceName' => '700ml',
                        ]],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('subtotal', 20);

        $this->withToken($token)
            ->postJson('/api/client/coupons/validate', [
                'code' => 'SIZE15',
                'items' => [[
                    'product_id' => $productId,
                    'quantity' => 1,
                    'details' => [
                        'options' => ['Tamanhos' => '700ml'],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('subtotal', 20);

        $this->withToken($token)
            ->postJson('/api/client/coupons/validate', [
                'code' => 'SIZE15',
                'items' => [
                    [
                        'product_id' => $productId,
                        'quantity' => 1,
                        'selected_options' => [(string) $optionId => 'size-300'],
                    ],
                    [
                        'product_id' => $productId,
                        'quantity' => 1,
                        'selected_options' => [(string) $optionId => 'size-500'],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('subtotal', 25);

        $this->withToken($token)
            ->postJson('/api/client/coupons/validate', [
                'code' => 'SIZE15',
                'items' => [[
                    'product_id' => $productId,
                    'quantity' => 1,
                    'selected_options' => [(string) $optionId => 'forged-choice'],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_order_persists_payment_method_and_coupon_discount_details(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'domain' => 'localhost',
            'active' => true,
        ]);
        $tenant->makeCurrent();
        $this->createOpenContactHours($tenant->id);

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->boolean('active')->default(true);
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('path_image')->nullable();
            $table->string('product_type')->nullable();
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->string('title');
            $table->json('choices');
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('number');
            $table->unsignedBigInteger('client_id');
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->json('delivery_address')->nullable();
            $table->string('delivery_method', 20)->nullable();
            $table->string('status');
            $table->string('payment_status');
            $table->string('payment_method', 50)->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('coupon_discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();
        });
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code', 50);
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2);
            $table->string('applies_to');
            $table->string('fixed_scope')->default('ORDER');
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
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->unique();
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_title')->nullable();
            $table->string('product_slug')->nullable();
            $table->string('product_image')->nullable();
            $table->string('product_type')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->json('details')->nullable();
            $table->timestamps();
        });

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Cliente',
            'phone' => '11999999999',
            'email' => 'ana@example.com',
            'password' => Hash::make('not-used-by-delivery-api'),
            'active' => true,
        ]);
        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $tenant->id,
            'active' => true,
            'title' => 'Açaí no copo',
            'price' => 15,
        ]);
        DB::table('coupons')->insert([
            'tenant_id' => $tenant->id,
            'code' => 'BEMVINDO',
            'discount_type' => 'FIXED',
            'discount_value' => 5,
            'applies_to' => 'ALL',
            'fixed_scope' => 'ORDER',
            'min_order_value' => 0,
            'usage_limit' => 1,
            'usage_per_customer' => 1,
            'usage_count' => 0,
            'is_active' => true,
        ]);
        $orderData = [
            'payment_method' => 'pix',
            'discount' => 99,
            'coupon_code' => 'BEMVINDO',
            'coupon_discount' => 99,
            'items' => [[
                'product_id' => $productId,
                'quantity' => 1,
                'unit_price' => 15,
            ]],
        ];

        $this->withToken($client->createToken('delivery')->plainTextToken)
            ->postJson('/api/client/orders', $orderData)
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'pix')
            ->assertJsonPath('data.coupon_code', 'BEMVINDO')
            ->assertJsonPath('data.coupon_discount', '5.00')
            ->assertJsonPath('data.discount', '5.00')
            ->assertJsonPath('data.total', '10.00');

        $this->assertDatabaseHas('orders', [
            'payment_method' => 'pix',
            'coupon_code' => 'BEMVINDO',
            'coupon_discount' => 5,
            'discount' => 5,
        ]);
        $this->assertDatabaseHas('coupons', [
            'code' => 'BEMVINDO',
            'usage_count' => 1,
        ]);
        $this->assertDatabaseCount('coupon_usages', 1);

        $this->withToken($client->createToken('delivery')->plainTextToken)
            ->postJson('/api/client/orders', $orderData)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');

        $this->withToken($client->createToken('delivery')->plainTextToken)
            ->postJson('/api/client/coupons/validate', [
                'code' => 'BEMVINDO',
                'items' => [[
                    'product_id' => $productId,
                    'quantity' => 1,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('valid', false);
    }

    private function createOpenContactHours(int $tenantId): void
    {
        $currentTime = now();
        $openTime = $currentTime->copy()->subHour()->format('H:i');
        $closeTime = $currentTime->copy()->addHour()->format('H:i');
        $openingHours = array_map(fn (int $dayOfWeek) => [
            'day_of_week' => $dayOfWeek,
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'is_closed' => false,
        ], range(1, 7));

        DB::table('contacts')->insert([
            'tenant_id' => $tenantId,
            'opening_hours' => json_encode($openingHours),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function openingHoursThatHaveClosed(): array
    {
        $currentTime = now();
        $openTime = $currentTime->copy()->subHours(2)->format('H:i');
        $closeTime = $currentTime->copy()->subHour()->format('H:i');

        return array_map(fn (int $dayOfWeek) => [
            'day_of_week' => $dayOfWeek,
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'is_closed' => false,
        ], range(1, 7));
    }

    private function createSensitiveAccessGrant(Client $client): string
    {
        $token = Str::random(64);

        $client->sensitiveAccessGrants()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(10),
        ]);

        return $token;
    }
}
