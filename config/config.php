<?php
declare(strict_types=1);

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST',     getenv('DB_HOST')   ?: 'db');
define('DB_NAME',     getenv('DB_NAME')   ?: 'usm_volley');
define('DB_USER',     getenv('DB_USER')   ?: 'usm');
define('DB_PASS',     getenv('DB_PASS')   ?: 'usm_password');
define('DB_CHARSET',  'utf8mb4');

// ── External Database (base USM — simulée en dev via db_external) ─────────────
define('EXT_DB_HOST', getenv('EXT_DB_HOST') ?: 'db_external');
define('EXT_DB_NAME', getenv('EXT_DB_NAME') ?: 'usm_external');
define('EXT_DB_USER', getenv('EXT_DB_USER') ?: 'usm_ext');
define('EXT_DB_PASS', getenv('EXT_DB_PASS') ?: 'usm_ext_password');

// ── Admin credentials ─────────────────────────────────────────────────────────
// Generate a new hash: password_hash('your_password', PASSWORD_BCRYPT)
define('ADMIN_EMAIL',         getenv('ADMIN_EMAIL')         ?: 'admin@usm-volley.fr');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2y$10$uVxk4vTrkDTHilRZXcoJaOzdifgkr8Y.dTm2WEMPlPpJnzQkYXZtG');

// ── App ────────────────────────────────────────────────────────────────────────
if (getenv('BASE_URL')) {
    define('BASE_URL', rtrim(getenv('BASE_URL'), '/'));
} else {
    // Auto-detect: works on InfinityFree and any shared hosting without env vars
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $proto . '://' . $host);
}
define('THEME',     getenv('THEME') ?: 'front001');
define('APP_DEBUG', (bool)(getenv('APP_DEBUG') ?: true));

// ── Agenda & Events ────────────────────────────────────────────────────────────
define('MINI_AGENDA_LIMIT', (int)(getenv('MINI_AGENDA_LIMIT') ?: 5));

// ── Brevo (Email service) ─────────────────────────────────────────────────────
define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?: '');
define('BREVO_FROM_EMAIL', getenv('BREVO_FROM_EMAIL') ?: 'noreply@usm-volley.fr');
define('BREVO_FROM_NAME', getenv('BREVO_FROM_NAME') ?: 'USM Volley');

// ── Google Analytics ──────────────────────────────────────────────────────────
define('GA_MEASUREMENT_ID', getenv('GA_MEASUREMENT_ID') ?: '');
define('GA_PROPERTY_ID', getenv('GA_PROPERTY_ID') ?: '');
define('GA_CREDENTIALS_PATH', getenv('GA_CREDENTIALS_PATH') ?: '');

// ── Upload ────────────────────────────────────────────────────────────────────
define('UPLOAD_DIR',      ROOT . '/public/assets/uploads');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
define('UPLOAD_ALLOWED_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
