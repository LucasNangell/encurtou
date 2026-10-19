<?php

function teamColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function teamEnsureRoleProfileColumn(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!teamColumnExists($pdo, 'team_members', 'role_profile')) {
        try {
            $pdo->exec("ALTER TABLE team_members ADD COLUMN role_profile VARCHAR(20) NOT NULL DEFAULT 'editor' AFTER role");
        } catch (Throwable $e) {
            // Ignora erro para manter compatibilidade em ambientes restritos.
        }
    }
}

function teamNormalizeProfile(?string $value): string
{
    $v = strtolower(trim((string)$value));
    if ($v === 'owner' || $v === 'proprietario') return 'owner';
    if ($v === 'administrator' || $v === 'admin' || $v === 'administrador') return 'administrator';
    if ($v === 'viewer' || $v === 'visualizador') return 'viewer';
    return 'editor';
}

function teamProfileLabel(string $profile): string
{
    $p = teamNormalizeProfile($profile);
    if ($p === 'owner') return 'Proprietário';
    if ($p === 'administrator') return 'Administrador';
    if ($p === 'viewer') return 'Visualizador';
    return 'Editor';
}

function teamCanManageMembers(string $profile): bool
{
    $p = teamNormalizeProfile($profile);
    return $p === 'owner' || $p === 'administrator';
}

function teamCanEditContent(string $profile): bool
{
    $p = teamNormalizeProfile($profile);
    return $p === 'owner' || $p === 'administrator' || $p === 'editor';
}

function teamResolveMembershipContext(PDO $pdo, int $userId): array
{
    teamEnsureRoleProfileColumn($pdo);
    $hasProfileColumn = teamColumnExists($pdo, 'team_members', 'role_profile');

    $ownerStmt = $pdo->prepare("SELECT id, owner_id, name FROM teams WHERE owner_id = ? LIMIT 1");
    $ownerStmt->execute([$userId]);
    $owned = $ownerStmt->fetch(PDO::FETCH_ASSOC);
    if ($owned) {
        return [
            'has_team' => true,
            'team_id' => (int)$owned['id'],
            'owner_id' => (int)$owned['owner_id'],
            'team_name' => (string)($owned['name'] ?? 'Equipe'),
            'profile' => 'owner',
            'is_owner' => true,
            'can_manage_members' => true,
            'can_edit_content' => true,
            'has_profile_column' => $hasProfileColumn
        ];
    }

    $selectProfile = $hasProfileColumn
        ? "COALESCE(NULLIF(tm.role_profile,''), IF(tm.role = 'admin', 'administrator', 'editor'))"
        : "IF(tm.role = 'admin', 'administrator', 'editor')";

    $memberStmt = $pdo->prepare("
        SELECT t.id, t.owner_id, t.name, {$selectProfile} AS resolved_profile
        FROM team_members tm
        INNER JOIN teams t ON t.id = tm.team_id
        WHERE tm.user_id = ?
        LIMIT 1
    ");
    $memberStmt->execute([$userId]);
    $membership = $memberStmt->fetch(PDO::FETCH_ASSOC);
    if (!$membership) {
        return [
            'has_team' => false,
            'team_id' => 0,
            'owner_id' => 0,
            'team_name' => '',
            'profile' => 'editor',
            'is_owner' => false,
            'can_manage_members' => false,
            'can_edit_content' => true,
            'has_profile_column' => $hasProfileColumn
        ];
    }

    $profile = teamNormalizeProfile((string)($membership['resolved_profile'] ?? 'editor'));
    return [
        'has_team' => true,
        'team_id' => (int)$membership['id'],
        'owner_id' => (int)$membership['owner_id'],
        'team_name' => (string)($membership['name'] ?? 'Equipe'),
        'profile' => $profile,
        'is_owner' => false,
        'can_manage_members' => teamCanManageMembers($profile),
        'can_edit_content' => teamCanEditContent($profile),
        'has_profile_column' => $hasProfileColumn
    ];
}

function teamGetMemberUserIds(PDO $pdo, int $teamId): array
{
    $ids = [];
    if ($teamId <= 0) {
        return $ids;
    }

    $ownerStmt = $pdo->prepare("SELECT owner_id FROM teams WHERE id = ? LIMIT 1");
    $ownerStmt->execute([$teamId]);
    $ownerId = (int)$ownerStmt->fetchColumn();
    if ($ownerId > 0) {
        $ids[] = $ownerId;
    }

    $memberStmt = $pdo->prepare("SELECT user_id FROM team_members WHERE team_id = ?");
    $memberStmt->execute([$teamId]);
    $members = $memberStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($members as $memberId) {
        $id = (int)$memberId;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

