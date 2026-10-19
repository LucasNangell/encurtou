<?php
require 'db.php';
$stmt = $pdo->query("SELECT * FROM plans");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "CONTEUDO DA TABELA PLANS:\n";
foreach ($plans as $p) {
    echo "ID: " . $p['id'] . " | Nome: " . $p['name'] . "\n";
    echo "Features: " . $p['features_json'] . "\n";
    echo "---------------------------------\n";
}

$stmt = $pdo->query("SELECT id, name, plan, current_plan_id FROM users WHERE id IN (SELECT DISTINCT user_id FROM payments_history)");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nUSUARIOS QUE PAGARAM:\n";
foreach ($users as $u) {
    echo "User ID: " . $u['id'] . " | Name: " . $u['name'] . " | Plan (Enum): " . $u['plan'] . " | Current Plan ID: " . $u['current_plan_id'] . "\n";
}
?>
