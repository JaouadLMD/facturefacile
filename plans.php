<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
requireSettingsAccess(); // Owner + gérant + directeur only
$user = getCurrentUser();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'plan') {
    $plan = $_POST['plan'] ?? 'gratuit';
    if (in_array($plan, ['gratuit','pro'], true)) {
        $pdo->prepare("UPDATE users SET plan=? WHERE id=?")->execute([$plan, $user['id']]);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();
        $success = $plan;
    }
}

$currentPlan = $user['plan'] ?? 'gratuit';
$isPro = in_array($currentPlan, ['pro','starter','business'], true);
$pageTitle = 'Plans & Abonnement';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<style>
/* ── Plans page ── */
.plans-page { max-width: 860px; margin: 0 auto; }

.plans-header { text-align: center; margin-bottom: 48px; }
.plans-title {
    font-size: 32px; font-weight: 900; color: var(--text);
    line-height: 1.15; margin: 0 0 10px;
    letter-spacing: -0.5px;
}
.plans-sub {
    font-size: 15px; color: var(--text-soft); margin: 0 0 20px;
}
.plans-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(16,185,129,.12); border: 1.5px solid rgba(16,185,129,.3);
    color: #10B981; border-radius: 999px;
    padding: 7px 20px; font-size: 13px; font-weight: 600;
}
.plans-pill-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #10B981; flex-shrink: 0;
}

/* ── Cards grid ── */
.plans-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 20px;
    align-items: start;
}

/* ── Free card ── */
.plan-free {
    background: var(--card);
    border: 1.5px solid var(--border2);
    border-radius: 22px;
    padding: 36px 32px 32px;
    display: flex;
    flex-direction: column;
}
.plan-icon-wrap {
    width: 52px; height: 52px; border-radius: 16px;
    background: var(--bg3); border: 1.5px solid var(--border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; margin-bottom: 20px;
}
.plan-name { font-size: 22px; font-weight: 800; color: var(--text); margin: 0 0 4px; }
.plan-tagline { font-size: 13px; color: var(--text-soft); margin: 0 0 24px; }
.plan-price-row { display: flex; align-items: flex-end; gap: 8px; margin-bottom: 28px; }
.plan-num { font-size: 56px; font-weight: 900; color: var(--text); line-height: 1; }
.plan-unit { font-size: 14px; color: var(--text-soft); font-weight: 500; padding-bottom: 8px; }
.plan-feats {
    list-style: none; padding: 0; margin: 0 0 28px;
    display: flex; flex-direction: column; gap: 12px;
    flex: 1;
}
.plan-feats li {
    display: flex; align-items: center; gap: 11px;
    font-size: 13.5px; color: var(--text-mid);
}
.feat-check {
    width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.feat-check-free {
    background: rgba(16,185,129,.14); border: 1.5px solid rgba(16,185,129,.3);
}
.feat-check-free svg { stroke: #10B981; }
.feat-check-pro {
    background: rgba(124,58,237,.15); border: 1.5px solid rgba(124,58,237,.3);
}
.feat-check-pro svg { stroke: var(--primary-2); }
.plan-btn {
    display: block; width: 100%; padding: 14px; border-radius: 13px;
    font-size: 14px; font-weight: 700; text-align: center;
    cursor: pointer; border: none; transition: all 0.18s;
    text-decoration: none; box-sizing: border-box;
}
.plan-btn-outline {
    background: transparent;
    border: 2px solid var(--border2);
    color: var(--text);
}
.plan-btn-outline:hover { border-color: var(--primary); color: var(--primary-2); background: var(--primary-bg); }
.plan-btn-current-free {
    background: var(--bg3); color: var(--text-soft);
    border: 2px solid var(--border); cursor: default;
}

/* ── Pro card ── */
.plan-pro {
    background: linear-gradient(150deg, #2e1065 0%, #4c1d95 35%, #6d28d9 70%, #7c3aed 100%);
    border-radius: 22px;
    padding: 62px 32px 32px;
    position: relative;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 60px rgba(109,40,217,.4), 0 4px 16px rgba(0,0,0,.3);
    overflow: hidden;
}
.plan-pro::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(167,139,250,.12);
}
.plan-pro::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -40px;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(124,58,237,.15);
}
.plan-popular-badge {
    position: absolute;
    top: 20px; left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    font-size: 11px; font-weight: 800;
    letter-spacing: 1.2px; text-transform: uppercase;
    padding: 6px 20px; border-radius: 999px;
    white-space: nowrap;
    box-shadow: 0 4px 14px rgba(245,158,11,.45);
    z-index: 2;
}
.plan-pro .plan-icon-wrap {
    background: rgba(255,255,255,.12);
    border-color: rgba(255,255,255,.2);
    position: relative; z-index: 1;
}
.plan-pro .plan-name { color: #fff; position: relative; z-index: 1; }
.plan-pro .plan-tagline { color: rgba(255,255,255,.7); position: relative; z-index: 1; }

.plan-price-box {
    background: rgba(0,0,0,.25);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 28px;
    position: relative; z-index: 1;
    backdrop-filter: blur(10px);
}
.plan-price-box-row { display: flex; align-items: flex-end; gap: 10px; }
.plan-price-big { font-size: 60px; font-weight: 900; color: #fff; line-height: 1; }
.plan-price-right { display: flex; flex-direction: column; gap: 2px; padding-bottom: 6px; }
.plan-price-currency { font-size: 16px; font-weight: 700; color: rgba(255,255,255,.9); }
.plan-price-period { font-size: 12px; color: rgba(255,255,255,.55); }
.plan-price-daily { font-size: 12px; color: #a78bfa; margin-top: 6px; font-weight: 500; }
.plan-price-daily::before { content: '↳ '; }

.plan-feats-label {
    font-size: 10px; font-weight: 800; letter-spacing: 1.5px;
    color: rgba(255,255,255,.5); text-transform: uppercase;
    margin-bottom: 14px; position: relative; z-index: 1;
}
.plan-pro .plan-feats { position: relative; z-index: 1; }
.plan-pro .plan-feats li { color: rgba(255,255,255,.9); }

.plan-btn-pro {
    background: #fff;
    color: #5b21b6;
    font-weight: 800;
    box-shadow: 0 6px 20px rgba(0,0,0,.2);
    position: relative; z-index: 1;
}
.plan-btn-pro:hover { background: #f5f3ff; box-shadow: 0 8px 26px rgba(0,0,0,.28); transform: translateY(-1px); }
.plan-btn-current-pro {
    background: rgba(255,255,255,.15);
    border: 2px solid rgba(255,255,255,.4);
    color: #fff; cursor: default;
    position: relative; z-index: 1;
}

/* ── Comparison note ── */
.plans-note {
    text-align: center; margin-top: 28px;
    font-size: 12.5px; color: var(--text-soft);
}
.plans-note a { color: var(--primary-2); text-decoration: none; font-weight: 600; }

@media (max-width: 640px) {
    .plans-grid-2 { grid-template-columns: 1fr; }
    .plan-pro { margin-top: 20px; }
    .plans-title { font-size: 24px; }
}
</style>

<div class="plans-page">

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-soft);margin-bottom:32px;">
    <a href="<?= APP_URL ?>/parametres.php" style="color:var(--text-mid);text-decoration:none;">Paramètres</a>
    <span>›</span>
    <span style="font-weight:500;color:var(--text);">Plans & Abonnement</span>
</div>

<?php if ($success): ?>
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);border-radius:12px;padding:13px 18px;margin-bottom:24px;font-size:13px;color:#10B981;text-align:center;font-weight:600;">
    ✅ Plan mis à jour vers <strong><?= $success === 'pro' ? 'Pro' : 'Gratuit' ?></strong> avec succès.
</div>
<?php endif; ?>
<?php if ($_GET['msg'] ?? '' === 'limit'): ?>
<div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:13px 18px;margin-bottom:24px;font-size:13px;color:#EF4444;text-align:center;">
    🚫 <strong>Limite atteinte.</strong> Passez au plan <strong>Pro</strong> pour continuer sans restriction.
</div>
<?php endif; ?>

<!-- Header -->
<div class="plans-header">
    <h1 class="plans-title">Des tarifs simples<br>et transparents</h1>
    <p class="plans-sub">Commencez gratuitement, passez au Pro quand vous êtes prêt.</p>
    <div class="plans-pill">
        <span class="plans-pill-dot"></span>
        Un seul prix. Tout inclus.
    </div>
</div>

<!-- Cards -->
<div class="plans-grid-2">

    <!-- ── GRATUIT ── -->
    <div class="plan-free">
        <div class="plan-icon-wrap">⚡</div>
        <p class="plan-name">Gratuit</p>
        <p class="plan-tagline">Pour découvrir la plateforme</p>
        <div class="plan-price-row">
            <span class="plan-num">0</span>
            <span class="plan-unit">MAD</span>
        </div>
        <ul class="plan-feats">
            <?php foreach ([
                '3 factures / mois',
                '3 devis / mois',
                '10 clients maximum',
                'Factures PDF',
                'Support email',
            ] as $f): ?>
            <li>
                <span class="feat-check feat-check-free">
                    <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="2,6 5,9 10,3"/></svg>
                </span>
                <?= htmlspecialchars($f) ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$isPro): ?>
        <div class="plan-btn plan-btn-current-free">✓ Plan actuel</div>
        <?php else: ?>
        <form method="POST" id="downgradeForm">
            <input type="hidden" name="action" value="plan">
            <input type="hidden" name="plan" value="gratuit">
            <button type="button" class="plan-btn plan-btn-outline"
                    onclick="ffConfirm('Rétrograder vers Gratuit', 'Rétrograder vers le plan Gratuit ? Les fonctionnalités Pro seront désactivées.', () => document.getElementById(\'downgradeForm\').submit(), true)">
                Commencer gratuitement
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- ── PRO ── -->
    <div class="plan-pro">
        <div class="plan-popular-badge">⭐ LE PLUS POPULAIRE</div>

        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;position:relative;z-index:1;">
            <div class="plan-icon-wrap" style="margin-bottom:0;">✦</div>
            <div>
                <p class="plan-name" style="margin:0;">Pro</p>
                <p class="plan-tagline" style="margin:0;">Pour les pros qui veulent tout</p>
            </div>
        </div>

        <div class="plan-price-box">
            <div class="plan-price-box-row">
                <span class="plan-price-big">99</span>
                <div class="plan-price-right">
                    <span class="plan-price-currency">MAD</span>
                    <span class="plan-price-period">/ mois</span>
                </div>
            </div>
            <p class="plan-price-daily">soit moins de 3,30 MAD / jour</p>
        </div>

        <p class="plan-feats-label">Le plan Pro inclut :</p>
        <ul class="plan-feats" style="margin-bottom:28px;">
            <?php foreach ([
                'Factures & devis illimités',
                'Clients illimités',
                'Export PDF & Excel',
                'Assistant IA avancé (Claude & Groq)',
                'Historique complet & audit',
                'Relances automatiques',
                'Équipe jusqu\'à 5 membres',
                'Email SMTP personnalisé',
                'Personnalisation complète',
                'Support prioritaire',
            ] as $f): ?>
            <li>
                <span class="feat-check feat-check-pro" style="background:rgba(167,139,250,.2);border-color:rgba(167,139,250,.4);">
                    <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="rgba(167,139,250,1)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="2,6 5,9 10,3"/></svg>
                </span>
                <?= htmlspecialchars($f) ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($isPro): ?>
        <div class="plan-btn plan-btn-current-pro">✓ Plan actuel</div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="plan">
            <input type="hidden" name="plan" value="pro">
            <button type="submit" class="plan-btn plan-btn-pro">Choisir Pro →</button>
        </form>
        <?php endif; ?>
    </div>

</div><!-- /plans-grid-2 -->

<p class="plans-note">
    💡 Mode démo — le changement est immédiat. Intégrez Stripe ou CIH Pay pour la production.<br>
    Des questions ? <a href="<?= APP_URL ?>/aide.php">Consultez notre FAQ →</a>
</p>

</div><!-- /plans-page -->

</div></div></body></html>
