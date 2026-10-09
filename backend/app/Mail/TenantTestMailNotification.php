<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Test mail notification sent synchronously from the Admin Panel.
 * Intentionally does NOT implement ShouldQueue so the UI receives instant feedback.
 */
class TenantTestMailNotification extends Mailable
{
    use SerializesModels;


    public function __construct(
        public string $storeName,
        public string $fromEmail,
        public string $smtpHost,
        public int $smtpPort,
        public string $testedAt = '',
    ) {
        $this->testedAt = $testedAt ?: now()->toDayDateTimeString();
    }

    public function build(): static
    {
        $mail = $this
            ->subject("[{$this->storeName}] SMTP Mail Delivery Test Successful")
            ->view('emails.tenant-test-notification');

        if (! empty($this->fromEmail)) {
            $mail->from($this->fromEmail, $this->storeName);
            $mail->replyTo($this->fromEmail, $this->storeName);
        }

        return $mail;
    }
}
