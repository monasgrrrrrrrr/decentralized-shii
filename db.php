<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // ── Auto-migrate on first boot ───────────────────────────────────────
            // If the `users` table doesn't exist yet this is a fresh database
            // (e.g. a brand-new Railway deploy). Run schema.sql automatically so
            // no manual phpMyAdmin step is ever needed.
            $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
            if (empty($tables)) {
                $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
                // Strip -- comments and split on semicolons so each statement
                // runs individually (PDO::exec can't handle multi-statement strings
                // reliably across all MySQL drivers).
                $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    $pdo->exec($stmt);
                }
            }
            // ─────────────────────────────────────────────────────────────────────
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #eee;border-radius:8px">'
                . '<h2 style="color:#0f172a">Database connection failed</h2>'
                . '<p>Please check your DB credentials in <code>config.php</code> (or your Railway environment variables).</p>'
                . '<p style="color:#888;font-size:13px">' . htmlspecialchars($e->getMessage()) . '</p></div>');
        }
    }
    return $pdo;
}
