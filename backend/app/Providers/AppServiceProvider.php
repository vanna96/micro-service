<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureCorsFromSecuritySettings();
    }

    /**
     * Dynamically configure CORS origins and settings from Security Center.
     */
    protected function configureCorsFromSecuritySettings(): void
    {
        try {
            if (class_exists(\App\Models\SecuritySetting::class)) {
                $rawOrigins = \App\Models\SecuritySetting::get('cors_allowed_origins');
                if ($rawOrigins !== null && trim((string) $rawOrigins) !== '') {
                    $origins = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $rawOrigins))));
                    if (! empty($origins)) {
                        config(['cors.allowed_origins' => $origins]);
                    }
                }

                $supportsCreds = \App\Models\SecuritySetting::get('cors_supports_credentials');
                if ($supportsCreds !== null) {
                    config(['cors.supports_credentials' => filter_var($supportsCreds, FILTER_VALIDATE_BOOLEAN)]);
                }

                $rawMethods = \App\Models\SecuritySetting::get('cors_allowed_methods');
                if ($rawMethods !== null && trim((string) $rawMethods) !== '') {
                    $methods = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $rawMethods))));
                    if (! empty($methods)) {
                        config(['cors.allowed_methods' => $methods]);
                    }
                }

                $rawHeaders = \App\Models\SecuritySetting::get('cors_allowed_headers');
                if ($rawHeaders !== null && trim((string) $rawHeaders) !== '') {
                    $headers = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $rawHeaders))));
                    if (! empty($headers)) {
                        config(['cors.allowed_headers' => $headers]);
                    }
                }

                $maxAge = \App\Models\SecuritySetting::get('cors_max_age');
                if ($maxAge !== null && is_numeric($maxAge)) {
                    config(['cors.max_age' => (int) $maxAge]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if database or table not ready during migrations/setup
        }
    }
}
