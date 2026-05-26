<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$updates = $body['updates'] ?? null;
if (!is_array($updates)) json_fail('updates_required');

// Whitelist
$allowed = [
    'app_name'             => 'string',
    'app_tagline'          => 'string',
    'groq_api_key'         => 'secret',
    'groq_model'           => 'string',
    'node_api_url'         => 'string',
    'node_api_key'         => 'secret',
    'socket_url'           => 'string',
    'webhook_secret'       => 'secret',
    'campaign_min_delay'   => 'int',
    'campaign_max_delay'   => 'int',
    'campaign_daily_limit' => 'int',
    'campaign_batch_size'  => 'int',
    'campaign_active_hours_start' => 'int',
    'campaign_active_hours_end'   => 'int',
    'retry_max_attempts'   => 'int',
    'retry_backoff_seconds'=> 'int',
    'feature_dark_mode'    => 'bool',
    'feature_notification_sound' => 'bool',
    'feature_logging'      => 'bool',
    'owner_brand_name'     => 'string',
    'owner_signature'      => 'string',
    'owner_offerings'      => 'string',
    'whatsapp_country_code'=> 'string',
];

$applied = 0;
foreach ($updates as $key => $value) {
    if (!isset($allowed[$key])) continue;
    $type = $allowed[$key];

    // Skip empty secrets to preserve existing
    if ($type === 'secret' && (string)$value === '') continue;
    if ($type === 'bool')  $value = !empty($value) ? 1 : 0;
    if ($type === 'int')   $value = (int)$value;

    SettingsRepository::set(
        $key,
        $value,
        $type,
        in_array($key, ['app_name','app_tagline','feature_dark_mode','feature_notification_sound','socket_url','owner_brand_name'], true)
    );
    $applied++;
}

AppLogger::info('settings_updated', ['count' => $applied, 'by' => Auth::user()['email'] ?? null], 'settings');
json_ok(['applied' => $applied]);
