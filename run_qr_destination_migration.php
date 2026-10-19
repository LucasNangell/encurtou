<?php
/**
 * Executa a migração para habilitar destinos dinâmicos e editáveis de QR Codes.
 * Acesso restrito: CLI ou localhost.
 */
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
require_once 'db.php';

if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Este script só pode ser executado localmente ou via CLI.');
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $cols = $pdo->query("SHOW COLUMNS FROM `links`")->fetchAll(PDO::FETCH_COLUMN);
    $needsType = !in_array('destination_type', $cols, true);
    $needsMeta = !in_array('destination_metadata', $cols, true);

    if ($needsType) {
        $pdo->exec("ALTER TABLE `links` ADD COLUMN `destination_type` VARCHAR(30) DEFAULT NULL AFTER `type`");
        echo "[OK] Coluna destination_type criada.\n";
    } else {
        echo "[SKIP] Coluna destination_type já existe.\n";
    }

    if ($needsMeta) {
        $pdo->exec("ALTER TABLE `links` ADD COLUMN `destination_metadata` JSON DEFAULT NULL AFTER `destination_type`");
        echo "[OK] Coluna destination_metadata criada.\n";
    } else {
        echo "[SKIP] Coluna destination_metadata já existe.\n";
    }

    $updated = $pdo->exec("UPDATE `links` SET `destination_type` = 'legacy' WHERE `type` = 'qr' AND `destination_type` IS NULL");
    echo "[OK] Backfill aplicado em {$updated} QR Codes antigos (destination_type=legacy).\n";

    echo "\nMigração concluída com sucesso.\n";
} catch (PDOException $e) {
    echo "[ERRO] " . $e->getMessage() . "\n";
    exit(1);
}
