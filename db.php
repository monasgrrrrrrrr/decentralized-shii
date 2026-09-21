<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // ── Step 1: Connect (separate try/catch so errors are accurate) ────
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #eee;border-radius:8px">'
                . '<h2 style="color:#0f172a">Database connection failed</h2>'
                . '<p>Please check your DB credentials in <code>config.php</code> (or your Railway environment variables).</p>'
                . '<p style="color:#888;font-size:13px">' . htmlspecialchars($e->getMessage()) . '</p></div>');
        }

        // ── Step 2: Auto-migrate on first boot (own try/catch — never block login) ──
        try {
            $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
            if (empty($tables)) {
                $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
                $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $stmtErr) {
                        error_log('[db auto-migrate] Statement skipped: ' . $stmtErr->getMessage() . ' | SQL: ' . substr($stmt, 0, 200));
                    }
                }
            }

            // Also ensure v10 columns exist the first time db() is called, so
            // manual migration isn't required after deploy.
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM wallet_connections LIKE 'email'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE wallet_connections ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER label");
                }
                $cols2 = $pdo->query("SHOW COLUMNS FROM wallet_connections LIKE 'image_path'")->fetchAll();
                if (empty($cols2)) {
                    $pdo->exec("ALTER TABLE wallet_connections ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER email");
                }
            } catch (Throwable $e) {
                // Columns may already exist or table may not yet exist — non-fatal
            }
        } catch (Throwable $e) {
            // Auto-migration errors must NEVER break the site or make login
            // look like a "connection failure". Log & fall through.
            error_log('[db auto-migrate] ' . $e->getMessage());
        }
    }
    return $pdo;
}
