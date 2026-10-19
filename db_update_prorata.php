<?php
require 'db.php';
try {
    // Adiciona colunas para controle de ciclo
    $pdo->exec("ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS plan_started_at DATETIME NULL, 
        ADD COLUMN IF NOT EXISTS plan_expires_at DATETIME NULL, 
        ADD COLUMN IF NOT EXISTS plan_cycle ENUM('monthly', 'annual') DEFAULT 'monthly'");
    
    // Atualiza usuários que já estão em planos pagos mas não tem data (setamos para hoje + 30 dias)
    $pdo->exec("UPDATE users SET plan_started_at = NOW(), plan_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) 
                WHERE (plan != 'free' OR current_plan_id > 1) AND plan_expires_at IS NULL");

    echo "Sucesso: Tabela 'users' atualizada para suporte a prorrata.";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
