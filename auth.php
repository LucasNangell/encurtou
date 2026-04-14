<?php
// Evita HTML quebrando o JSON
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // Verifica Status
    if ($action === 'status') {
        if (isset($_SESSION['user_id'])) {
            echo json_encode(['logged_in' => true, 'name' => $_SESSION['user_name']]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
        exit;
    }

    // Sair
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // CRIAR CONTA
        if ($action === 'signup') {
            $name = trim($_POST['name'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $pass = $_POST['password'] ?? '';

            if (!$name || !$nickname || !$email || !$pass || !$phone) {
                echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado.']);
                exit;
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, nickname, email, phone, password) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $nickname, $email, $phone, $hash])) {
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_name'] = $nickname; 
                // Retorna o nome para o Javascript atualizar a tela na hora
                echo json_encode(['success' => true, 'name' => $nickname]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar no banco.']);
            }
            exit;
        }

        // LOGIN
        if ($action === 'login') {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass = $_POST['password'] ?? '';

            $stmt = $pdo->prepare("SELECT id, name, nickname, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($pass, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $nomeExibicao = !empty($user['nickname']) ? $user['nickname'] : $user['name'];
                $_SESSION['user_name'] = $nomeExibicao;
                
                // Retorna o nome para o Javascript
                echo json_encode(['success' => true, 'name' => $nomeExibicao]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Email ou senha incorretos.']);
            }
            exit;
        }

        // ESQUECEU A SENHA
        if ($action === 'forgot') {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $code = sprintf("%06d", mt_rand(1, 999999));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $stmt->execute([$code, $expires, $user['id']]);

                $to = $email;
                $subject = "Redefinir Senha - Encurtou.pro";
                $message = "Olá " . $user['name'] . ",<br><br>Seu código para redefinir a senha é: <b style='font-size:20px; color:#04449E;'>{$code}</b><br><br>Este código expira em 1 hora.";
                $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Encurtou.pro <redefinirsenha@encurtou.pro>\r\nReply-To: redefinirsenha@encurtou.pro\r\n";
                mail($to, $subject, $message, $headers);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        // REDEFINIR SENHA
        if ($action === 'reset') {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $code = trim($_POST['code'] ?? '');
            $newpass = $_POST['new_password'] ?? '';

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$email, $code]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $hash = password_hash($newpass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                $stmt->execute([$hash, $user['id']]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Código inválido ou expirado.']);
            }
            exit;
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro fatal no servidor.']);
}
?>