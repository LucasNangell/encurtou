<?php
/**
 * CLI ou browser (logado local): cria tabelas bio_form_* se não existirem.
 */
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
require_once __DIR__ . '/db.php';

$sqlFile = __DIR__ . '/bio_form_migration.sql';
if (!is_readable($sqlFile)) {
    echo "Arquivo bio_form_migration.sql não encontrado.\n";
    exit(1);
}

$raw = file_get_contents($sqlFile);
$raw = preg_replace('/--.*$/m', '', $raw);
$parts = array_filter(array_map('trim', preg_split('/;\s*\n/', $raw)));

foreach ($parts as $stmt) {
    if ($stmt === '') {
        continue;
    }
    try {
        $pdo->exec($stmt);
        echo "OK: " . substr($stmt, 0, 60) . "...\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "Já existe (ignorado): " . $e->getMessage() . "\n";
        } else {
            echo "Erro: " . $e->getMessage() . "\n";
        }
    }
}
echo "Concluído.\n";
