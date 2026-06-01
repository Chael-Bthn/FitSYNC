<?php
// ============================================================
//  FitSync — Database Configuration
//  /config/db.php
//
//  DO NOT commit this file to version control.
//  Add  /config/db.php  to your .gitignore
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'fitsync');
define('DB_USER',    'root');       // ← change to your DB user
define('DB_PASS',    '');           // ← change to your DB password
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO connection (singleton).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Schema migration: Add is_approved column if it doesn't exist
            if (!isset($_SERVER['_fitsync_schema_migrated'])) {
                try {
                    $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_approved'");
                    if ($checkCol->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN is_approved TINYINT(1) DEFAULT 1");
                    }
                    $_SERVER['_fitsync_schema_migrated'] = true;
                } catch (Exception) {
                    // Column likely already exists or other issue — continue anyway
                }
            }
        } catch (PDOException $e) {
            // Never expose DB errors in production — log and show a safe message
            error_log('[FitSync DB] Connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed. Please try again later.']));
        }
    }

    return $pdo;
}