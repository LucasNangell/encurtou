<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'billing/PlanService.php';
require_once 'billing/EntitlementService.php';
require_once 'team_permissions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$readOnlyActions = ['list'];
if ($action !== '' && !in_array($action, $readOnlyActions, true)) {
    // Mantém compatibilidade com clientes legados via fallback same-origin.
    requireCsrfToken(false);
}

function resolveTeamLimits(PDO $pdo, int $ownerId): array {
    $stmtPlan = $pdo->prepare("SELECT current_plan_id, plan FROM users WHERE id = ?");
    $stmtPlan->execute([$ownerId]);
    $userPlanData = $stmtPlan->fetch(PDO::FETCH_ASSOC) ?: [];

    $planId = (int)($userPlanData['current_plan_id'] ?? 0);
    $planSlug = strtolower((string)($userPlanData['plan'] ?? 'free'));
    if ($planId <= 0) {
        $planId = ($planSlug === 'business') ? 4 : (($planSlug === 'pro') ? 3 : (($planSlug === 'creator') ? 2 : 1));
    }

    $maxMembers = EntitlementService::getEffectiveLimit($ownerId, 'max_team_members', 1);
    if ($maxMembers >= 999999) {
        $maxMembers = 999999;
    }
    if ($maxMembers <= 1) {
        if ($planSlug === 'pro' || $planId === 3) $maxMembers = 2;
        if ($planSlug === 'business' || $planId === 4) $maxMembers = 10;
    }

    return [
        'plan_id' => $planId,
        'max_members' => $maxMembers
    ];
}

function buildTeamMembersList(PDO $pdo, int $teamId): array
{
    $members = [];
    $ctx = teamResolveMembershipContext($pdo, (int)($_SESSION['user_id'] ?? 0));
    $hasProfileColumn = $ctx['has_profile_column'] ?? false;

    $ownerStmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, t.created_at AS joined_at
        FROM teams t
        INNER JOIN users u ON u.id = t.owner_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $ownerStmt->execute([$teamId]);
    $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
    if ($owner) {
        $members[] = [
            'id' => (int)$owner['id'],
            'name' => (string)$owner['name'],
            'email' => (string)$owner['email'],
            'role' => 'proprietario',
            'profile' => 'owner',
            'profile_label' => teamProfileLabel('owner'),
            'joined_at' => (string)($owner['joined_at'] ?? ''),
        ];
    }

    $profileSql = $hasProfileColumn
        ? "COALESCE(NULLIF(tm.role_profile,''), IF(tm.role = 'admin', 'administrator', 'editor'))"
        : "IF(tm.role = 'admin', 'administrator', 'editor')";

    $memberStmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, tm.role, {$profileSql} AS profile, tm.joined_at
        FROM team_members tm
        INNER JOIN users u ON u.id = tm.user_id
        WHERE tm.team_id = ?
        ORDER BY tm.id ASC
    ");
    $memberStmt->execute([$teamId]);
    while ($row = $memberStmt->fetch(PDO::FETCH_ASSOC)) {
        $profile = teamNormalizeProfile((string)($row['profile'] ?? 'editor'));
        $members[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'email' => (string)$row['email'],
            'role' => (string)($row['role'] ?? 'member'),
            'profile' => $profile,
            'profile_label' => teamProfileLabel($profile),
            'joined_at' => (string)($row['joined_at'] ?? ''),
        ];
    }

    return $members;
}

try {
    // 1. LISTAR MEMBROS
    if ($action === 'list') {
        $teamFeatureEnabled = EntitlementService::canAccessFeature($userId, 'team');
        $team = teamResolveMembershipContext($pdo, (int)$userId);

        if (empty($team['has_team'])) {
            echo json_encode([
                'success' => true,
                'has_team' => false,
                'team_feature_enabled' => $teamFeatureEnabled,
                'profile' => 'editor',
                'profile_label' => teamProfileLabel('editor'),
                'can_manage_members' => false,
                'can_edit_content' => true,
                'can_invite' => false,
                'members' => []
            ]);
            exit;
        }

        $members = buildTeamMembersList($pdo, (int)$team['team_id']);

        $teamLimits = resolveTeamLimits($pdo, (int)$team['owner_id']);
        $maxMembers = (int)$teamLimits['max_members'];

        echo json_encode([
            'success' => true, 
            'has_team' => true, 
            'is_owner' => (bool)$team['is_owner'],
            'team_name' => $team['team_name'],
            'team_feature_enabled' => $teamFeatureEnabled,
            'profile' => $team['profile'],
            'profile_label' => teamProfileLabel((string)$team['profile']),
            'can_manage_members' => (bool)$team['can_manage_members'],
            'can_edit_content' => (bool)$team['can_edit_content'],
            'can_invite' => (bool)$team['can_manage_members'],
            'max_members' => $maxMembers,
            'members' => $members
        ]);
        exit;
    }

    // 2. CONVIDAR MEMBRO
    if ($action === 'invite') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!$email) {
            echo json_encode(['success' => false, 'message' => 'Email é obrigatório.']);
            exit;
        }

        if (!EntitlementService::canAccessFeature($userId, 'team')) {
            echo json_encode([
                'success' => false,
                'error' => 'feature_locked',
                'message' => 'Seu plano atual não permite colaboração em equipe.'
            ]);
            exit;
        }

        $myTeamCtx = teamResolveMembershipContext($pdo, (int)$userId);
        if (!teamCanManageMembers((string)$myTeamCtx['profile'])) {
            echo json_encode(['success' => false, 'message' => 'Seu perfil na equipe não permite convidar membros.']);
            exit;
        }

        $teamOwnerId = (int)($myTeamCtx['owner_id'] ?: $userId);
        $teamLimits = resolveTeamLimits($pdo, $teamOwnerId);
        $maxMembers = (int)$teamLimits['max_members'];

        // Garante time existente para owner; para membros usa o time atual
        $teamId = (int)($myTeamCtx['team_id'] ?? 0);
        if ($teamId <= 0) {
            $pdo->prepare("INSERT INTO teams (owner_id) VALUES (?)")->execute([$userId]);
            $teamId = $pdo->lastInsertId();
        }

        // Verifica consumo no ciclo mensal (Dono + convites do ciclo)
        $cycle = EntitlementService::getUsageCycleWindow($teamOwnerId);
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND joined_at >= ? AND joined_at < ?");
        $stmtCount->execute([$teamId, $cycle['start'], $cycle['end']]);
        $currentMembersCount = (int)$stmtCount->fetchColumn() + 1; // +1 do dono

        if ($currentMembersCount >= $maxMembers) {
            echo json_encode(['success' => false, 'message' => 'Limite mensal de membros do plano atingido ('.$maxMembers.').']);
            exit;
        }

        // Verifica se o usuário a ser convidado existe
        $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtUser->execute([$email]);
        $invitedUser = $stmtUser->fetch();

        if (!$invitedUser) {
            echo json_encode(['success' => false, 'message' => 'Usuário com este e-mail não encontrado. Peça para ele se cadastrar primeiro.']);
            exit;
        }

        if ($invitedUser['id'] == $userId) {
            echo json_encode(['success' => false, 'message' => 'Você já é o proprietário desta equipe.']);
            exit;
        }

        // Verifica se já está em alguma equipe
        $stmtInTeam = $pdo->prepare("
            SELECT id FROM team_members WHERE user_id = ? 
            UNION 
            SELECT id FROM teams WHERE owner_id = ?
        ");
        $stmtInTeam->execute([$invitedUser['id'], $invitedUser['id']]);
        if ($stmtInTeam->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Este usuário já pertence a uma equipe.']);
            exit;
        }

        // Adiciona à equipe
        if (teamColumnExists($pdo, 'team_members', 'role_profile')) {
            $stmtAdd = $pdo->prepare("INSERT INTO team_members (team_id, user_id, role, role_profile) VALUES (?, ?, 'member', 'editor')");
            $stmtAdd->execute([$teamId, $invitedUser['id']]);
        } else {
            $stmtAdd = $pdo->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
            $stmtAdd->execute([$teamId, $invitedUser['id']]);
        }

        echo json_encode(['success' => true, 'message' => 'Membro adicionado com sucesso!']);
        exit;
    }

    // 3. REMOVER MEMBRO
    if ($action === 'remove_member') {
        $memberUserId = (int)$_POST['user_id'];

        $teamCtx = teamResolveMembershipContext($pdo, (int)$userId);
        if (empty($teamCtx['has_team'])) {
            echo json_encode(['success' => false, 'message' => 'Equipe não encontrada.']);
            exit;
        }
        if (!teamCanManageMembers((string)$teamCtx['profile'])) {
            echo json_encode(['success' => false, 'message' => 'Seu perfil na equipe não permite remover membros.']);
            exit;
        }
        if ((int)$teamCtx['owner_id'] === $memberUserId) {
            echo json_encode(['success' => false, 'message' => 'Não é possível remover o proprietário da equipe.']);
            exit;
        }

        $stmtDel = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
        $stmtDel->execute([(int)$teamCtx['team_id'], $memberUserId]);

        echo json_encode(['success' => true, 'message' => 'Membro removido.']);
        exit;
    }

    // 4. ATUALIZAR PERFIL DO MEMBRO
    if ($action === 'update_member_role') {
        $memberUserId = (int)($_POST['user_id'] ?? 0);
        $profile = teamNormalizeProfile((string)($_POST['profile'] ?? 'editor'));

        if ($memberUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Membro inválido.']);
            exit;
        }
        if (!in_array($profile, ['administrator', 'editor', 'viewer'], true)) {
            echo json_encode(['success' => false, 'message' => 'Perfil inválido.']);
            exit;
        }

        $teamCtx = teamResolveMembershipContext($pdo, (int)$userId);
        if (empty($teamCtx['has_team'])) {
            echo json_encode(['success' => false, 'message' => 'Equipe não encontrada.']);
            exit;
        }
        if (!teamCanManageMembers((string)$teamCtx['profile'])) {
            echo json_encode(['success' => false, 'message' => 'Seu perfil na equipe não permite alterar permissões.']);
            exit;
        }
        if ((int)$teamCtx['owner_id'] === $memberUserId) {
            echo json_encode(['success' => false, 'message' => 'Não é possível alterar o perfil do proprietário.']);
            exit;
        }

        $legacyRole = ($profile === 'administrator') ? 'admin' : 'member';
        if (teamColumnExists($pdo, 'team_members', 'role_profile')) {
            $stmt = $pdo->prepare("UPDATE team_members SET role = ?, role_profile = ? WHERE team_id = ? AND user_id = ?");
            $stmt->execute([$legacyRole, $profile, (int)$teamCtx['team_id'], $memberUserId]);
        } else {
            $stmt = $pdo->prepare("UPDATE team_members SET role = ? WHERE team_id = ? AND user_id = ?");
            $stmt->execute([$legacyRole, (int)$teamCtx['team_id'], $memberUserId]);
        }

        echo json_encode(['success' => true, 'message' => 'Perfil do membro atualizado com sucesso.']);
        exit;
    }

} catch (PDOException $e) {
    error_log('manage_teams.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar equipe.']);
}
