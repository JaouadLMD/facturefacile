<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
requireSettingsAccess();
$user = getCurrentUser();
requirePlan('equipe');

$postes = ['gerant'=>'Gérant','directeur'=>'Directeur','commercial'=>'Commercial',
           'comptable'=>'Comptable','technicien'=>'Technicien','assistant'=>'Assistant',
           'rh'=>'RH','autre'=>'Autre'];
$posteColors = ['gerant'=>'#7C3AED','directeur'=>'#D97706','commercial'=>'#059669',
                'comptable'=>'#D97706','technicien'=>'#0891B2','assistant'=>'#DB2777',
                'rh'=>'#4F46E5','autre'=>'#6B7280'];

$stmt = $pdo->prepare("SELECT * FROM team_members WHERE user_id=? ORDER BY poste, nom");
$stmt->execute([$user['id']]);
$membres = $stmt->fetchAll();

// Fetch login stats per member
$loginStats = [];
if ($membres) {
    $ids = array_column($membres, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtL = $pdo->prepare("
        SELECT team_member_id,
               COUNT(*) as total_logins,
               MAX(login_at) as last_login,
               SUM(CASE WHEN logout_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,login_at,logout_at) ELSE 0 END) as total_minutes
        FROM login_history
        WHERE team_member_id IN ($placeholders)
        GROUP BY team_member_id
    ");
    $stmtL->execute($ids);
    foreach ($stmtL->fetchAll() as $row) {
        $loginStats[$row['team_member_id']] = $row;
    }
}

// Fetch action counts per team member from audit_logs (logged under owner user_id)
// We track actions via session — for now show login-based stats + member info
$pageTitle = 'Statistiques de l\'équipe';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-soft);margin-bottom:20px;">
    <a href="<?= APP_URL ?>/equipes.php" style="color:var(--text-mid);text-decoration:none;">Équipe</a>
    <span>›</span>
    <span style="font-weight:500;color:var(--text);">Statistiques</span>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0;">📊 Statistiques de l'équipe</h1>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;"><?= count($membres) ?> membre(s) · activité & connexions</p>
    </div>
    <a href="<?= APP_URL ?>/equipes.php" class="btn-secondary">← Retour équipe</a>
</div>

<?php if (empty($membres)): ?>
<div class="card" style="padding:48px;text-align:center;">
    <p style="font-size:40px;margin:0 0 10px;">👥</p>
    <p style="color:var(--text);font-weight:600;font-size:14px;">Aucun membre dans votre équipe</p>
    <a href="<?= APP_URL ?>/equipes.php" class="btn-primary" style="display:inline-flex;margin-top:12px;">+ Ajouter un membre</a>
</div>
<?php else: ?>

<!-- Summary cards -->
<?php
$nbActifs   = count(array_filter($membres, fn($m) => $m['statut']==='actif'));
$nbWithLogin= count(array_filter($membres, fn($m) => !empty($m['password'])));
$totalLogins= array_sum(array_column($loginStats, 'total_logins'));
?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:24px;">
    <div class="card" style="padding:18px;text-align:center;">
        <p style="font-size:28px;font-weight:900;color:var(--text);margin:0;"><?= count($membres) ?></p>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;">Total membres</p>
    </div>
    <div class="card" style="padding:18px;text-align:center;">
        <p style="font-size:28px;font-weight:900;color:#10B981;margin:0;"><?= $nbActifs ?></p>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;">Actifs</p>
    </div>
    <div class="card" style="padding:18px;text-align:center;">
        <p style="font-size:28px;font-weight:900;color:var(--primary-2);margin:0;"><?= $nbWithLogin ?></p>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;">Avec accès</p>
    </div>
    <div class="card" style="padding:18px;text-align:center;">
        <p style="font-size:28px;font-weight:900;color:var(--text);margin:0;"><?= $totalLogins ?></p>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;">Connexions totales</p>
    </div>
</div>

<!-- Member cards -->
<div style="display:flex;flex-direction:column;gap:14px;">
<?php foreach ($membres as $m):
    $col      = $posteColors[$m['poste']] ?? '#6B7280';
    $initials = strtoupper(mb_substr($m['prenom']?:$m['nom'],0,1)).strtoupper(mb_substr($m['nom'],0,1));
    $ls       = $loginStats[$m['id']] ?? null;
    $logins   = $ls ? (int)$ls['total_logins'] : 0;
    $lastLogin= $ls && $ls['last_login'] ? date('d/m/Y H:i', strtotime($ls['last_login'])) : null;
    $minutes  = $ls ? (int)$ls['total_minutes'] : 0;
    $hours    = intdiv($minutes, 60);
    $mins     = $minutes % 60;
    $timeStr  = $hours > 0 ? "{$hours}h {$mins}min" : ($mins > 0 ? "{$mins}min" : '—');
    $perms    = json_decode($m['permissions']??'{}', true) ?: [];
    $nbPerms  = count(array_filter($perms, fn($v) => $v !== 'none'));
    $isPriv   = in_array($m['poste'], ['gerant','directeur'], true);
?>
<div class="card" style="padding:20px;">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">

        <!-- Avatar + Identity -->
        <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:220px;">
            <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,<?= $col ?>,<?= $col ?>cc);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:17px;flex-shrink:0;box-shadow:0 3px 8px <?= $col ?>44;">
                <?= h($initials) ?>
            </div>
            <div>
                <p style="font-weight:700;color:var(--text);font-size:14px;margin:0;"><?= h(trim($m['prenom'].' '.$m['nom'])) ?></p>
                <div style="display:flex;align-items:center;gap:6px;margin-top:3px;flex-wrap:wrap;">
                    <span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;background:<?= $col ?>20;color:<?= $col ?>;"><?= $postes[$m['poste']]??ucfirst($m['poste']) ?></span>
                    <?php if ($isPriv): ?>
                    <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:700;background:#EDE9FE;color:#5B21B6;">⚡ Accès complet</span>
                    <?php endif; ?>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <div style="width:7px;height:7px;border-radius:50%;background:<?= $m['statut']==='actif'?'#10B981':'#D1D5DB' ?>;"></div>
                        <span style="font-size:11px;color:var(--text-soft);"><?= $m['statut']==='actif'?'Actif':'Inactif' ?></span>
                    </div>
                </div>
                <?php if ($m['email']): ?>
                <p style="font-size:11.5px;color:var(--text-soft);margin:3px 0 0;">📧 <?= h($m['email']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="text-align:center;min-width:70px;">
                <p style="font-size:22px;font-weight:900;color:var(--primary-2);margin:0;"><?= $logins ?></p>
                <p style="font-size:11px;color:var(--text-soft);margin:2px 0 0;">Connexions</p>
            </div>
            <div style="text-align:center;min-width:80px;">
                <p style="font-size:22px;font-weight:900;color:var(--text);margin:0;"><?= $timeStr ?></p>
                <p style="font-size:11px;color:var(--text-soft);margin:2px 0 0;">Temps total</p>
            </div>
            <div style="text-align:center;min-width:80px;">
                <p style="font-size:22px;font-weight:900;color:var(--text);margin:0;"><?= $isPriv ? '∞' : $nbPerms ?></p>
                <p style="font-size:11px;color:var(--text-soft);margin:2px 0 0;">Modules actifs</p>
            </div>
        </div>

        <!-- Last login + Access -->
        <div style="min-width:160px;text-align:right;">
            <?php if ($lastLogin): ?>
            <p style="font-size:12px;color:var(--text-mid);margin:0 0 4px;">Dernière connexion</p>
            <p style="font-size:12.5px;font-weight:700;color:var(--text);margin:0;"><?= $lastLogin ?></p>
            <?php else: ?>
            <p style="font-size:12px;color:var(--text-soft);margin:0;">Jamais connecté</p>
            <?php endif; ?>
            <div style="margin-top:6px;">
                <?php if (!empty($m['password'])): ?>
                <span style="font-size:10.5px;font-weight:600;padding:3px 8px;border-radius:6px;background:#D1FAE5;color:#059669;">🔑 Accès actif</span>
                <?php else: ?>
                <span style="font-size:10.5px;font-weight:600;padding:3px 8px;border-radius:6px;background:var(--bg3);color:var(--text-soft);">Sans accès</span>
                <?php endif; ?>
            </div>
        </div>

        <a href="<?= APP_URL ?>/equipes.php" style="padding:7px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:9px;font-size:12px;font-weight:600;color:var(--text-mid);text-decoration:none;">✏️ Modifier</a>
    </div>

    <!-- Login history mini bar -->
    <?php
    $stmtRecent = $pdo->prepare("SELECT login_at, logout_at, ip FROM login_history WHERE team_member_id=? ORDER BY login_at DESC LIMIT 5");
    $stmtRecent->execute([$m['id']]);
    $recentLogins = $stmtRecent->fetchAll();
    ?>
    <?php if ($recentLogins): ?>
    <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px;">
        <p style="font-size:10px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:.7px;margin:0 0 8px;">Dernières connexions</p>
        <div style="display:flex;flex-direction:column;gap:5px;">
            <?php foreach ($recentLogins as $login): ?>
            <div style="display:flex;align-items:center;gap:10px;font-size:12px;color:var(--text-mid);">
                <span style="color:var(--text-soft);">📅</span>
                <span><?= date('d/m/Y H:i', strtotime($login['login_at'])) ?></span>
                <?php if ($login['logout_at']): ?>
                <span style="color:var(--text-soft);">→ <?= date('H:i', strtotime($login['logout_at'])) ?></span>
                <?php else: ?>
                <span style="color:#10B981;font-weight:600;font-size:11px;">● En ligne</span>
                <?php endif; ?>
                <?php if ($login['ip']): ?>
                <span style="color:var(--text-soft);font-size:11px;">IP: <?= h($login['ip']) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

</div></div></body></html>
