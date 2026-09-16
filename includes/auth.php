<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isTeamMember(): bool {
    return isset($_SESSION['is_team_member']) && $_SESSION['is_team_member'] === true;
}

function getTeamMemberId(): int {
    return (int)($_SESSION['team_member_id'] ?? 0);
}

function requireLogin(): void {
    global $pdo;
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
    // Force-logout check
    try {
        if (isTeamMember()) {
            $mid  = getTeamMemberId();
            $stmt = $pdo->prepare("SELECT force_logout FROM team_members WHERE id=?");
            $stmt->execute([$mid]);
            $row  = $stmt->fetch();
            if ($row && $row['force_logout']) {
                $pdo->prepare("UPDATE team_members SET force_logout=0 WHERE id=?")->execute([$mid]);
                logout();
            }
        } else {
            $stmt = $pdo->prepare("SELECT force_logout FROM users WHERE id=?");
            $stmt->execute([$_SESSION['user_id']]);
            $row  = $stmt->fetch();
            if ($row && $row['force_logout']) {
                $pdo->prepare("UPDATE users SET force_logout=0 WHERE id=?")->execute([$_SESSION['user_id']]);
                logout();
            }
        }
    } catch (\Throwable $e) {}
}

function getCurrentUser(): ?array {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function getTeamMemberData(): ?array {
    global $pdo;
    if (!isTeamMember()) return null;
    $stmt = $pdo->prepare("SELECT * FROM team_members WHERE id = ?");
    $stmt->execute([getTeamMemberId()]);
    return $stmt->fetch() ?: null;
}

function getTeamPermissions(): ?array {
    if (!isTeamMember()) return null;
    $member = getTeamMemberData();
    if (!$member) return null;
    return json_decode($member['permissions'] ?? '{}', true) ?: [];
}

function canAccess(string $module, string $level = 'read'): bool {
    if (!isTeamMember()) return true;
    if (isPrivilegedMember()) return true; // gérant & directeur have full access
    $perms     = getTeamPermissions() ?? [];
    $userLevel = $perms[$module] ?? 'none';
    $hierarchy = ['none' => 0, 'read' => 1, 'write' => 2, 'admin' => 3];
    return ($hierarchy[$userLevel] ?? 0) >= ($hierarchy[$level] ?? 1);
}

function login(string $email, string $password): bool {
    global $pdo;
    $email = trim($email);

    // 1. Admin users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['nom'];
        unset($_SESSION['is_team_member'], $_SESSION['team_member_id'], $_SESSION['login_history_id']);
        _recordLogin($user['id'], null);
        return true;
    }

    // 2. Team members — email field is used for login (same as professional email)
    $stmt2 = $pdo->prepare("
        SELECT tm.*, u.id AS admin_user_id
        FROM team_members tm
        JOIN users u ON u.id = tm.user_id
        WHERE (tm.email = ? OR tm.login_email = ?) AND tm.statut = 'actif' AND tm.password IS NOT NULL
    ");
    $stmt2->execute([$email, $email]);
    $member = $stmt2->fetch();
    if ($member && password_verify($password, $member['password'])) {
        $_SESSION['user_id']        = $member['admin_user_id'];
        $_SESSION['user_name']      = trim(($member['prenom'] ?? '') . ' ' . $member['nom']);
        $_SESSION['is_team_member'] = true;
        $_SESSION['team_member_id'] = $member['id'];
        unset($_SESSION['login_history_id']);
        _recordLogin($member['admin_user_id'], $member['id']);
        return true;
    }

    return false;
}

function _recordLogin(int $userId, ?int $teamMemberId): void {
    global $pdo;
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $pdo->prepare("INSERT INTO login_history (user_id, team_member_id, ip, user_agent) VALUES (?,?,?,?)")
            ->execute([$userId, $teamMemberId, $ip, $ua]);
        $_SESSION['login_history_id'] = (int)$pdo->lastInsertId();
    } catch (\Throwable $e) {}
}

function register(array $data): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (nom, email, password, entreprise, telephone) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            trim($data['nom']),
            trim($data['email']),
            password_hash($data['password'], PASSWORD_DEFAULT),
            trim($data['entreprise'] ?? ''),
            trim($data['telephone'] ?? ''),
        ]);
    } catch (\PDOException $e) {
        return false;
    }
}

function logout(): void {
    global $pdo;
    try {
        if (!empty($_SESSION['login_history_id'])) {
            $pdo->prepare("UPDATE login_history SET logout_at=NOW(), is_active=0 WHERE id=?")
                ->execute([$_SESSION['login_history_id']]);
        }
    } catch (\Throwable $e) {}
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

function canCreateFacture(array $user): bool {
    global $pdo;
    $limits = getPlanLimits($user['plan'] ?? 'gratuit');
    if ($limits['factures'] >= 999999) return true;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE user_id=? AND MONTH(date_facture)=MONTH(CURDATE()) AND YEAR(date_facture)=YEAR(CURDATE())");
    $stmt->execute([$user['id']]);
    return (int)$stmt->fetchColumn() < $limits['factures'];
}

function getPlanLimits(string $plan): array {
    $pro = [
        'factures'     => 999999,
        'devis'        => 999999,
        'clients'      => 999999,
        'team_members' => 999999,
        'export'       => true,
        'relances'     => true,
        'ai'           => true,
        'historique'   => true,
        'equipe'       => true,
    ];
    $limits = [
        'gratuit' => [
            'factures'     => 3,
            'devis'        => 3,
            'clients'      => 10,
            'team_members' => 0,
            'export'       => false,
            'relances'     => false,
            'ai'           => false,
            'historique'   => false,
            'equipe'       => false,
        ],
        'pro'      => $pro,
        'starter'  => $pro,  // legacy alias → pro
        'business' => $pro,  // legacy alias → pro
    ];
    return $limits[$plan] ?? $limits['gratuit'];
}

/**
 * Redirect to dashboard with an access-denied message if the user's plan
 * doesn't include the requested feature.
 */
function requirePlan(string $feature): void {
    $user = getCurrentUser();
    if (!$user) return;
    $limits  = getPlanLimits($user['plan'] ?? 'gratuit');
    $allowed = $limits[$feature] ?? false;
    if ($allowed === false || $allowed === 0) {
        header('Location: ' . APP_URL . '/dashboard.php?access_denied=' . urlencode($feature));
        exit;
    }
}

/** Role of the currently logged-in team member, null if not a team member. */
function getMemberPoste(): ?string {
    if (!isTeamMember()) return null;
    $member = getTeamMemberData();
    return $member['poste'] ?? null;
}

/** True when the logged-in team member has a privileged role (gérant / directeur). */
function isPrivilegedMember(): bool {
    return in_array(getMemberPoste(), ['gerant', 'directeur'], true);
}

/**
 * Block access for team members who are NOT gérant or directeur.
 * Account owners always pass.
 */
function requireSettingsAccess(): void {
    if (isTeamMember() && !isPrivilegedMember()) {
        header('Location: ' . APP_URL . '/dashboard.php?access_denied=settings');
        exit;
    }
}

/**
 * Block access for ALL team members — only the account owner can proceed.
 */
function requireOwner(): void {
    if (isTeamMember()) {
        header('Location: ' . APP_URL . '/dashboard.php?access_denied=owner_only');
        exit;
    }
}
