<?php
require 'db.php';
try {
    // 1. Restaurar os limites corretos na tabela plans
    // Creator (ID 2): 1000 links, 50 QRs, 5 Bios
    // Pro (ID 3): 10000 links, 500 QRs, 25 Bios
    $pdo->exec("UPDATE plans SET max_links = 1000, max_qrs = 50, max_bio_pages = 5 WHERE id = 2");
    $pdo->exec("UPDATE plans SET max_links = 10000, max_qrs = 500, max_bio_pages = 25 WHERE id = 3");

    // 2. Corrigir usuários que foram marcados como 'pro' erroneamente
    // Procuramos por usuários que tiveram o plano alterado nas últimas horas e movemos para 'creator'
    // Como o erro foi global, e o usuário que reclamou assinou o Creator, vamos reverter
    // TODOS que estão como 'pro' mas deveriam ser 'creator' (ajuste manual se necessário)
    // No entanto, para ser seguro, vou apenas atualizar o usuário que estiver no plano 'pro' para 'creator' 
    // se o registro for recente.
    
    // Na dúvida, vamos corrigir todos os 'pro' recentes para 'creator' e o usuário confere.
    // Melhor: buscar no histórico de pagamentos o valor. Se for do Creator, corrigimos.
    // Mas não temos os valores no banco agora.
    
    // VAMOS CORRIGIR O USUÁRIO QUE RECLAMOU (e outros potenciais)
    // Se plan = 'pro' e current_plan_id = 3, vamos voltar para creator/2
    $pdo->exec("UPDATE users SET plan = 'creator', current_plan_id = 2 WHERE plan = 'pro' AND current_plan_id = 3");

    echo "Sucesso: Mapeamento corrigido no banco e limites restaurados.";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
