<?php
/**
 * Bootstrap — single entry point loaded by every PHP page/api/script.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load config
$APP = require APP_ROOT . '/config/app.php';

// Timezone + locale
date_default_timezone_set($APP['timezone'] ?? 'Asia/Kolkata');
setlocale(LC_ALL, 'en_IN.UTF-8');

// Errors
if (!empty($APP['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/logs/php_errors.log');

// DB
require_once APP_ROOT . '/config/db.php';
DB::init($APP['db']);

// Includes
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/logger.php';
require_once APP_ROOT . '/includes/settings_repository.php';
require_once APP_ROOT . '/includes/lead_repository.php';
require_once APP_ROOT . '/includes/message_repository.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/node_client.php';
require_once APP_ROOT . '/includes/groq.php';
require_once APP_ROOT . '/includes/csv_parser.php';

// Merge dynamic settings from DB over static config
$dyn = SettingsRepository::all();
if (!empty($dyn['groq_api_key']))      $APP['groq']['api_key']     = $dyn['groq_api_key'];
if (!empty($dyn['groq_model']))        $APP['groq']['model']       = $dyn['groq_model'];
if (!empty($dyn['node_api_url']))      $APP['node']['api_url']     = $dyn['node_api_url'];
if (!empty($dyn['node_api_key']))      $APP['node']['api_key']     = $dyn['node_api_key'];
if (!empty($dyn['socket_url']))        $APP['socket_url']          = $dyn['socket_url'];
if (!empty($dyn['webhook_secret']))    $APP['webhook']['secret']   = $dyn['webhook_secret'];
if (isset($dyn['campaign_min_delay'])) $APP['campaign']['min_delay']  = (int)$dyn['campaign_min_delay'];
if (isset($dyn['campaign_max_delay'])) $APP['campaign']['max_delay']  = (int)$dyn['campaign_max_delay'];
if (isset($dyn['campaign_daily_limit']))$APP['campaign']['daily_limit']= (int)$dyn['campaign_daily_limit'];
if (isset($dyn['campaign_batch_size'])) $APP['campaign']['batch_size'] = (int)$dyn['campaign_batch_size'];
if (isset($dyn['campaign_active_hours_start'])) $APP['campaign']['active_start']= (int)$dyn['campaign_active_hours_start'];
if (isset($dyn['campaign_active_hours_end']))   $APP['campaign']['active_end']  = (int)$dyn['campaign_active_hours_end'];
if (!empty($dyn['owner_brand_name']))   $APP['owner']['brand_name'] = $dyn['owner_brand_name'];
if (!empty($dyn['owner_signature']))    $APP['owner']['signature']  = $dyn['owner_signature'];
if (!empty($dyn['owner_offerings']))    $APP['owner']['offerings']  = $dyn['owner_offerings'];
if (isset($dyn['whatsapp_country_code']))$APP['phone']['default_country_code'] = (string)$dyn['whatsapp_country_code'];
if (isset($dyn['feature_dark_mode']))   $APP['features']['dark_mode']         = (bool)(int)$dyn['feature_dark_mode'];
if (isset($dyn['feature_notification_sound'])) $APP['features']['notification_sound'] = (bool)(int)$dyn['feature_notification_sound'];
if (isset($dyn['feature_logging']))     $APP['features']['logging']           = (bool)(int)$dyn['feature_logging'];

// Auto-detect base_url if empty
if (empty($APP['base_url'])) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $APP['base_url'] = $proto . '://' . $host;
}

// Make $APP globally available
$GLOBALS['APP'] = $APP;
