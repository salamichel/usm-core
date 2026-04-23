<?php
declare(strict_types=1);

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST',     getenv('DB_HOST')   ?: 'db');
define('DB_NAME',     getenv('DB_NAME')   ?: 'usm_volley');
define('DB_USER',     getenv('DB_USER')   ?: 'usm');
define('DB_PASS',     getenv('DB_PASS')   ?: 'usm_password');
define('DB_CHARSET',  'utf8mb4');

// ── Admin credentials ─────────────────────────────────────────────────────────
// Generate a new hash: password_hash('your_password', PASSWORD_BCRYPT)
define('ADMIN_EMAIL',         getenv('ADMIN_EMAIL')         ?: 'admin@usm-volley.fr');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2y$10$afFrFTWviOiBaVpcqoKfJ.ovXqF1rlTCFvv3ldRQcBdUCjpgrMEz2');

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

// ── Upload ────────────────────────────────────────────────────────────────────
define('UPLOAD_DIR',      ROOT . '/public/assets/uploads');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
define('UPLOAD_ALLOWED_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
