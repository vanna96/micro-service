<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Notifications Enabled
    |--------------------------------------------------------------------------
    |
    | Globally control whether Telegram order notifications are enabled.
    |
    */

    'enabled' => env('TELEGRAM_NOTIFICATIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Credentials
    |--------------------------------------------------------------------------
    |
    | The bot token issued by @BotFather and default chat ID where
    | order receipts should be sent if not overridden by the tenant.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Telegram Error Log Notifications
    |--------------------------------------------------------------------------
    |
    | Configuration for routing application errors and unhandled exceptions
    | directly from laravel.log to a dedicated Telegram channel or chat.
    |
    */

    'error_log_enabled' => env('TELEGRAM_LOG_ENABLED', false),

    'error_log_bot_token' => env('TELEGRAM_LOG_BOT_TOKEN'),

    'error_log_chat_id' => env('TELEGRAM_LOG_CHAT_ID'),

    'error_log_level' => env('TELEGRAM_LOG_LEVEL', 'error'),

    /*
    |--------------------------------------------------------------------------
    | API Request Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds for requests sent to the Telegram Bot API.
    |
    */

    'timeout' => (int) env('TELEGRAM_TIMEOUT', 10),

];
