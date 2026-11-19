<?php
/**
 * ZeroVault - Zero-Database, Self-Hosted Secure File Sharing & Ephemeral Vault
 * 
 * Multi-language (English default, Turkish, and extensible), Light & Dark Theme,
 * Flat-file architecture with zero external database requirements.
 * 
 * @license MIT
 */

// PHP Built-in Web Server (CLI Server) Router Support
if (php_sapi_name() === 'cli-server') {
    $uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($uri_path !== '/' && file_exists(__DIR__ . $uri_path) && !is_dir(__DIR__ . $uri_path)) {
        return false;
    }
}

// PHP & Session Configuration
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// Security HTTP Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Directories & Files
$VAULT_DIR = __DIR__ . '/vault';
$LANG_DIR = __DIR__ . '/languages';
$CONFIG_FILE = $VAULT_DIR . '/config.json';
$DB_FILE = $VAULT_DIR . '/db.json';
$SECURITY_FILE = $VAULT_DIR . '/security_cache.json';

// Allowed and Banned Extensions
$ALLOWED_EXTENSIONS = [
    'zip', 'rar', '7z', 'tar', 'gz',
    'pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif',
    'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'
];

$BANNED_EXTENSIONS = [
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar',
    'cgi', 'pl', 'exe', 'sh', 'bat', 'cmd', 'vbs', 'ps1',
    'jsp', 'asp', 'aspx', 'htm', 'html', 'svg', 'js', 'htaccess'
];

// Ensure Vault & Languages Directories Exist
if (!file_exists($VAULT_DIR)) mkdir($VAULT_DIR, 0755, true);
if (!file_exists($LANG_DIR)) mkdir($LANG_DIR, 0755, true);

// Protect Vault Directory
$vault_htaccess = $VAULT_DIR . '/.htaccess';
if (!file_exists($vault_htaccess)) {
    $rules = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
             "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n" .
             "Options -Indexes -ExecCGI\n" .
             "<Files \"*\">\n    SetHandler default-handler\n</Files>\n";
    file_put_contents($vault_htaccess, $rules, LOCK_EX);
}

// Protect Languages Directory
$lang_htaccess = $LANG_DIR . '/.htaccess';
if (!file_exists($lang_htaccess)) {
    $lang_rules = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
                  "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
    file_put_contents($lang_htaccess, $lang_rules, LOCK_EX);
}

// ==== MULTI-LANGUAGE (i18n) ENGINE ====

function get_available_languages() {
    global $LANG_DIR;
    $languages = [];
    if (!is_dir($LANG_DIR)) return $languages;

    foreach (glob($LANG_DIR . '/*.json') as $file) {
        $code = basename($file, '.json');
        $content = json_decode(@file_get_contents($file), true);
        if (is_array($content) && isset($content['__meta'])) {
            $languages[$code] = [
                'code' => $code,
                'name' => $content['__meta']['name'] ?? strtoupper($code),
                'file' => $file
            ];
        }
    }

    if (empty($languages)) {
        $languages['en'] = ['code' => 'en', 'name' => 'English', 'file' => $LANG_DIR . '/en.json'];
    }
    return $languages;
}

function load_language_strings($code) {
    global $LANG_DIR;
    $file = $LANG_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $code) . '.json';
    if (file_exists($file)) {
        $data = json_decode(@file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return [];
}

// Configuration Helper
function is_system_installed() {
    global $CONFIG_FILE;
    if (!file_exists($CONFIG_FILE)) return false;
    $cfg = json_decode(@file_get_contents($CONFIG_FILE), true);
    return !empty($cfg['admin_username']) && !empty($cfg['admin_password_hash']);
}

function get_config() {
    global $CONFIG_FILE;
    if (!file_exists($CONFIG_FILE)) {
        return [
            'app_name' => 'ZeroVault | Secure File Delivery & Vault',
            'admin_username' => '',
            'admin_password_hash' => '',
            'max_upload_mb' => 100,
            'default_lang' => 'en',
            'installed_at' => null
        ];
    }
    $cfg = json_decode(@file_get_contents($CONFIG_FILE), true);
    return is_array($cfg) ? $cfg : [];
}

function save_config($data) {
    global $CONFIG_FILE;
    return file_put_contents($CONFIG_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Active Language Resolution
$available_langs = get_available_languages();
$config = get_config();
$default_lang = $config['default_lang'] ?? 'en';

if (isset($_GET['lang']) && isset($available_langs[$_GET['lang']])) {
    $current_lang = $_GET['lang'];
    $_SESSION['zerovault_lang'] = $current_lang;
    setcookie('zerovault_lang', $current_lang, time() + (86400 * 365), '/');
} elseif (!empty($_SESSION['zerovault_lang']) && isset($available_langs[$_SESSION['zerovault_lang']])) {
    $current_lang = $_SESSION['zerovault_lang'];
} elseif (!empty($_COOKIE['zerovault_lang']) && isset($available_langs[$_COOKIE['zerovault_lang']])) {
    $current_lang = $_COOKIE['zerovault_lang'];
} else {
    $current_lang = isset($available_langs[$default_lang]) ? $default_lang : 'en';
}

$TRANSLATIONS = load_language_strings($current_lang);
$FALLBACK_TRANSLATIONS = ($current_lang !== 'en') ? load_language_strings('en') : $TRANSLATIONS;

function __($key, $fallback = '') {
    global $TRANSLATIONS, $FALLBACK_TRANSLATIONS;
    if (isset($TRANSLATIONS[$key]) && $TRANSLATIONS[$key] !== '') {
        return $TRANSLATIONS[$key];
    }
    if (isset($FALLBACK_TRANSLATIONS[$key]) && $FALLBACK_TRANSLATIONS[$key] !== '') {
        return $FALLBACK_TRANSLATIONS[$key];
    }
    return $fallback ?: $key;
}

// Database Helpers
function get_db() {
    global $DB_FILE;
    if (!file_exists($DB_FILE)) return [];
    $content = @file_get_contents($DB_FILE);
    return json_decode($content, true) ?: [];
}

function save_db($data) {
    global $DB_FILE;
    return file_put_contents($DB_FILE, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// IP-Based Security Cache
function get_security_cache() {
    global $SECURITY_FILE;
    if (!file_exists($SECURITY_FILE)) return [];
    $data = json_decode(@file_get_contents($SECURITY_FILE), true);
    if (!is_array($data)) return [];

    $now = time();
    $cleaned = [];
    foreach ($data as $ip => $records) {
        if (!is_array($records)) continue;
        foreach ($records as $type => $info) {
            if (isset($info['expires_at']) && $info['expires_at'] > $now) {
                $cleaned[$ip][$type] = $info;
            }
        }
    }
    return $cleaned;
}

function save_security_cache($cache) {
    global $SECURITY_FILE;
    return file_put_contents($SECURITY_FILE, json_encode($cache, JSON_PRETTY_PRINT), LOCK_EX);
}

function check_ip_lock($type, $max_attempts = 5, $lock_seconds = 300) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cache = get_security_cache();
    $now = time();

    if (isset($cache[$ip][$type])) {
        $entry = $cache[$ip][$type];
        if (isset($entry['locked_until']) && $entry['locked_until'] > $now) {
            return [
                'locked' => true,
                'remaining' => $entry['locked_until'] - $now,
                'attempts' => $entry['attempts'] ?? 0
            ];
        }
    }
    return ['locked' => false, 'remaining' => 0, 'attempts' => $cache[$ip][$type]['attempts'] ?? 0];
}

function record_ip_attempt($type, $success, $max_attempts = 5, $lock_seconds = 300) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cache = get_security_cache();
    $now = time();

    if ($success) {
        if (isset($cache[$ip][$type])) {
            unset($cache[$ip][$type]);
            save_security_cache($cache);
        }
        return;
    }

    $entry = $cache[$ip][$type] ?? ['attempts' => 0, 'locked_until' => 0, 'expires_at' => $now + 3600];
    $entry['attempts'] = ($entry['attempts'] ?? 0) + 1;
    $entry['expires_at'] = $now + 3600;

    if ($entry['attempts'] >= $max_attempts) {
        $entry['locked_until'] = $now + $lock_seconds;
    }

    $cache[$ip][$type] = $entry;
    save_security_cache($cache);
}

function formatBytes($size, $precision = 1) {
    if ($size <= 0) return "0 B";
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = floor($base);
    return round(pow(1024, $base - $index), $precision) . ' ' . ($suffixes[$index] ?? 'B');
}

function get_dir_size($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : get_dir_size($each);
    }
    return $size;
}

function time_elapsed_string($datetime) {
    if (!$datetime) return '-';
    try {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = ['y' => 'y', 'm' => 'm', 'w' => 'w', 'd' => 'd', 'h' => 'h', 'i' => 'min', 's' => 'sec'];
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v;
            } else {
                unset($string[$k]);
            }
        }
        $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    } catch (Exception $e) {
        return '-';
    }
}

// Inline Vector Icons
function render_icon($name, $classes = "icon") {
    $icons = [
        'vault' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="3"/><path d="m14.5 9.5 2-2"/><path d="m7.5 16.5 2-2"/><path d="m9.5 9.5-2-2"/><path d="m16.5 16.5-2-2"/></svg>',
        'upload' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>',
        'download' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>',
        'shield' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'lock' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'key' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15.5 8.5 2 2"/><path d="M2.5 19.5 8 14l3 3 1.5-1.5-3-3L11 11l4.5 4.5 3-3-9-9a4.24 4.24 0 0 0-6 6l7 7Z"/></svg>',
        'copy' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>',
        'trash' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>',
        'pause' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="10" x2="10" y1="15" y2="9"/><line x1="14" x2="14" y1="15" y2="9"/></svg>',
        'play' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>',
        'search' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
        'settings' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
        'file' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'logout' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>',
        'check' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        'external' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>',
        'globe' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
        'sun' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>',
        'moon' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>',
        'language' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>',
        'edit' => '<svg class="'.$classes.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>'
    ];
    return $icons[$name] ?? '';
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die("Security error: Invalid CSRF token. Please refresh the page and try again.");
    }
}

// Dynamic Base Paths
$script_name = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$base_dir = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
if ($base_dir === '/' || $base_dir === '\\') $base_dir = '';
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base_dir;
$admin_url = $base_dir . '/admin';
$home_url = $base_dir . '/';

$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$route_path = (!empty($base_dir) && str_starts_with($request_uri, $base_dir)) ? substr($request_uri, strlen($base_dir)) : $request_uri;
$is_admin_route = (basename(rtrim($route_path, '/')) === 'admin') || isset($_GET['admin']);

$is_installed = is_system_installed();
$app_name = !empty($config['app_name']) ? $config['app_name'] : __('app_name_default', 'ZeroVault | Secure File Delivery & Vault');

// ==== 1. INITIAL SETUP WIZARD ====
if (!$is_installed) {
    $setup_error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
        verify_csrf();
        $in_app_name = trim($_POST['app_name'] ?? '');
        $in_username = trim($_POST['username'] ?? '');
        $in_password = $_POST['password'] ?? '';
        $in_confirm = $_POST['password_confirm'] ?? '';
        $in_max_mb = (int)($_POST['max_upload_mb'] ?? 100);
        $in_lang = trim($_POST['default_lang'] ?? 'en');

        if (empty($in_username) || strlen($in_username) < 3) {
            $setup_error = "Admin username must be at least 3 characters.";
        } elseif (strlen($in_password) < 8) {
            $setup_error = "Admin password must be at least 8 characters.";
        } elseif ($in_password !== $in_confirm) {
            $setup_error = "Passwords do not match.";
        } elseif ($in_max_mb < 1 || $in_max_mb > 2048) {
            $setup_error = "Max upload limit must be between 1 and 2048 MB.";
        } else {
            $new_config = [
                'app_name' => !empty($in_app_name) ? htmlspecialchars($in_app_name, ENT_QUOTES, 'UTF-8') : 'ZeroVault | Secure File Delivery & Vault',
                'admin_username' => $in_username,
                'admin_password_hash' => password_hash($in_password, PASSWORD_BCRYPT),
                'max_upload_mb' => $in_max_mb,
                'default_lang' => isset($available_langs[$in_lang]) ? $in_lang : 'en',
                'installed_at' => date('Y-m-d H:i:s')
            ];

            if (save_config($new_config)) {
                if (!file_exists($DB_FILE)) save_db([]);
                $_SESSION['is_admin'] = true;
                header('Location: ' . $admin_url . '?setup_done=1');
                exit;
            } else {
                $setup_error = "Failed to write 'vault/config.json'. Please check file permissions.";
            }
        }
    }
    render_setup_view($setup_error, $csrf_token, $available_langs, $current_lang);
    exit;
}

// ==== 2. ADMIN AUTHENTICATION ====
if (isset($_POST['admin_logout'])) {
    verify_csrf();
    unset($_SESSION['is_admin']);
    session_regenerate_id(true);
    header('Location: ' . $admin_url);
    exit;
}

$login_error = null;
if (isset($_POST['login'])) {
    verify_csrf();
    $req_username = trim($_POST['username'] ?? '');
    $req_password = $_POST['password'] ?? '';

    $lock_info = check_ip_lock('admin_login', 5, 300);
    if ($lock_info['locked']) {
        $login_error = "Too many failed attempts. Locked for " . $lock_info['remaining'] . " seconds.";
    } else {
        $admin_user = $config['admin_username'] ?? '';
        $admin_hash = $config['admin_password_hash'] ?? '';

        if (hash_equals($admin_user, $req_username) && password_verify($req_password, $admin_hash)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            record_ip_attempt('admin_login', true);
            header('Location: ' . $admin_url);
            exit;
        } else {
            record_ip_attempt('admin_login', false, 5, 300);
            $new_lock = check_ip_lock('admin_login', 5, 300);
            if ($new_lock['locked']) {
                $login_error = "Too many failed login attempts! Access locked for 5 minutes.";
            } else {
                $rem = max(0, 5 - $new_lock['attempts']);
                $login_error = "Invalid username or password. Remaining attempts: " . $rem;
            }
        }
    }
}

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// ==== 3. SECURE FILE DOWNLOAD ====
if (isset($_GET['download'])) {
    $id = trim($_GET['download']);

    if (!preg_match('/^[a-f0-9]{64}$/', $id)) {
        http_response_code(400);
        die("Invalid file identifier.");
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rate_key = 'dl_' . md5($ip . '_' . $id);
    $rate_check = check_ip_lock($rate_key, 2, 2);
    if ($rate_check['locked']) {
        http_response_code(429);
        die("Rate limit exceeded. Please wait a moment.");
    }
    record_ip_attempt($rate_key, false, 2, 2);

    $db = get_db();
    $found_key = null;
    foreach ($db as $k => $file) {
        if ($file['id'] === $id) {
            $found_key = $k;
            break;
        }
    }

    if ($found_key === null) {
        http_response_code(404);
        die("File not found in vault.");
    }

    $file = &$db[$found_key];
    $is_active = $file['is_active'] ?? true;
    if (!$is_active) {
        http_response_code(403);
        die("File distribution has been suspended.");
    }

    if (!empty($file['password'])) {
        $auth_key = 'auth_' . $file['id'];
        if (empty($_SESSION[$auth_key])) {
            http_response_code(401);
            die("Password verification required.");
        }
    }

    $safe_filename = basename($file['real_file_name']);
    $filepath = $VAULT_DIR . '/' . $safe_filename;
    $real_vault = realpath($VAULT_DIR);
    $real_file = realpath($filepath);

    if (!$real_file || !str_starts_with($real_file, $real_vault) || !file_exists($real_file)) {
        http_response_code(404);
        die("Physical file not found.");
    }

    $file['download_count'] = ($file['download_count'] ?? 0) + 1;
    $file['last_downloaded'] = date('Y-m-d H:i:s');
    save_db($db);

    if (ob_get_level()) ob_end_clean();

    $download_name = preg_replace('/[^a-zA-Z0-9_\-\. \(\)\[\]]/', '_', $file['display_name']);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($real_file));
    readfile($real_file);
    exit;
}

// ==== 4. ADMIN ACTIONS ====
$msg = null;
$err = null;

if ($is_admin && $is_admin_route) {
    if (isset($_GET['setup_done'])) {
        $msg = "ZeroVault successfully installed and activated!";
    }

    // 4.1 File Upload
    if (isset($_POST['upload'])) {
        verify_csrf();
        $display_name = trim($_POST['display_name'] ?? '');
        $virustotal_url = trim($_POST['virustotal_url'] ?? '');
        $file_password = trim($_POST['password'] ?? '');

        if (!empty($virustotal_url) && !preg_match('#^https://(www\.)?virustotal\.com/#i', $virustotal_url)) {
            $err = "VirusTotal URL must start with 'https://www.virustotal.com/'.";
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $max_bytes = ($config['max_upload_mb'] ?? 100) * 1024 * 1024;
            $file_size = $_FILES['file']['size'];

            if ($file_size > $max_bytes) {
                $err = "File size exceeds the configured limit of " . ($config['max_upload_mb'] ?? 100) . " MB.";
            } else {
                $tmp_name = $_FILES['file']['tmp_name'];
                $orig_name = basename($_FILES['file']['name']);
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                $name_parts = explode('.', strtolower($orig_name));
                $has_banned = false;
                foreach ($name_parts as $p) {
                    if (in_array($p, $BANNED_EXTENSIONS)) {
                        $has_banned = true;
                        break;
                    }
                }

                if (!in_array($ext, $ALLOWED_EXTENSIONS) || $has_banned) {
                    $err = "File type not permitted. Allowed: " . implode(', ', $ALLOWED_EXTENSIONS);
                } else {
                    $mime = 'application/octet-stream';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $tmp_name);
                        finfo_close($finfo);
                    }

                    $banned_mimes = ['application/x-httpd-php', 'application/x-php', 'text/x-php', 'application/x-executable', 'text/html'];
                    if (in_array(strtolower($mime), $banned_mimes)) {
                        $err = "Security check failed: File appears to contain executable code.";
                    } else {
                        $real_file_name = bin2hex(random_bytes(16)) . '.' . $ext;
                        $destination = $VAULT_DIR . '/' . $real_file_name;

                        if (move_uploaded_file($tmp_name, $destination)) {
                            $clean_display = !empty($display_name) ? strip_tags($display_name) : $orig_name;
                            $clean_display = mb_substr($clean_display, 0, 100);

                            $db = get_db();
                            $hashed_pw = !empty($file_password) ? password_hash($file_password, PASSWORD_BCRYPT) : '';

                            $db[] = [
                                'id' => hash('sha256', uniqid(mt_rand(), true)),
                                'real_file_name' => $real_file_name,
                                'display_name' => $clean_display,
                                'size' => filesize($destination),
                                'upload_date' => date('Y-m-d H:i:s'),
                                'download_count' => 0,
                                'virustotal_url' => $virustotal_url,
                                'password' => $hashed_pw,
                                'is_active' => true,
                                'last_downloaded' => null
                            ];
                            save_db($db);
                            $msg = "File successfully stored in the secure vault.";
                        } else {
                            $err = "Server error while saving the file.";
                        }
                    }
                }
            }
        } else {
            $err = "Please select a file to upload.";
        }
    }

    // 4.2 Delete File
    if (isset($_POST['delete_id'])) {
        verify_csrf();
        $id = trim($_POST['delete_id']);
        $db = get_db();
        $found = false;

        foreach ($db as $key => $file) {
            if ($file['id'] === $id) {
                $filepath = $VAULT_DIR . '/' . basename($file['real_file_name']);
                if (file_exists($filepath)) @unlink($filepath);
                unset($db[$key]);
                save_db($db);
                $found = true;
                $msg = "File permanently destroyed from storage and database.";
                break;
            }
        }
        if (!$found) $err = "File not found.";
    }

    // 4.3 Toggle File Status
    if (isset($_POST['toggle_id'])) {
        verify_csrf();
        $id = trim($_POST['toggle_id']);
        $db = get_db();
        foreach ($db as &$file) {
            if ($file['id'] === $id) {
                $file['is_active'] = !($file['is_active'] ?? true);
                save_db($db);
                $msg = $file['is_active'] ? "File distribution resumed." : "File distribution suspended.";
                break;
            }
        }
    }

    // 4.4 Update System Settings
    if (isset($_POST['update_settings'])) {
        verify_csrf();
        $new_app_name = trim($_POST['app_name'] ?? '');
        $new_username = trim($_POST['admin_username'] ?? '');
        $curr_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $new_max_mb = (int)($_POST['max_upload_mb'] ?? 100);
        $new_default_lang = trim($_POST['default_lang'] ?? 'en');

        if (!password_verify($curr_pass, $config['admin_password_hash'])) {
            $err = "Current admin password verification failed.";
        } elseif (empty($new_username) || strlen($new_username) < 3) {
            $err = "Username must be at least 3 characters.";
        } elseif ($new_max_mb < 1 || $new_max_mb > 2048) {
            $err = "Max upload limit must be between 1 and 2048 MB.";
        } else {
            $config['app_name'] = !empty($new_app_name) ? htmlspecialchars($new_app_name, ENT_QUOTES, 'UTF-8') : 'ZeroVault | Secure File Delivery & Vault';
            $config['admin_username'] = $new_username;
            $config['max_upload_mb'] = $new_max_mb;
            if (isset($available_langs[$new_default_lang])) {
                $config['default_lang'] = $new_default_lang;
            }

            if (!empty($new_pass)) {
                if (strlen($new_pass) < 8) {
                    $err = "New password must be at least 8 characters.";
                } else {
                    $config['admin_password_hash'] = password_hash($new_pass, PASSWORD_BCRYPT);
                    save_config($config);
                    $msg = "Admin password and system configuration successfully updated.";
                }
            } else {
                save_config($config);
                $msg = "System settings updated.";
            }
        }
    }

    // 4.5 Language Management Actions
    // Add New Language
    if (isset($_POST['add_language'])) {
        verify_csrf();
        $new_code = strtolower(trim($_POST['new_lang_code'] ?? ''));
        $new_name = trim($_POST['new_lang_name'] ?? '');

        if (!preg_match('/^[a-z]{2,5}$/', $new_code)) {
            $err = "Language code must be 2 to 5 lowercase letters (e.g. de, fr, es).";
        } elseif (empty($new_name)) {
            $err = "Language name cannot be empty.";
        } elseif (file_exists($LANG_DIR . '/' . $new_code . '.json')) {
            $err = "Language code '" . htmlspecialchars($new_code) . "' already exists.";
        } else {
            $base_template = load_language_strings('en');
            $base_template['__meta'] = [
                'name' => htmlspecialchars($new_name, ENT_QUOTES, 'UTF-8'),
                'code' => $new_code
            ];
            file_put_contents($LANG_DIR . '/' . $new_code . '.json', json_encode($base_template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            $msg = "Language pack '" . htmlspecialchars($new_name) . "' created. You can now edit its strings.";
            $available_langs = get_available_languages();
        }
    }

    // Delete Language Pack
    if (isset($_POST['delete_language'])) {
        verify_csrf();
        $del_code = strtolower(trim($_POST['del_lang_code'] ?? ''));

        if ($del_code === 'en') {
            $err = "The core English language pack cannot be deleted.";
        } elseif ($del_code === ($config['default_lang'] ?? 'en')) {
            $err = "Cannot delete the default system language. Change default language first.";
        } else {
            $target_lang_file = $LANG_DIR . '/' . preg_replace('/[^a-z]/', '', $del_code) . '.json';
            if (file_exists($target_lang_file)) {
                @unlink($target_lang_file);
                $msg = "Language pack successfully removed.";
                $available_langs = get_available_languages();
            } else {
                $err = "Language pack not found.";
            }
        }
    }

    // Save Edited Language Translations
    if (isset($_POST['save_language_strings'])) {
        verify_csrf();
        $edit_code = strtolower(trim($_POST['edit_lang_code'] ?? ''));
        $target_lang_file = $LANG_DIR . '/' . preg_replace('/[^a-z]/', '', $edit_code) . '.json';

        if (file_exists($target_lang_file) && isset($_POST['strings']) && is_array($_POST['strings'])) {
            $existing_data = json_decode(@file_get_contents($target_lang_file), true) ?: [];
            $meta = $existing_data['__meta'] ?? ['name' => strtoupper($edit_code), 'code' => $edit_code];

            $updated_strings = ['__meta' => $meta];
            foreach ($_POST['strings'] as $s_key => $s_val) {
                $s_key = trim($s_key);
                if ($s_key === '__meta') continue;
                $updated_strings[$s_key] = trim($s_val);
            }

            file_put_contents($target_lang_file, json_encode($updated_strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            $msg = "Translations for '" . htmlspecialchars($meta['name'] ?? $edit_code) . "' successfully updated.";
            $TRANSLATIONS = load_language_strings($current_lang);
        } else {
            $err = "Failed to update language strings.";
        }
    }

    // 4.6 Reset Security Locks
    if (isset($_POST['clear_security_locks'])) {
        verify_csrf();
        save_security_cache([]);
        $msg = "All IP lockouts and security frequency caches have been cleared.";
    }
}

// ==== UI RENDER FUNCTIONS ====

function render_setup_view($setup_error, $csrf_token, $available_langs, $current_lang) {
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(__('setup_title', 'ZeroVault Initial Setup'), ENT_QUOTES, 'UTF-8'); ?></title>
    <script>
    (function(){const t=localStorage.getItem('zerovault_theme');if(t==='light')document.documentElement.setAttribute('data-theme','light');})();
    </script>
    <?php render_styles(); ?>
</head>
<body class="setup-body">
    <div class="auth-card">
        <div style="display:flex; justify-content:flex-end; margin-bottom: 0.5rem;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleTheme()" title="<?php echo htmlspecialchars(__('theme_light'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="theme-sun"><?php echo render_icon('sun', 'icon-sm'); ?></span>
                <span class="theme-moon"><?php echo render_icon('moon', 'icon-sm'); ?></span>
            </button>
        </div>

        <div class="card-brand">
            <div class="brand-icon"><?php echo render_icon('vault', 'icon-lg'); ?></div>
            <h2><?php echo htmlspecialchars(__('setup_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="text-muted"><?php echo htmlspecialchars(__('setup_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <?php if ($setup_error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($setup_error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="form-group">
                <label><?php echo htmlspecialchars(__('system_title_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="app_name" value="ZeroVault | Secure File Delivery & Vault" required class="form-input">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label><?php echo htmlspecialchars(__('admin_username_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="text" name="username" placeholder="admin" required class="form-input" autofocus>
                </div>
                <div class="form-group">
                    <label><?php echo htmlspecialchars(__('default_lang_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                    <select name="default_lang" class="form-input">
                        <?php foreach ($available_langs as $code => $l): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($code === 'en') echo 'selected'; ?>>
                                <?php echo htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo strtoupper($code); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label><?php echo htmlspecialchars(__('new_password_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="password" name="password" placeholder="Min. 8 chars" required class="form-input">
                </div>
                <div class="form-group">
                    <label><?php echo htmlspecialchars(__('confirm_pass_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="password" name="password_confirm" placeholder="Confirm password" required class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label><?php echo htmlspecialchars(__('max_upload_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="number" name="max_upload_mb" value="100" min="5" max="2048" required class="form-input">
            </div>

            <button type="submit" name="run_setup" value="1" class="btn btn-primary btn-block">
                <?php echo render_icon('check', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_setup'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </form>
    </div>
    <script>
    function toggleTheme() {
        const cur = document.documentElement.getAttribute('data-theme') || 'dark';
        const next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('zerovault_theme', next);
    }
    </script>
</body>
</html>
<?php
}

function render_styles() {
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap');

    :root, [data-theme="dark"] {
        --bg-body: #090d16;
        --bg-surface: #111827;
        --bg-surface-elevated: #162032;
        --bg-surface-hover: #1e293b;
        --border-color: rgba(255, 255, 255, 0.08);
        --border-color-hover: rgba(255, 255, 255, 0.18);
        --primary: #3b82f6;
        --primary-hover: #2563eb;
        --accent: #10b981;
        --accent-hover: #059669;
        --danger: #f43f5e;
        --danger-hover: #e11d48;
        --warning: #f59e0b;
        --text-primary: #f8fafc;
        --text-secondary: #94a3b8;
        --text-muted: #64748b;
        --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.25);
        --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.4);
    }

    [data-theme="light"] {
        --bg-body: #f8fafc;
        --bg-surface: #ffffff;
        --bg-surface-elevated: #f1f5f9;
        --bg-surface-hover: #e2e8f0;
        --border-color: rgba(0, 0, 0, 0.1);
        --border-color-hover: rgba(0, 0, 0, 0.2);
        --primary: #2563eb;
        --primary-hover: #1d4ed8;
        --accent: #059669;
        --accent-hover: #047857;
        --danger: #e11d48;
        --danger-hover: #be123c;
        --warning: #d97706;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
        --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; transition: background-color 0.2s, border-color 0.2s; }

    body {
        background-color: var(--bg-body);
        color: var(--text-primary);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        line-height: 1.5;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 2.5rem 1rem;
        -webkit-font-smoothing: antialiased;
    }

    .app-container { width: 100%; max-width: 980px; }

    /* Top Bar */
    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 1rem;
    }

    .brand-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        color: var(--text-primary);
    }

    .brand-title h1 {
        font-size: 1.35rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .brand-badge {
        font-size: 0.72rem;
        font-family: 'JetBrains Mono', monospace;
        background: rgba(59, 130, 246, 0.12);
        color: var(--primary);
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        border: 1px solid rgba(59, 130, 246, 0.25);
        font-weight: 600;
    }

    /* Stats */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.25rem;
        box-shadow: var(--shadow-sm);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-icon.green { background: rgba(16, 185, 129, 0.1); color: var(--accent); }
    .stat-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

    .stat-meta h3 {
        font-size: 0.78rem;
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.25rem;
    }

    .stat-meta .stat-value {
        font-size: 1.35rem;
        font-weight: 700;
        font-family: 'JetBrains Mono', monospace;
    }

    /* Tabs */
    .tab-nav {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-surface);
        padding: 0.35rem;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        margin-bottom: 1.75rem;
        overflow-x: auto;
    }

    .tab-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: transparent;
        border: none;
        color: var(--text-secondary);
        padding: 0.65rem 1.15rem;
        font-size: 0.88rem;
        font-weight: 500;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .tab-btn:hover { color: var(--text-primary); background: var(--bg-surface-hover); }

    .tab-btn.active {
        background: var(--primary);
        color: #fff;
        font-weight: 600;
        box-shadow: 0 2px 10px rgba(59, 130, 246, 0.3);
    }

    /* Cards */
    .card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        margin-bottom: 2rem;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .card-header h2 {
        font-size: 1.15rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    /* Dropzone */
    .dropzone {
        border: 2px dashed var(--border-color-hover);
        border-radius: 12px;
        padding: 2.5rem 1.5rem;
        text-align: center;
        background: var(--bg-surface-elevated);
        cursor: pointer;
        margin-bottom: 1.5rem;
        transition: all 0.2s;
    }

    .dropzone:hover, .dropzone.dragover {
        border-color: var(--primary);
        background: rgba(59, 130, 246, 0.05);
    }

    .dropzone-icon { color: var(--primary); margin-bottom: 0.75rem; }
    .dropzone-title { font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem; }
    .dropzone-subtitle { font-size: 0.85rem; color: var(--text-muted); }

    .file-preview {
        display: none;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.9rem;
        color: var(--accent);
        margin-top: 0.75rem;
    }

    /* Forms */
    .form-group { margin-bottom: 1.25rem; }
    .form-group label {
        display: block;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
    }

    .form-input {
        width: 100%;
        background: var(--bg-surface-elevated);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        padding: 0.8rem 1rem;
        border-radius: 8px;
        font-family: inherit;
        font-size: 0.92rem;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.4rem;
        font-size: 0.88rem;
        font-weight: 600;
        border-radius: 8px;
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        font-family: inherit;
        transition: all 0.2s;
    }

    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-success { background: var(--accent); color: #fff; }
    .btn-success:hover { background: var(--accent-hover); }
    .btn-secondary { background: var(--bg-surface-hover); color: var(--text-primary); border-color: var(--border-color); }
    .btn-secondary:hover { border-color: var(--border-color-hover); }
    .btn-danger-outline { background: transparent; color: var(--danger); border-color: rgba(244, 63, 94, 0.3); }
    .btn-danger-outline:hover { background: rgba(244, 63, 94, 0.1); border-color: var(--danger); }
    .btn-sm { padding: 0.45rem 0.8rem; font-size: 0.8rem; }
    .btn-block { width: 100%; }

    /* Tables */
    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.88rem; }
    th {
        text-align: left;
        padding: 0.9rem 1rem;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--border-color);
    }
    td { padding: 1.1rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--bg-surface-hover); }

    .file-name-link { color: var(--text-primary); text-decoration: none; font-weight: 600; }
    .file-name-link:hover { color: var(--primary); }

    .file-subtext {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.76rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
        display: flex;
        gap: 0.6rem;
        align-items: center;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
    }
    .badge-green { background: rgba(16, 185, 129, 0.15); color: var(--accent); }
    .badge-amber { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
    .badge-blue { background: rgba(59, 130, 246, 0.15); color: var(--primary); }

    /* Alerts */
    .alert {
        padding: 1rem 1.25rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        font-size: 0.88rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); color: var(--accent); }
    .alert-danger { background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.25); color: var(--danger); }

    /* Auth Card */
    .auth-card {
        width: 100%;
        max-width: 460px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 2.5rem;
        box-shadow: var(--shadow-md);
        margin: auto;
    }
    .card-brand { text-align: center; margin-bottom: 2rem; }
    .card-brand .brand-icon {
        width: 56px;
        height: 56px;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary);
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }
    .card-brand h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 0.4rem; }

    /* Theme Toggle Display */
    [data-theme="dark"] .theme-sun { display: inline-flex; }
    [data-theme="dark"] .theme-moon { display: none; }
    [data-theme="light"] .theme-sun { display: none; }
    [data-theme="light"] .theme-moon { display: inline-flex; }

    .icon { width: 20px; height: 20px; display: inline-block; vertical-align: middle; flex-shrink: 0; }
    .icon-sm { width: 16px; height: 16px; }
    .icon-lg { width: 28px; height: 28px; }

    @media (max-width: 640px) {
        body { padding: 1rem 0.75rem; }
        .grid-2 { grid-template-columns: 1fr; }
        .card { padding: 1.25rem; }
        .auth-card { padding: 1.5rem; }
    }
</style>
<?php
}

// ==== MAIN HTML TEMPLATE ====
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?></title>
    <script>
    (function(){const t=localStorage.getItem('zerovault_theme');if(t==='light')document.documentElement.setAttribute('data-theme','light');})();
    </script>
    <?php render_styles(); ?>
</head>
<body>
    <div class="app-container">
        
        <!-- Top Navigation -->
        <header class="top-bar">
            <a href="<?php echo htmlspecialchars($home_url, ENT_QUOTES, 'UTF-8'); ?>" class="brand-title">
                <div style="color: var(--primary);"><?php echo render_icon('vault', 'icon-lg'); ?></div>
                <h1><?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if ($is_admin_route): ?>
                    <span class="brand-badge"><?php echo htmlspecialchars(__('admin_badge', 'ADMIN'), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </a>

            <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
                <!-- Language Selector Dropdown -->
                <div style="position: relative;">
                    <select onchange="window.location.href='?lang=' + this.value" class="btn btn-secondary btn-sm" style="padding-right: 1.8rem; cursor: pointer; appearance: auto;">
                        <?php foreach ($available_langs as $code => $l): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($code === $current_lang) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo strtoupper($code); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Light / Dark Theme Toggle Button -->
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleTheme()" title="<?php echo htmlspecialchars(__('theme_light'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="theme-sun"><?php echo render_icon('sun', 'icon-sm'); ?></span>
                    <span class="theme-moon"><?php echo render_icon('moon', 'icon-sm'); ?></span>
                </button>

                <?php if ($is_admin): ?>
                    <a href="<?php echo htmlspecialchars($home_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-secondary btn-sm">
                        <?php echo render_icon('external', 'icon-sm'); ?> <?php echo htmlspecialchars(__('public_view'), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($admin_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary btn-sm">
                        <?php echo render_icon('key', 'icon-sm'); ?> <?php echo htmlspecialchars(__('admin_login'), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Notification Alerts -->
        <?php if (!empty($msg)): ?>
            <div class="alert alert-success">
                <?php echo render_icon('check', 'icon-sm'); ?>
                <span><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($err) || !empty($login_error)): ?>
            <div class="alert alert-danger">
                <?php echo render_icon('shield', 'icon-sm'); ?>
                <span><?php echo htmlspecialchars($err ?? $login_error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($is_admin_route): ?>
            <?php if (!$is_admin): ?>
                <!-- ADMIN LOGIN CARD -->
                <div class="auth-card">
                    <div class="card-brand">
                        <div class="brand-icon"><?php echo render_icon('lock', 'icon-lg'); ?></div>
                        <h2><?php echo htmlspecialchars(__('login_card_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="text-muted"><?php echo htmlspecialchars(__('login_card_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        
                        <div class="form-group">
                            <label><?php echo htmlspecialchars(__('admin_username_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input type="text" name="username" required autofocus class="form-input" autocomplete="username">
                        </div>

                        <div class="form-group">
                            <label><?php echo htmlspecialchars(__('password_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input type="password" name="password" required class="form-input" autocomplete="current-password">
                        </div>

                        <button type="submit" name="login" class="btn btn-primary btn-block">
                            <?php echo render_icon('key', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_login'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- ADMIN DASHBOARD -->
                <?php
                $vault_size = formatBytes(get_dir_size($VAULT_DIR));
                $db_files = get_db();
                $active_files = count(array_filter($db_files, function($f) { return $f['is_active'] ?? true; }));
                $total_files = count($db_files);
                $total_downloads = array_sum(array_column($db_files, 'download_count'));
                ?>

                <!-- Stat Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><?php echo render_icon('vault'); ?></div>
                        <div class="stat-meta">
                            <h3><?php echo htmlspecialchars(__('stat_storage'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="stat-value"><?php echo $vault_size; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><?php echo render_icon('file'); ?></div>
                        <div class="stat-meta">
                            <h3><?php echo htmlspecialchars(__('stat_active_files'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="stat-value"><?php echo $active_files; ?> <span style="font-size:0.9rem; color:var(--text-muted);">/ <?php echo $total_files; ?></span></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><?php echo render_icon('download'); ?></div>
                        <div class="stat-meta">
                            <h3><?php echo htmlspecialchars(__('stat_total_downloads'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="stat-value"><?php echo $total_downloads; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <nav class="tab-nav">
                    <button type="button" class="tab-btn active" onclick="switchTab('upload')">
                        <?php echo render_icon('upload', 'icon-sm'); ?> <?php echo htmlspecialchars(__('tab_upload'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('files')">
                        <?php echo render_icon('file', 'icon-sm'); ?> <?php echo htmlspecialchars(__('tab_inventory'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo $total_files; ?>)
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('languages')">
                        <?php echo render_icon('globe', 'icon-sm'); ?> <?php echo htmlspecialchars(__('tab_languages'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('settings')">
                        <?php echo render_icon('settings', 'icon-sm'); ?> <?php echo htmlspecialchars(__('tab_settings'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <div style="margin-left: auto;">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" name="admin_logout" class="tab-btn" style="color: var(--danger);">
                                <?php echo render_icon('logout', 'icon-sm'); ?> <?php echo htmlspecialchars(__('logout'), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </form>
                    </div>
                </nav>

                <!-- TAB 1: UPLOAD -->
                <section id="tab-upload" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h2><?php echo render_icon('upload'); ?> <?php echo htmlspecialchars(__('upload_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <span class="badge badge-blue"><?php echo htmlspecialchars(__('max_limit'), ENT_QUOTES, 'UTF-8'); ?> <?php echo (int)($config['max_upload_mb'] ?? 100); ?> MB</span>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                            <!-- Drag & Drop Zone -->
                            <div class="dropzone" id="dropArea" onclick="document.getElementById('fileInput').click()">
                                <input type="file" name="file" id="fileInput" required style="display: none;" onchange="handleFileSelected(this)">
                                <div class="dropzone-icon"><?php echo render_icon('upload', 'icon-lg'); ?></div>
                                <div class="dropzone-title"><?php echo htmlspecialchars(__('dropzone_title'), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="dropzone-subtitle"><?php echo htmlspecialchars(__('dropzone_subtitle'), ENT_QUOTES, 'UTF-8'); ?> <?php echo implode(', ', $ALLOWED_EXTENSIONS); ?></div>
                                <div class="file-preview" id="filePreview">
                                    <?php echo render_icon('file', 'icon-sm'); ?> <span id="previewName"></span> (<span id="previewSize"></span>)
                                </div>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(__('display_name_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                    <input type="text" name="display_name" id="displayNameInput" placeholder="<?php echo htmlspecialchars(__('display_name_placeholder'), ENT_QUOTES, 'UTF-8'); ?>" class="form-input">
                                </div>

                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(__('password_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                    <input type="text" name="password" placeholder="<?php echo htmlspecialchars(__('password_hint'), ENT_QUOTES, 'UTF-8'); ?>" class="form-input">
                                </div>
                            </div>

                            <div class="form-group">
                                <label><?php echo htmlspecialchars(__('vt_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input type="text" name="virustotal_url" placeholder="https://www.virustotal.com/gui/file/..." class="form-input">
                            </div>

                            <button type="submit" name="upload" class="btn btn-success btn-block" style="padding: 0.9rem; font-size: 0.95rem;">
                                <?php echo render_icon('upload', 'icon-sm'); ?> <?php echo htmlspecialchars(__('upload_btn'), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </form>
                    </div>
                </section>

                <!-- TAB 2: INVENTORY -->
                <section id="tab-files" class="tab-content" style="display: none;">
                    <div class="card" style="padding: 1.5rem;">
                        <div class="card-header" style="margin-bottom: 1rem;">
                            <h2><?php echo render_icon('file'); ?> <?php echo htmlspecialchars(__('inventory_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div style="position: relative; width: 280px;">
                                <input type="text" id="fileSearch" placeholder="<?php echo htmlspecialchars(__('search_placeholder'), ENT_QUOTES, 'UTF-8'); ?>" class="form-input" style="padding-left: 2.3rem; font-size: 0.85rem;" onkeyup="filterFiles()">
                                <div style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">
                                    <?php echo render_icon('search', 'icon-sm'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="filesTable">
                                <thead>
                                    <tr>
                                        <th><?php echo htmlspecialchars(__('col_file'), ENT_QUOTES, 'UTF-8'); ?></th>
                                        <th><?php echo htmlspecialchars(__('col_size'), ENT_QUOTES, 'UTF-8'); ?></th>
                                        <th><?php echo htmlspecialchars(__('col_activity'), ENT_QUOTES, 'UTF-8'); ?></th>
                                        <th style="text-align: right;"><?php echo htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($db_files) as $f): 
                                        $is_file_active = $f['is_active'] ?? true;
                                        $last_dl = $f['last_downloaded'] ?? null;
                                        $file_link = $base_url . '/?file=' . $f['id'];
                                    ?>
                                    <tr class="file-row">
                                        <td>
                                            <a href="<?php echo htmlspecialchars($file_link, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="file-name-link">
                                                <?php echo htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                            <div class="file-subtext">
                                                <span>ID: <?php echo substr($f['id'], 0, 10); ?>...</span>
                                                <?php if (!empty($f['password'])): ?>
                                                    <span class="badge badge-amber"><?php echo render_icon('lock', 'icon-sm'); ?> <?php echo htmlspecialchars(__('badge_encrypted'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                                <?php if ($is_file_active): ?>
                                                    <span class="badge badge-green"><?php echo htmlspecialchars(__('badge_active'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-amber"><?php echo htmlspecialchars(__('badge_suspended'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">
                                            <?php echo formatBytes($f['size'] ?? 0); ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; font-family: 'JetBrains Mono', monospace;"><?php echo (int)($f['download_count'] ?? 0); ?> dl</div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo time_elapsed_string($last_dl); ?></div>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 0.4rem;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="copyShareLink('<?php echo htmlspecialchars($file_link, ENT_QUOTES, 'UTF-8'); ?>', this)" title="<?php echo htmlspecialchars(__('btn_copy'), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo render_icon('copy', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_copy'), ENT_QUOTES, 'UTF-8'); ?>
                                                </button>

                                                <form method="POST" style="display:inline; margin:0;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="toggle_id" value="<?php echo htmlspecialchars($f['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php if ($is_file_active): ?>
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="<?php echo htmlspecialchars(__('btn_pause'), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo render_icon('pause', 'icon-sm'); ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="<?php echo htmlspecialchars(__('btn_activate'), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo render_icon('play', 'icon-sm'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </form>

                                                <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('<?php echo htmlspecialchars(__('confirm_delete'), ENT_QUOTES, 'UTF-8'); ?>');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($f['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="btn btn-danger-outline btn-sm" title="<?php echo htmlspecialchars(__('btn_delete'), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo render_icon('trash', 'icon-sm'); ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($db_files)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                                                <?php echo htmlspecialchars(__('no_files'), ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- TAB 3: LANGUAGES -->
                <section id="tab-languages" class="tab-content" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h2><?php echo render_icon('globe'); ?> <?php echo htmlspecialchars(__('lang_mgmt_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.5rem;">
                            <?php echo htmlspecialchars(__('lang_mgmt_desc'), ENT_QUOTES, 'UTF-8'); ?>
                        </p>

                        <!-- Installed Languages Table -->
                        <div class="table-responsive" style="margin-bottom: 2rem;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>CODE</th>
                                        <th>LANGUAGE NAME</th>
                                        <th>STATUS</th>
                                        <th style="text-align: right;">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_langs as $code => $l): ?>
                                    <tr>
                                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 600;">
                                            <?php echo strtoupper($code); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($code === ($config['default_lang'] ?? 'en')): ?>
                                                <span class="badge badge-green">Default</span>
                                            <?php endif; ?>
                                            <?php if ($code === $current_lang): ?>
                                                <span class="badge badge-blue">Active in View</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 0.5rem;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="openLangEditor('<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>')">
                                                    <?php echo render_icon('edit', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_edit_translations'), ENT_QUOTES, 'UTF-8'); ?>
                                                </button>

                                                <?php if ($code !== 'en' && $code !== ($config['default_lang'] ?? 'en')): ?>
                                                    <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this language pack?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="del_lang_code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" name="delete_language" class="btn btn-danger-outline btn-sm">
                                                            <?php echo render_icon('trash', 'icon-sm'); ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Add New Language Form -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                            <h3 style="font-size: 1rem; margin-bottom: 1rem; font-weight: 600;">
                                <?php echo render_icon('language', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_add_language'), ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="grid-2">
                                    <div class="form-group">
                                        <label><?php echo htmlspecialchars(__('lang_code_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                        <input type="text" name="new_lang_code" placeholder="e.g. de, fr, es, ru" pattern="[a-z]{2,5}" maxlength="5" required class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label><?php echo htmlspecialchars(__('lang_name_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                        <input type="text" name="new_lang_name" placeholder="e.g. Deutsch" required class="form-input">
                                    </div>
                                </div>
                                <button type="submit" name="add_language" class="btn btn-primary">
                                    <?php echo render_icon('check', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_add_language'), ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Translation Editor Modal / Drawer -->
                    <div id="langEditorModal" class="card" style="display: none; border-color: var(--primary);">
                        <div class="card-header">
                            <h2><?php echo render_icon('edit'); ?> <span id="editorLangTitle">Edit Translations</span></h2>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="closeLangEditor()">Close</button>
                        </div>
                        <form method="POST" id="langStringsForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="edit_lang_code" id="editLangCodeHidden" value="">
                            
                            <div id="stringsContainer" style="max-height: 480px; overflow-y: auto; padding-right: 0.5rem; margin-bottom: 1.5rem;">
                                <!-- Dynamic Fields populated by JS -->
                            </div>

                            <button type="submit" name="save_language_strings" class="btn btn-success">
                                <?php echo render_icon('check', 'icon-sm'); ?> Save Translations
                            </button>
                        </form>
                    </div>
                </section>

                <!-- TAB 4: SETTINGS -->
                <section id="tab-settings" class="tab-content" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h2><?php echo render_icon('settings'); ?> <?php echo htmlspecialchars(__('settings_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="grid-2">
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(__('system_title_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                    <input type="text" name="app_name" value="<?php echo htmlspecialchars($config['app_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="form-input">
                                </div>

                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(__('admin_username_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                    <input type="text" name="admin_username" value="<?php echo htmlspecialchars($config['admin_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="form-input">
                                </div>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(__('max_upload_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                    <input type="number" name="max_upload_mb" value="<?php echo (int)($config['max_upload_mb'] ?? 100); ?>" min="1" max="2048" required class="form-input">
                                </div>

                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(__('default_lang_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                    <select name="default_lang" class="form-input">
                                        <?php foreach ($available_langs as $code => $l): ?>
                                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($code === ($config['default_lang'] ?? 'en')) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo strtoupper($code); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div style="border-top: 1px solid var(--border-color); margin: 1.5rem 0; padding-top: 1.5rem;">
                                <h3 style="font-size: 0.95rem; margin-bottom: 1rem; color: var(--text-primary); font-weight: 600;">
                                    <?php echo render_icon('lock', 'icon-sm'); ?> <?php echo htmlspecialchars(__('password_change_title'), ENT_QUOTES, 'UTF-8'); ?>
                                </h3>

                                <div class="grid-2">
                                    <div class="form-group">
                                        <label><?php echo htmlspecialchars(__('new_password_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                        <input type="password" name="new_password" placeholder="Min. 8 characters" class="form-input">
                                    </div>

                                    <div class="form-group">
                                        <label><?php echo htmlspecialchars(__('current_password_label'), ENT_QUOTES, 'UTF-8'); ?> <span style="color:var(--danger)">*</span></label>
                                        <input type="password" name="current_password" required class="form-input">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <?php echo render_icon('check', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_save_settings'), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </form>

                        <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem;">
                            <h3 style="font-size: 0.95rem; margin-bottom: 0.4rem; color: var(--danger); font-weight: 600;">
                                <?php echo htmlspecialchars(__('security_locks_title'), ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                                <?php echo htmlspecialchars(__('security_locks_desc'), ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" name="clear_security_locks" class="btn btn-danger-outline btn-sm">
                                    <?php echo htmlspecialchars(__('btn_clear_locks'), ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

            <?php endif; ?>

        <?php elseif (isset($_GET['file'])): ?>
            <!-- PUBLIC DOWNLOAD / PASSWORD GATE PAGE -->
            <?php
            $file_id = trim($_GET['file']);
            $db = get_db();
            $target_file = null;

            if (preg_match('/^[a-f0-9]{64}$/', $file_id)) {
                foreach ($db as $f) {
                    if ($f['id'] === $file_id) {
                        $target_file = $f;
                        break;
                    }
                }
            }

            if ($target_file):
                $is_file_active = $target_file['is_active'] ?? true;
                $file_password = $target_file['password'] ?? '';

                if (!$is_file_active):
            ?>
                <div class="auth-card" style="text-align: center; border-color: var(--danger);">
                    <div style="color: var(--danger); margin-bottom: 1rem;"><?php echo render_icon('pause', 'icon-lg'); ?></div>
                    <h2><?php echo htmlspecialchars(__('file_suspended_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="text-muted" style="margin-top: 0.5rem;"><?php echo htmlspecialchars(__('file_suspended_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php else:
                $authorized = empty($file_password);
                $auth_error = null;

                if (!empty($file_password)) {
                    $auth_key = 'auth_' . $target_file['id'];
                    $ip_key = 'fauth_' . substr($target_file['id'], 0, 16);

                    if (isset($_POST['auth_password'])) {
                        verify_csrf();
                        $lock = check_ip_lock($ip_key, 3, 120);

                        if ($lock['locked']) {
                            $auth_error = "Too many attempts. Locked for " . $lock['remaining'] . " seconds.";
                        } elseif (password_verify($_POST['auth_password'], $file_password)) {
                            $_SESSION[$auth_key] = true;
                            record_ip_attempt($ip_key, true);
                            $authorized = true;
                        } else {
                            record_ip_attempt($ip_key, false, 3, 120);
                            $new_lock = check_ip_lock($ip_key, 3, 120);
                            if ($new_lock['locked']) {
                                $auth_error = "Too many failed attempts. Locked for 2 minutes.";
                            } else {
                                $rem = max(0, 3 - $new_lock['attempts']);
                                $auth_error = "Invalid passcode. Remaining: " . $rem;
                            }
                        }
                    }
                    $authorized = !empty($_SESSION[$auth_key]);
                }

                if (!$authorized):
            ?>
                <!-- SECRET CODE PROMPT -->
                <div class="auth-card">
                    <div class="card-brand">
                        <div class="brand-icon"><?php echo render_icon('lock', 'icon-lg'); ?></div>
                        <h2><?php echo htmlspecialchars(__('file_locked_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="text-muted"><?php echo htmlspecialchars(__('file_locked_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <?php if ($auth_error): ?>
                        <div class="alert alert-danger" style="padding: 0.75rem 1rem; font-size: 0.85rem;">
                            <?php echo htmlspecialchars($auth_error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <input type="password" name="auth_password" required autofocus placeholder="••••••••" class="form-input" style="text-align: center; letter-spacing: 0.15em; font-size: 1.15rem;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <?php echo render_icon('key', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_unlock'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- FILE READY FOR DOWNLOAD -->
                <div class="card" style="max-width: 580px; margin: 2rem auto; text-align: center; padding: 3rem 2.5rem;">
                    <div style="width: 72px; height: 72px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                        <?php echo render_icon('file', 'icon-lg'); ?>
                    </div>

                    <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 0.5rem; word-break: break-word;">
                        <?php echo htmlspecialchars($target_file['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </h2>

                    <div style="display: flex; justify-content: center; gap: 1rem; color: var(--text-muted); font-size: 0.88rem; margin-bottom: 2rem; font-family: 'JetBrains Mono', monospace; flex-wrap: wrap;">
                        <span><?php echo formatBytes($target_file['size'] ?? 0); ?></span>
                        <span>•</span>
                        <span><?php echo htmlspecialchars($target_file['upload_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>•</span>
                        <span><?php echo (int)($target_file['download_count'] ?? 0); ?> <?php echo htmlspecialchars(__('stat_total_downloads'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div style="display: grid; gap: 0.85rem;">
                        <a href="?download=<?php echo urlencode($target_file['id']); ?>" class="btn btn-success" style="padding: 1.1rem; font-size: 1.05rem;">
                            <?php echo render_icon('download', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_download'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>

                        <?php if (!empty($target_file['virustotal_url'])): ?>
                            <a href="<?php echo htmlspecialchars($target_file['virustotal_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                                <?php echo render_icon('shield', 'icon-sm'); ?> <?php echo htmlspecialchars(__('btn_vt_report'), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            <?php 
                endif; 
            endif;
            ?>
            <?php else: ?>
                <div class="auth-card" style="text-align: center;">
                    <div style="color: var(--danger); margin-bottom: 1rem;"><?php echo render_icon('shield', 'icon-lg'); ?></div>
                    <h2><?php echo htmlspecialchars(__('file_not_found_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="text-muted" style="margin-top: 0.5rem;"><?php echo htmlspecialchars(__('file_not_found_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- PUBLIC HOMEPAGE -->
            <div class="card" style="text-align: center; padding: 4.5rem 2rem; max-width: 600px; margin: 3rem auto;">
                <div style="width: 64px; height: 64px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                    <?php echo render_icon('vault', 'icon-lg'); ?>
                </div>
                <h2 style="font-size: 1.5rem; margin-bottom: 0.75rem; font-weight: 700;">
                    <?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?>
                </h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 2rem;">
                    <?php echo htmlspecialchars(__('public_home_desc'), ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <div style="display: inline-flex; gap: 0.75rem;">
                    <a href="<?php echo htmlspecialchars($admin_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
                        <?php echo render_icon('key', 'icon-sm'); ?> <?php echo htmlspecialchars(__('admin_login'), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Client-Side Interactions -->
    <script>
    // Theme Switcher
    function toggleTheme() {
        const cur = document.documentElement.getAttribute('data-theme') || 'dark';
        const next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('zerovault_theme', next);
    }

    // Link Copy
    function copyShareLink(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<?php echo render_icon("check", "icon-sm"); ?> <?php echo htmlspecialchars(__('btn_copied'), ENT_QUOTES, 'UTF-8'); ?>';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-secondary');

            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-secondary');
            }, 2000);
        }).catch(() => {
            prompt("Share link:", text);
        });
    }

    // Tab Switching
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        const target = document.getElementById('tab-' + tabName);
        if (target) target.style.display = 'block';

        const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(b => 
            b.getAttribute('onclick') && b.getAttribute('onclick').includes(tabName)
        );
        if (activeBtn) activeBtn.classList.add('active');
    }

    // Filter Files
    function filterFiles() {
        const input = document.getElementById('fileSearch');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('#filesTable tbody tr.file-row');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }

    // Language Editor
    const ALL_LANGUAGES_DATA = <?php 
        $all_data = [];
        foreach ($available_langs as $c => $l) {
            $all_data[$c] = load_language_strings($c);
        }
        echo json_encode($all_data, JSON_UNESCAPED_UNICODE);
    ?>;

    function openLangEditor(code) {
        const modal = document.getElementById('langEditorModal');
        const title = document.getElementById('editorLangTitle');
        const hidden = document.getElementById('editLangCodeHidden');
        const container = document.getElementById('stringsContainer');
        
        hidden.value = code;
        const langData = ALL_LANGUAGES_DATA[code] || {};
        const langName = (langData.__meta && langData.__meta.name) ? langData.__meta.name : code.toUpperCase();
        title.innerText = 'Edit ' + langName + ' (' + code.toUpperCase() + ') Translations';

        container.innerHTML = '';
        const enData = ALL_LANGUAGES_DATA['en'] || {};

        // Merge all keys from en as baseline
        const keys = Object.keys(enData).filter(k => k !== '__meta');

        keys.forEach(key => {
            const row = document.createElement('div');
            row.className = 'form-group';
            row.style.marginBottom = '0.85rem';

            const label = document.createElement('label');
            label.style.fontFamily = 'JetBrains Mono, monospace';
            label.style.fontSize = '0.78rem';
            label.innerText = key;

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'strings[' + key + ']';
            input.value = langData[key] !== undefined ? langData[key] : (enData[key] || '');
            input.className = 'form-input';

            row.appendChild(label);
            row.appendChild(input);
            container.appendChild(row);
        });

        modal.style.display = 'block';
        modal.scrollIntoView({ behavior: 'smooth' });
    }

    function closeLangEditor() {
        const modal = document.getElementById('langEditorModal');
        if (modal) modal.style.display = 'none';
    }

    // Drag and Drop Zone
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');

    if (dropArea && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropArea.classList.remove('dragover');
            }, false);
        });

        dropArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelected(fileInput);
            }
        });
    }

    function handleFileSelected(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const preview = document.getElementById('filePreview');
            const previewName = document.getElementById('previewName');
            const previewSize = document.getElementById('previewSize');
            const displayName = document.getElementById('displayNameInput');

            previewName.innerText = file.name;
            previewSize.innerText = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            preview.style.display = 'flex';

            if (displayName && !displayName.value) {
                displayName.value = file.name;
            }
        }
    }
    </script>
</body>
</html>
