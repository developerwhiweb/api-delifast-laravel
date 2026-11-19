<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class EmailService
{
    public function configureAndSend($emailSettings): void
    {
        if (! $emailSettings) {
            throw new \InvalidArgumentException('Configurações de e-mail não encontradas.');
        }

        $mailer = $emailSettings->mail_mailer ?: config('mail.default');
        Config::set([
            'mail.default' => $mailer,
            'mail.mailers.smtp.transport' => $mailer,
            'mail.mailers.smtp.host' => $emailSettings->mail_host ?: config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => $emailSettings->mail_port ?: config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.encryption' => $emailSettings->mail_encryption ?: config('mail.mailers.smtp.encryption'),
            'mail.mailers.smtp.username' => $emailSettings->mail_username ?: config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $emailSettings->mail_password ?: config('mail.mailers.smtp.password'),
            'mail.from.address' => $emailSettings->mail_from_address ?: config('mail.from.address'),
            'mail.from.name' => $emailSettings->mail_from_name ?: config('mail.from.name'),
        ]);
    }
}
