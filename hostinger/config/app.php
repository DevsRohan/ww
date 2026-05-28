<?php
/**
 * Application Configuration
 * --------------------------------------------
 * Edit values below for production.
 * NOTE: Sensitive values (API keys, secrets) can also be overridden
 * dynamically via the dashboard Settings panel and stored in the
 * `settings` DB table — DB values take precedence at runtime.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

return [
    // Brand / UI
    'app_name'     => 'WhatsApp CRM',
    'app_tagline'  => 'Cold Outreach Operating System',
    'app_version'  => '1.1.0',

    // Environment
    'env'          => 'production', // production | development
    'debug'        => false,
    'timezone'     => 'Asia/Kolkata',

    // URLs
    'base_url'     => '', // e.g. https://yourdomain.com  (empty = auto-detect)
    'public_path'  => '/', // sub-folder if any

    // Database (override via env or edit directly)
    'db' => [
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'port'     => (int)(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'whatsapp_crm',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
        'collation'=> 'utf8mb4_unicode_ci',
    ],

    // Hugging Face Node backend
    'node' => [
        'api_url' => '', // https://your-space.hf.space   (managed via Settings)
        'api_key' => '', // managed via Settings
        'timeout' => 20,
    ],

    // Public Socket.io URL (browser connects directly)
    'socket_url' => '',

    // Webhook receiver
    'webhook' => [
        'secret'           => '', // managed via Settings
        'tolerance_seconds'=> 300, // anti-replay window
    ],

    // Groq AI
    'groq' => [
        'api_key' => '',
        'model'   => 'llama-3.3-70b-versatile',
        'endpoint'=> 'https://api.groq.com/openai/v1/chat/completions',
        'timeout' => 30,
        'max_tokens' => 800,
        'temperature'=> 0.7,
    ],

    // Campaign defaults
    'campaign' => [
        'min_delay'    => 120,
        'max_delay'    => 300,
        'daily_limit'  => 60,
        'batch_size'   => 5,
        'active_start' => 6,  // 6 AM
        'active_end'   => 23, // 11 PM
    ],

    // Retry policy
    'retry' => [
        'max_attempts'    => 3,
        'backoff_seconds' => 600,
    ],

    // Phone normalization
    'phone' => [
        'default_country_code' => '91',
    ],

    // Sender identity (used by Groq prompts)
    'owner' => [
        'brand_name'  => 'Rohan Digital',
        'signature'   => 'DevsArun',
        'offerings'   => 'Landing Pages, Business Websites, eCommerce, Web Apps, AI Agents, Automation, Android Apps, Chrome Extensions, Digital Marketing',
    ],

    // Auth
    'auth' => [
        'session_name'    => 'wcrm_sess',
        'session_lifetime'=> 86400,    // 24h
        'login_throttle'  => 5,         // attempts
        'login_lockout'   => 600,       // 10m
    ],

    // Feature flags
    'features' => [
        'dark_mode'           => false,
        'notification_sound'  => true,
        'logging'             => true,
    ],

    // Paths
    'paths' => [
        'uploads' => APP_ROOT . '/uploads',
        'logs'    => APP_ROOT . '/logs',
    ],
];
