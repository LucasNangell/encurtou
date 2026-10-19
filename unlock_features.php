<?php
require 'db.php';
try {
    // Plano Creator - ID 2
    $featCreator = json_encode([
        'analytics' => true,
        'custom_domain' => true,
        'bio_pages' => true,
        'custom_path' => true,
        'tracking' => true,
        'redirect' => true // Adicionado para garantir o funcionamento base
    ]);

    // Plano Pro - ID 3
    $featPro = json_encode([
        'analytics' => true,
        'custom_domain' => true,
        'bio_pages' => true,
        'custom_path' => true,
        'tracking' => true,
        'redirect' => true,
        'deep_linking' => true,
        'bulk_utm' => true,
        'ab_testing' => true,
        'rules' => true,
        'team' => true
    ]);

    // Plano Business - ID 4
    $featBusiness = json_encode([
        'analytics' => true,
        'custom_domain' => true,
        'bio_pages' => true,
        'custom_path' => true,
        'tracking' => true,
        'redirect' => true,
        'deep_linking' => true,
        'bulk_utm' => true,
        'ab_testing' => true,
        'rules' => true,
        'team' => true
    ]);

    $pdo->prepare("UPDATE plans SET features_json = ? WHERE id = 2")->execute([$featCreator]);
    $pdo->prepare("UPDATE plans SET features_json = ? WHERE id = 3")->execute([$featPro]);
    $pdo->prepare("UPDATE plans SET features_json = ? WHERE id = 4")->execute([$featBusiness]);

    echo "Sucesso: Matriz de funcionalidades atualizada nos planos pagos.\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
