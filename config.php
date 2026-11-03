<?php
// Configuration for Astrologer Admin Panel
error_reporting(E_ALL);
ini_set('display_errors', '0');

define('DB_HOST', 'localhost');
define('DB_NAME', 'astro_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

// Admin credentials
if (!defined('ADMIN_USER')) {
    define('ADMIN_USER', 'admin');
}
if (!defined('ADMIN_PASS_HASH')) {
    define('ADMIN_PASS_HASH', password_hash('astro2025', PASSWORD_BCRYPT));
}

// Uploads directory
define('UPLOAD_DIR', __DIR__ . '/uploads/payments/');

/**
 * Universal Database Connection Helper:
 * Attempts MySQL first; if unavailable, seamlessly falls back to local SQLite.
 */
function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // 1. Try MySQL first if accessible
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (Throwable $e) {
        // MySQL server not available -> fallback to SQLite
    }

    // 2. Check if main chatbot sqlite database exists nearby
    $candidates = [
        __DIR__ . '/../astro-chatbot/data/astro.sqlite',
        __DIR__ . '/data/astro.sqlite',
        __DIR__ . '/astro.sqlite'
    ];

    $sqlitePath = null;
    foreach ($candidates as $cand) {
        if (file_exists($cand)) {
            $sqlitePath = $cand;
            break;
        }
    }

    if (!$sqlitePath) {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        $sqlitePath = $dataDir . '/astro.sqlite';
    }

    $isNew = !file_exists($sqlitePath) || filesize($sqlitePath) < 50;
    $pdo = new PDO("sqlite:" . $sqlitePath, null, null, $options);
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA foreign_keys = ON;");

    if ($isNew) {
        initAdminSqliteTables($pdo);
    }

    return $pdo;
}

function initAdminSqliteTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT NOT NULL,
            email TEXT,
            dob TEXT NOT NULL,
            tob TEXT NOT NULL,
            pob TEXT NOT NULL,
            lat REAL,
            lon REAL,
            slot_date TEXT NOT NULL,
            slot_time TEXT NOT NULL,
            question TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            chart_json TEXT,
            report_text TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS slot_locks (
            slot_date TEXT NOT NULL,
            slot_time TEXT NOT NULL,
            booking_id INTEGER NOT NULL,
            locked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (slot_date, slot_time)
        );

        CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id INTEGER NOT NULL,
            screenshot_path TEXT,
            amount REAL NOT NULL DEFAULT 500.00,
            upi_ref TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME,
            notes TEXT
        );

        CREATE INDEX IF NOT EXISTS idx_b_slot ON bookings (slot_date, slot_time);
        CREATE INDEX IF NOT EXISTS idx_b_status ON bookings (status);
        CREATE INDEX IF NOT EXISTS idx_b_phone ON bookings (phone);
    ");
}

