<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();
requirePlan('historique');

$filterModule = $_GET['module'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDate   = $_GET['date']   ?? '';
$search       = trim($_GET['q'] ?? '');

$where  = "l.user_id=?";
$params = [$user['id']];
if ($filterModule) { $where .= " AND l.module=?"; $params[] = $filterModule; }
if ($filterAction) { $where .= " AND l.action=?"; $params[] = $filterAction; }
if ($filterDate)   { $where .= " AND DATE(l.created_at)=?"; $params[] = $filterDate; }
if ($search)       { $where .= " AND (l.entity_ref LIKE ? OR l.details LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $pdo->prepare("SELECT l.*, u.nom as user_nom FROM audit_logs l LEFT JOIN users u ON l.user_id=u.id WHERE $where ORDER BY l.created_at DESC LIMIT 200");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = ['facture'=>'Factures','devis'=>'Devis','client'=>'Clients','equipe'=>'Équipe','auth'=>'Connexion','parametres'=>'Paramètres','export'=>'Export'];
$actions = ['create'=>'Création','update'=>'Modification','delete'=>'Suppression','send'=>'Envoi email','status_change'=>'Changement statut','login'=>'Connexion','logout'=>'Déconnexion','export'=>'Export','upload'=>'Fichier uploadé'];

$moduleColors = ['facture'=>'#2563EB','devis'=>'#7C3AED','client'=>'#059669','equipe'=>'#D97706','auth'=>'#0891B2','parametres'=>'#6B7280','export'=>'#DB2777'];
$actionIcons  = ['create'=>'✅','update'=>'✏️','delete'=>'🗑️','send'=>'📧','status_change'=>'🔄','login'=>'🔑','logout'=>'🚪','export'=>'⬇️','upload'=>'📎','relance'=>'🔔'];

$pageTitle = 'Historique';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0;">📜 Historique des actions</h1>
        <p style="font-size:12px;color:var(--text-soft);margin:4px 0 0;"><?= count($logs) ?> entrée(s) trouvée(s)</p>
    </div>
    <a href="<?= APP_URL ?>/api/export.php?type=historique" target="_blank" class="btn-secondary">⬇️ Exporter</a>
</div>

<!-- Filtres -->
<div class="card" style="padding:14px 18px;margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:160px;">
            <label style="font-size:11px;color:var(--text-soft);display:block;margin-bottom:4px;">Rechercher</label>
            <input type="text" name="q" value="<?= h($search) ?>" placeholder="Référence, détails..."
                   style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--bg3);color:var(--text);">
        </div>
        <div>
            <label style="font-size:11px;color:var(--text-soft);display:block;margin-bottom:4px;">Module</label>
            <select name="module" style="padding:8px 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--bg3);color:var(--text);">
                <option value="">Tous</option>
                <?php foreach ($modules as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filterModule===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:11px;color:var(--text-soft);display:block;margin-bottom:4px;">Action</label>
            <select name="action" style="padding:8px 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--bg3);color:var(--text);">
                <option value="">Toutes</option>
                <?php foreach ($actions as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filterAction===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:11px;color:var(--text-soft);display:block;margin-bottom:4px;">Date</label>
            <input type="date" name="date" value="<?= h($filterDate) ?>"
                   style="padding:8px 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--bg3);color:var(--text);">
        </div>
        <button type="submit" class="btn-primary" style="padding:8px 18px;">🔍 Filtrer</button>
        <a href="<?= APP_URL ?>/historique.php" class="btn-secondary">✕ Reset</a>
    </form>
</div>

<?php if (empty($logs)): ?>
<div class="card" style="padding:60px;text-align:center;">
    <p style="font-size:40px;margin:0 0 10px;">📭</p>
    <p style="font-weight:600;color:var(--text);margin:0;">Aucune action enregistrée</p>
    <p style="color:var(--text-soft);font-size:13px;margin:6px 0 0;">Les actions se rempliront au fur et à mesure de l'utilisation.</p>
</div>
<?php else: ?>

<div class="card" style="overflow:hidden;">
    <!-- Timeline -->
    <div style="padding:8px 24px;">
        <?php
        $prevDate = null;
        foreach ($logs as $log):
            $logDate = date('d/m/Y', strtotime($log['created_at']));
            $logHour = date('H:i', strtotime($log['created_at']));
            $col     = $moduleColors[$log['module']] ?? '#6B7280';
            $icon    = $actionIcons[$log['action']] ?? '•';
            $modLabel = $modules[$log['module']] ?? ucfirst($log['module']);
            $actLabel = $actions[$log['action']] ?? ucfirst($log['action']);
        ?>

        <?php if ($logDate !== $prevDate): $prevDate = $logDate; ?>
        <div style="display:flex;align-items:center;gap:12px;padding:16px 0 8px;">
            <div style="flex:1;height:1px;background:var(--border);"></div>
            <span style="font-size:11.5px;font-weight:600;color:var(--text-soft);white-space:nowrap;"><?= $logDate ?></span>
            <div style="flex:1;height:1px;background:var(--border);"></div>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:14px;padding:10px 0;border-bottom:1px solid var(--border);" class="table-row">
            <!-- Timeline dot -->
            <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;padding-top:2px;">
                <div style="width:34px;height:34px;border-radius:10px;background:<?= $col ?>18;display:flex;align-items:center;justify-content:center;font-size:16px;">
                    <?= $icon ?>
                </div>
            </div>

            <!-- Content -->
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px;">
                    <span style="font-size:13px;font-weight:600;color:var(--text);"><?= h($log['user_nom'] ?? 'Utilisateur') ?></span>
                    <span style="font-size:12.5px;color:var(--text-mid);"><?= $actLabel ?></span>
                    <?php if ($log['entity_ref']): ?>
                    <span style="font-size:12px;font-weight:700;color:<?= $col ?>;font-family:monospace;"><?= h($log['entity_ref']) ?></span>
                    <?php endif; ?>
                    <span style="display:inline-block;padding:1px 8px;border-radius:6px;font-size:10.5px;font-weight:600;background:<?= $col ?>18;color:<?= $col ?>;"><?= $modLabel ?></span>
                </div>
                <?php if ($log['details']): ?>
                <p style="font-size:12px;color:var(--text-soft);margin:0;"><?= h($log['details']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Time + IP -->
            <div style="text-align:right;flex-shrink:0;">
                <p style="font-size:12px;font-weight:600;color:var(--text-mid);margin:0;"><?= $logHour ?></p>
                <?php if ($log['ip']): ?>
                <p style="font-size:11px;color:var(--text-soft);margin:2px 0 0;font-family:monospace;"><?= h($log['ip']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</div></div></body></html>
