<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Services\MailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerVerificationCode extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $storeName,
        public int $expiresInMinutes = 10,
        public ?string $tenantId = null,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {
        $this->tenantId = $tenantId ?: (tenant('id') ? (string) tenant('id') : null);
        $this->fromAddress = $fromAddress ?: (string) config('mail.from.address');
        $this->fromName = $fromName ?: ((string) config('mail.from.name') ?: $storeName);
    }

    public function build(): static
    {
        if ($this->tenantId && (! tenant() || (string) tenant('id') !== (string) $this->tenantId)) {
            $tenant = Tenant::find($this->tenantId);
            if ($tenant && app()->bound(MailNotificationService::class)) {
                app(MailNotificationService::class)->configureMailerForTenant($tenant);
            }
        }

        $mail = $this
            ->subject($this->storeName.' email verification code')
            ->view('emails.customer-verification-code');

        $fromAddress = $this->fromAddress ?: config('mail.from.address');
        $fromName = $this->fromName ?: (config('mail.from.name') ?: $this->storeName);
        if (! empty($fromAddress)) {
            $mail->from($fromAddress, $fromName);
            $mail->replyTo($fromAddress, $fromName);
        }

        return $mail;
    }
}
