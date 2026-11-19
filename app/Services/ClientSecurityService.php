<?php

namespace App\Services;

use App\Mail\ClientVerificationCodeMail;
use App\Models\Client;
use App\Models\ClientVerificationChallenge;
use App\Models\SettingEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ClientSecurityService
{
    private const MAX_CODE_ATTEMPTS = 5;

    private const MAX_CODE_SENDS = 3;

    private const CODE_SEND_WINDOW_MINUTES = 15;

    private const CODE_VALIDITY_MINUTES = 5;

    private const GRANT_VALIDITY_MINUTES = 10;

    public function requestEmailVerification(
        Client $client,
        ?string $email,
        Request $request
    ): void {
        $email = $email ? strtolower(trim($email)) : null;

        if ($client->email_verified_at) {
            if (! $email || $email === strtolower($client->email)) {
                throw ValidationException::withMessages([
                    'email' => 'Este e-mail já está confirmado.',
                ]);
            }

            $this->assertSensitiveAccess($request, $client);
            $targetEmail = $email;
        } elseif ($client->email) {
            if ($email && $email !== strtolower($client->email)) {
                throw ValidationException::withMessages([
                    'email' => 'Confirme primeiro o e-mail já cadastrado.',
                ]);
            }

            $targetEmail = $client->email;
        } else {
            if ($client->orders()->exists() || $client->addresses()->exists()) {
                abort(403, 'É necessária uma verificação de identidade adicional para cadastrar um e-mail nesta conta.');
            }

            if (! $email) {
                throw ValidationException::withMessages([
                    'email' => 'Informe um e-mail para confirmar.',
                ]);
            }

            $targetEmail = $email;
        }

        if (
            Client::query()
                ->where('email', $targetEmail)
                ->where('id', '!=', $client->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => 'Este e-mail já está associado a outra conta.',
            ]);
        }

        $this->issueCode($client, 'email', $targetEmail);
    }

    public function confirmEmailVerification(Client $client, string $code): array
    {
        $challenge = $this->consumeCode($client, 'email', $code);

        return DB::transaction(function () use ($client, $challenge) {
            $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);

            if (
                ! $lockedClient->email
                && ($lockedClient->orders()->exists() || $lockedClient->addresses()->exists())
            ) {
                abort(403, 'É necessária uma verificação de identidade adicional para cadastrar um e-mail nesta conta.');
            }

            if (
                $lockedClient->email
                && ! $lockedClient->email_verified_at
                && strtolower($lockedClient->email) !== strtolower($challenge->email)
            ) {
                throw ValidationException::withMessages([
                    'email' => 'Confirme primeiro o e-mail já cadastrado.',
                ]);
            }

            if (
                Client::query()
                    ->where('email', $challenge->email)
                    ->where('id', '!=', $lockedClient->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'email' => 'Este e-mail já está associado a outra conta.',
                ]);
            }

            $lockedClient->email = $challenge->email;
            $lockedClient->email_verified_at = now();
            $lockedClient->save();
            $lockedClient->sensitiveAccessGrants()->delete();

            return $this->clientSecurityData($lockedClient->fresh());
        });
    }

    public function requestSensitiveAccessCode(Client $client): void
    {
        if (! $client->email || ! $client->email_verified_at) {
            abort(403, 'Confirme um e-mail na conta antes de solicitar a visualização dos dados protegidos.');
        }

        $this->issueCode($client, 'sensitive_access', $client->email);
    }

    public function confirmSensitiveAccessCode(Client $client, string $code): array
    {
        $this->consumeCode($client, 'sensitive_access', $code);

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes(self::GRANT_VALIDITY_MINUTES);

        $grant = $client->sensitiveAccessGrants()->create([
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'expires_at' => $grant->expires_at->toIso8601String(),
        ];
    }

    public function hasSensitiveAccess(Request $request, Client $client): bool
    {
        $token = $request->header('X-Client-Verification');

        if (! is_string($token) || $token === '' || ! $client->email_verified_at) {
            return false;
        }

        return $client->sensitiveAccessGrants()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function assertSensitiveAccess(Request $request, Client $client): void
    {
        if (! $this->hasSensitiveAccess($request, $client)) {
            abort(403, 'Confirme sua identidade para visualizar os dados protegidos.');
        }
    }

    public function updateAddressStorage(Client $client, bool $enabled): array
    {
        if ($enabled && (! $client->email || ! $client->email_verified_at)) {
            throw ValidationException::withMessages([
                'address_storage_enabled' => 'Confirme um e-mail antes de ativar o armazenamento de endereços.',
            ]);
        }

        $client->address_storage_enabled = $enabled;
        $client->save();

        return $this->clientSecurityData($client->fresh());
    }

    private function issueCode(Client $client, string $purpose, string $email): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($client, $purpose, $email, $code) {
            Client::query()->lockForUpdate()->findOrFail($client->id);

            $sentRecently = ClientVerificationChallenge::query()
                ->where('client_id', $client->id)
                ->where('purpose', $purpose)
                ->where('created_at', '>=', now()->subMinutes(self::CODE_SEND_WINDOW_MINUTES))
                ->count();

            if ($sentRecently >= self::MAX_CODE_SENDS) {
                throw ValidationException::withMessages([
                    'code' => 'Limite de envio atingido. Tente novamente mais tarde.',
                ]);
            }

            ClientVerificationChallenge::query()
                ->where('client_id', $client->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            ClientVerificationChallenge::query()->create([
                'client_id' => $client->id,
                'purpose' => $purpose,
                'email' => $email,
                'code_hash' => $this->hashCode($code),
                'expires_at' => now()->addMinutes(self::CODE_VALIDITY_MINUTES),
            ]);
        });

        $this->configureMailer();
        Mail::to($email)->send(new ClientVerificationCodeMail($code));
    }

    private function consumeCode(
        Client $client,
        string $purpose,
        string $code
    ): ClientVerificationChallenge {
        $result = DB::transaction(function () use ($client, $purpose, $code) {
            $challenge = ClientVerificationChallenge::query()
                ->where('client_id', $client->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $challenge || $challenge->expires_at->isPast()) {
                return ['status' => 'expired'];
            }

            if ($challenge->attempts >= self::MAX_CODE_ATTEMPTS) {
                return ['status' => 'attempts_exceeded'];
            }

            if (! hash_equals($challenge->code_hash, $this->hashCode($code))) {
                $challenge->increment('attempts');

                return ['status' => 'invalid'];
            }

            $challenge->consumed_at = now();
            $challenge->save();

            return ['status' => 'valid', 'challenge' => $challenge];
        });

        if ($result['status'] !== 'valid') {
            $message = match ($result['status']) {
                'attempts_exceeded' => 'Limite de tentativas atingido. Solicite um novo código.',
                'expired' => 'O código expirou ou não está mais válido. Solicite um novo.',
                default => 'O código informado é inválido.',
            };

            throw ValidationException::withMessages(['code' => $message]);
        }

        return $result['challenge'];
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function configureMailer(): void
    {
        $settings = SettingEmail::query()->first();

        if ($settings) {
            app(EmailService::class)->configureAndSend($settings);
        }
    }

    private function clientSecurityData(Client $client): array
    {
        return [
            'id' => $client->id,
            'has_email' => (bool) $client->email,
            'email_verified' => (bool) $client->email_verified_at,
            'address_storage_enabled' => (bool) $client->address_storage_enabled,
            'can_bind_email' => ! $client->email
                && ! $client->orders()->exists()
                && ! $client->addresses()->exists(),
        ];
    }
}
