<?php
// Impede que erros do PHP quebrem o formato JSON da resposta
ini_set('display_errors', 0);
header('Content-Type: application/json');

// O db.php já inicia a sessão e faz a conexão com o banco (local ou servidor)
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SEGURANÇA: Verifica se o usuário está logado
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou não autorizada. Faça login novamente.']);
        exit;
    }

    // 2. CAPTURA E LIMPEZA DOS DADOS
    $rawUrl = trim($_POST['url'] ?? '');
    $sub = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['subdomain'] ?? '')));
    $path = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['path'] ?? '')));

    // 3. VACINA DE URL: Adiciona https:// se o usuário não digitou o protocolo
    if (!empty($rawUrl) && !preg_match("~^(?:f|ht)tps?://~i", $rawUrl)) {
        $rawUrl = "https://" . $rawUrl;
    }

    // Validação final da URL após o tratamento
    $url = filter_var($rawUrl, FILTER_VALIDATE_URL);

    // 4. VALIDAÇÃO DE CAMPOS OBRIGATÓRIOS
    if (!$url || !$path) {
        echo json_encode(['success' => false, 'message' => 'URL inválida ou terminação faltando.']);
        exit;
    }

    // Define o valor padrão para subdomínio vazio
    $subValue = $sub ?: 'ROOT';
    
    // Captura o IP do criador (função definida no db.php)
    $creatorIP = getRealIP();

    try {
        // 5. INSERÇÃO NO BANCO DE DADOS
        $stmt = $pdo->prepare("INSERT INTO links (user_id, subdomain, path, original_url, creator_ip) VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $userId, 
            $subValue, 
            $path, 
            $url, 
            $creatorIP
        ]);
        
        // Se chegou aqui, deu certo
        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        // Trata erro de duplicidade (Subdomínio + Path já existentes)
        if ($e->getCode() == 23000) { 
            echo json_encode(['success' => false, 'message' => 'Essa combinação de subdomínio e terminação já está em uso.']);
        } else {
            // Em caso de outro erro de banco, envia uma mensagem genérica ou o erro se for local
            $msg = (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) ? $e->getMessage() : 'Erro interno ao salvar no banco.';
            echo json_encode(['success' => false, 'message' => $msg]);
        }
    }
} else {
    // Caso tentem acessar o arquivo via GET (pelo navegador direto)
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
}
?>