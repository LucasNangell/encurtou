<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'status') {
        if (isset($_SESSION['user_id'])) {
            echo json_encode(['logged_in' => true, 'name' => $_SESSION['user_name']]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                echo json_encode(['success' => false, 'message' => 'E-mail já cadastrado.']);
                exit;
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, nickname, email, phone, password) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $nickname, $email, $phone, $hash])) {
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_name'] = $nickname;
                echo json_encode(['success' => true, 'name' => $nickname]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao criar conta.']);
            }
        }

        if ($action === 'login') {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass = $_POST['password'] ?? '';
            $stmt = $pdo->prepare("SELECT id, name, nickname, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($pass, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $display = !empty($user['nickname']) ? $user['nickname'] : $user['name'];
                $_SESSION['user_name'] = $display;
                echo json_encode(['success' => true, 'name' => $display]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Credenciais inválidas.']);
            }
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>