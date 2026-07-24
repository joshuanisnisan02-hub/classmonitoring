<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Database Error</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f6f8fb;padding:40px;color:#13233a}.box{max-width:850px;margin:auto;background:white;border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.08)}code{background:#eef2f7;padding:2px 6px;border-radius:6px}</style></head><body><div class="box">';
        echo '<h2>Database connection failed</h2>';
        echo '<p>Please create/import the database first, then check <code>includes/config.php</code>.</p>';
        echo '<p><b>Suggested fix:</b> open phpMyAdmin and import <code>database/install.sql</code>, or create database <code>' . htmlspecialchars(DB_NAME) . '</code> then open <code>setup.php</code>.</p>';
        echo '<p><b>Technical message:</b> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div></body></html>';
        exit;
    }
}
