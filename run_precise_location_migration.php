<?php
/**
 * Adds request_precise_location on links and optional geo columns on clicks_log.
 * Safe to run multiple times (checks information_schema).
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function migrationColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    if (!migrationColumnExists($pdo, 'links', 'request_precise_location')) {
        $pdo->exec(
            'ALTER TABLE links ADD COLUMN request_precise_location TINYINT(1) NOT NULL DEFAULT 0'
        );
        echo "Added links.request_precise_location\n";
    } else {
        echo "Skip links.request_precise_location (exists)\n";
    }

    if (!migrationColumnExists($pdo, 'clicks_log', 'geo_lat')) {
        $pdo->exec(
            'ALTER TABLE clicks_log ADD COLUMN geo_lat DECIMAL(10,7) NULL DEFAULT NULL'
        );
        echo "Added clicks_log.geo_lat\n";
    } else {
        echo "Skip clicks_log.geo_lat (exists)\n";
    }

    if (!migrationColumnExists($pdo, 'clicks_log', 'geo_lng')) {
        $pdo->exec(
            'ALTER TABLE clicks_log ADD COLUMN geo_lng DECIMAL(10,7) NULL DEFAULT NULL'
        );
        echo "Added clicks_log.geo_lng\n";
    } else {
        echo "Skip clicks_log.geo_lng (exists)\n";
    }

    if (!migrationColumnExists($pdo, 'clicks_log', 'geo_accuracy_m')) {
        $pdo->exec(
            'ALTER TABLE clicks_log ADD COLUMN geo_accuracy_m DECIMAL(12,4) NULL DEFAULT NULL'
        );
        echo "Added clicks_log.geo_accuracy_m\n";
    } else {
        echo "Skip clicks_log.geo_accuracy_m (exists)\n";
    }

    if (!migrationColumnExists($pdo, 'clicks_log', 'geo_source')) {
        $pdo->exec(
            "ALTER TABLE clicks_log ADD COLUMN geo_source VARCHAR(32) NULL DEFAULT NULL"
        );
        echo "Added clicks_log.geo_source\n";
    } else {
        echo "Skip clicks_log.geo_source (exists)\n";
    }

    if (!migrationColumnExists($pdo, 'clicks_log', 'geo_neighborhood')) {
        $pdo->exec(
            'ALTER TABLE clicks_log ADD COLUMN geo_neighborhood VARCHAR(128) NULL DEFAULT NULL'
        );
        echo "Added clicks_log.geo_neighborhood\n";
    } else {
        echo "Skip clicks_log.geo_neighborhood (exists)\n";
    }

    echo "Done.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
