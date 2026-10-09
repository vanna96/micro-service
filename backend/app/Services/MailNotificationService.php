<?php

namespace App\Services;

use App\Mail\TenantTestMailNotification;
use App\Models\Tenant;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailNotificationService
{
    /**
     * Preserved central / environment configuration when switching contexts.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $centralConfig = null;

    /**
     * Default tenant mail settings schema.
     */
    public const DEFAULTS = [
        'mail_notifications_enabled' => false,
        'mail_mailer' => 'smtp',
        'mail_host' => '',
        'mail_port' => 1025,
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'null',
        'mail_from_address' => '',
        'mail_from_name' => '',
        'mail_order_notifications_enabled' => false,
    ];

    /**
     * Extract and normalize mail settings from a tenant's general_settings.
     *
     * @return array<string, mixed>
     */
    public function getTenantMailSettings(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?: tenant() ?: (function_exists('admin_current_tenant') ? admin_current_tenant() : null);

        $generalSettings = [];
        if ($tenant && is_array($tenant->general_settings)) {
            $generalSettings = $tenant->general_settings;
        }

        $storeName = $tenant
            ? (admin_tenant_display_name($tenant) ?: $tenant->alias)
            : config('app.name', 'VPOS');

        $port = $generalSettings['mail_port'] ?? null;
        $normalizedPort = (! empty($port) && is_numeric($port)) ? (int) $port : 1025;

        return [
            'mail_notifications_enabled' => ! empty($generalSettings['mail_notifications_enabled']),
            'mail_mailer' => (string) ($generalSettings['mail_mailer'] ?? 'smtp') ?: 'smtp',
            'mail_host' => trim((string) ($generalSettings['mail_host'] ?? '')),
            'mail_port' => $normalizedPort,
            'mail_username' => trim((string) ($generalSettings['mail_username'] ?? '')),
            'mail_password' => (string) ($generalSettings['mail_password'] ?? ''),
            'mail_encryption' => $this->normalizeEncryptionSetting($generalSettings['mail_encryption'] ?? 'null'),
            'mail_from_address' => trim((string) ($generalSettings['mail_from_address'] ?? ($generalSettings['contact_email'] ?? ''))),
            'mail_from_name' => trim((string) ($generalSettings['mail_from_name'] ?? ($generalSettings['store_name'] ?? $storeName))),
            'mail_order_notifications_enabled' => ! empty($generalSettings['mail_order_notifications_enabled']),
            'store_name' => $storeName,
        ];
    }

    /**
     * Check if a tenant has custom mail host configured.
     */
    public function isConfigured(?Tenant $tenant = null): bool
    {
        $settings = $this->getTenantMailSettings($tenant);

        return ! empty($settings['mail_host']);
    }

    /**
     * Check if mail notifications are active for a tenant.
     */
    public function isEnabled(?Tenant $tenant = null): bool
    {
        $settings = $this->getTenantMailSettings($tenant);

        return $settings['mail_notifications_enabled'] && $this->isConfigured($tenant);
    }

    /**
     * Dynamically configure Laravel's mailer from tenant database settings.
     */
    public function configureMailerForTenant(?Tenant $tenant = null): void
    {
        $tenant = $tenant ?: tenant() ?: (function_exists('admin_current_tenant') ? admin_current_tenant() : null);

        if (! $tenant) {
            return;
        }

        $settings = $this->getTenantMailSettings($tenant);

        // If no mail host is configured and notifications aren't enabled, keep system defaults
        if (! $settings['mail_notifications_enabled'] && empty($settings['mail_host'])) {
            return;
        }

        // Cache the central configuration once
        if ($this->centralConfig === null) {
            $this->centralConfig = [
                'default' => config('mail.default'),
                'mailers' => config('mail.mailers'),
                'from' => config('mail.from'),
            ];
        }

        $driver = $settings['mail_mailer'] ?: 'smtp';
        $encryption = $this->resolveEncryptionForTransport($settings['mail_encryption']);

        config([
            'mail.default' => $driver,
            "mail.mailers.{$driver}.transport" => $driver,
            "mail.mailers.{$driver}.host" => $settings['mail_host'] ?: config("mail.mailers.{$driver}.host"),
            "mail.mailers.{$driver}.port" => $settings['mail_port'] ?: config("mail.mailers.{$driver}.port"),
            "mail.mailers.{$driver}.encryption" => $encryption,
            "mail.mailers.{$driver}.username" => $settings['mail_username'] ?: null,
            "mail.mailers.{$driver}.password" => $settings['mail_password'] ?: null,
            'mail.from.address' => $settings['mail_from_address'] ?: config('mail.from.address'),
            'mail.from.name' => $settings['mail_from_name'] ?: config('mail.from.name'),
        ]);

        if (app()->bound('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }

    /**
     * Restore original central mail configuration and purge mailer caches.
     */
    public function restoreCentralConfiguration(): void
    {
        if ($this->centralConfig !== null) {
            config([
                'mail.default' => $this->centralConfig['default'],
                'mail.mailers' => $this->centralConfig['mailers'],
                'mail.from' => $this->centralConfig['from'],
            ]);
            $this->centralConfig = null;

            if (app()->bound('mail.manager')) {
                app('mail.manager')->forgetMailers();
            }
        }
    }

    /**
     * Send a live test email using provided credentials or saved tenant settings.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    public function sendTestEmail(string $recipientEmail, array $credentials = [], ?Tenant $tenant = null): array
    {
        $tenant = $tenant ?: tenant() ?: (function_exists('admin_current_tenant') ? admin_current_tenant() : null);
        $savedSettings = $this->getTenantMailSettings($tenant);

        $driver = $credentials['mail_mailer'] ?? $savedSettings['mail_mailer'] ?? 'smtp';
        $host = trim((string) ($credentials['mail_host'] ?? $savedSettings['mail_host'] ?? ''));
        $port = ! empty($credentials['mail_port']) ? (int) $credentials['mail_port'] : (int) ($savedSettings['mail_port'] ?? 1025);
        $encryptionSetting = $credentials['mail_encryption'] ?? $savedSettings['mail_encryption'] ?? 'null';
        $encryption = $this->resolveEncryptionForTransport($encryptionSetting);
        $username = trim((string) ($credentials['mail_username'] ?? $savedSettings['mail_username'] ?? ''));
        $password = (string) ($credentials['mail_password'] ?? $savedSettings['mail_password'] ?? '');
        $fromAddress = trim((string) ($credentials['mail_from_address'] ?? $savedSettings['mail_from_address'] ?? config('mail.from.address')));
        $fromName = trim((string) ($credentials['mail_from_name'] ?? $savedSettings['mail_from_name'] ?? $savedSettings['store_name']));

        if ($driver === 'smtp' && empty($host)) {
            return [
                'success' => false,
                'message' => __('SMTP Host is required to send a test email.'),
            ];
        }

        if (empty($fromAddress)) {
            $fromAddress = config('mail.from.address', 'hello@example.com');
        }

        $storeName = $fromName ?: ($savedSettings['store_name'] ?? config('app.name', 'VPOS'));

        try {
            $transportConfig = [
                'transport' => $driver,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username !== '' ? $username : null,
                'password' => $password !== '' ? $password : null,
                'timeout' => 10,
            ];

            if ($driver === 'sendmail') {
                $transportConfig = [
                    'transport' => 'sendmail',
                    'path' => config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs'),
                ];
            } elseif ($driver === 'log') {
                $transportConfig = [
                    'transport' => 'log',
                    'channel' => config('mail.mailers.log.channel'),
                ];
            }

            /** @var MailManager $manager */
            $manager = app('mail.manager');
            $mailer = $manager->build($transportConfig);
            $mailer->alwaysFrom($fromAddress, $storeName);

            $mailable = new TenantTestMailNotification(
                storeName: $storeName,
                fromEmail: $fromAddress,
                smtpHost: $host ?: $driver,
                smtpPort: $port,
            );

            $mailer->to($recipientEmail)->send($mailable);

            return [
                'success' => true,
                'message' => __("Test email successfully sent to :email via :target!", [
                    'email' => $recipientEmail,
                    'target' => $host ? "{$host}:{$port}" : $driver,
                ]),
            ];
        } catch (Throwable $e) {
            Log::error("[MailNotificationService] Test email failed: " . $e->getMessage(), [
                'recipient' => $recipientEmail,
                'host' => $host,
                'port' => $port,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => __("Failed to send test email: :error", ['error' => $e->getMessage()]),
            ];
        }
    }

    /**
     * Normalize encryption string for database and UI selection.
     */
    protected function normalizeEncryptionSetting(mixed $encryption): string
    {
        $val = strtolower(trim((string) $encryption));
        if ($val === 'ssl' || $val === 'smtps') {
            return 'ssl';
        }
        if ($val === 'tls' || $val === 'starttls') {
            return 'tls';
        }

        return 'null';
    }

    /**
     * Resolve encryption value for Symfony Mailer transport.
     */
    protected function resolveEncryptionForTransport(mixed $encryption): ?string
    {
        $val = $this->normalizeEncryptionSetting($encryption);
        if ($val === 'null' || $val === 'none') {
            return null;
        }

        return $val;
    }
}
