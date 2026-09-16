<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser();
$pageTitle = 'Aide & Support';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<style>
.faq-item { border-bottom: 1px solid var(--border); }
.faq-question {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; cursor: pointer; font-size: 14px; font-weight: 600;
    color: var(--text); gap: 12px; transition: background .15s;
}
.faq-question:hover { background: var(--bg3); }
.faq-chevron { font-size: 12px; color: var(--text-soft); transition: transform .25s; flex-shrink:0; }
.faq-answer {
    display: none; padding: 0 20px 18px; font-size: 13.5px;
    color: var(--text-mid); line-height: 1.75;
}
.faq-item.open .faq-answer { display: block; }
.faq-item.open .faq-chevron { transform: rotate(180deg); }

.contact-card {
    display: flex; flex-direction: column; align-items: center; gap: 10px;
    padding: 28px 20px; border-radius: var(--radius);
    background: var(--card); border: 1px solid var(--border);
    box-shadow: var(--shadow); text-align: center; transition: border-color .2s;
}
.contact-card:hover { border-color: var(--primary); }
.contact-icon {
    width: 52px; height: 52px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center; font-size: 24px;
}
.contact-title { font-size: 14px; font-weight: 700; color: var(--text); }
.contact-desc  { font-size: 12px; color: var(--text-soft); line-height: 1.5; }
.contact-btn {
    margin-top: 4px; padding: 9px 22px; border-radius: 10px;
    font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
    display: inline-block; transition: all .15s;
}
</style>

<!-- Header -->
<div style="margin-bottom:28px;">
    <h1 style="font-size:22px;font-weight:800;color:var(--text);margin:0 0 6px;">❓ Aide & Support</h1>
    <p style="font-size:13px;color:var(--text-soft);margin:0;">Trouvez des réponses à vos questions ou contactez notre équipe.</p>
</div>

<!-- Search bar -->
<div style="margin-bottom:28px;">
    <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:12px 16px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="faqSearch" placeholder="Rechercher dans l'aide…"
               oninput="filterFaq(this.value)"
               style="border:none;background:transparent;font-size:14px;color:var(--text);outline:none;width:100%;">
    </div>
</div>

<!-- FAQ sections -->
<?php
$faqs = [
    'Facturation' => [
        ['Comment créer une facture ?', 'Cliquez sur <strong>Nouvelle facture</strong> dans le menu de gauche ou dans la barre du haut. Remplissez les informations du client, ajoutez vos lignes de prestation, puis cliquez sur <em>Enregistrer</em>.'],
        ['Comment modifier une facture existante ?', 'Sur la page Factures, cliquez sur le numéro de la facture pour l\'ouvrir. La modification directe n\'est pas disponible sur les factures envoyées ; créez une nouvelle facture ou annulez l\'ancienne.'],
        ['Comment imprimer ou exporter en PDF ?', 'Ouvrez la facture depuis la liste, puis cliquez sur le bouton <strong>🖨️ Imprimer / PDF</strong>. Dans la boîte de dialogue d\'impression du navigateur, choisissez <em>Enregistrer au format PDF</em> comme destination.'],
        ['Puis-je personnaliser les couleurs de la facture ?', 'Oui ! Rendez-vous dans <strong>Paramètres → Couleurs de la facture</strong> pour choisir vos couleurs de marque. Vous pouvez également ajouter votre logo et un pied de page personnalisé.'],
        ['Comment convertir un devis en facture ?', 'Ouvrez le devis depuis la liste Devis, puis cliquez sur le bouton <strong>Convertir en facture</strong>. Le devis sera marqué comme Accepté et une nouvelle facture sera créée automatiquement.'],
        ['Comment changer le statut d\'une facture ?', 'Ouvrez la facture et utilisez le menu déroulant de statut en haut à droite : Brouillon, Envoyée, Payée, Impayée ou Annulée.'],
    ],
    'Clients & Devis' => [
        ['Comment ajouter un client ?', 'Allez dans <strong>Clients</strong> dans le menu, puis cliquez sur <strong>+ Nouveau client</strong>. Renseignez le nom, email, ICE et adresse. Le client sera disponible dans toutes vos factures et devis.'],
        ['Puis-je créer plusieurs devis pour le même client ?', 'Oui, il n\'y a pas de limite de devis par client. Créez autant de devis que nécessaire depuis le menu <strong>Devis → Nouveau devis</strong>.'],
        ['Quelle est la différence entre un devis accepté et converti ?', 'Un devis <em>accepté</em> signifie que le client a donné son accord. Convertir un devis crée une facture correspondante et marque automatiquement le devis comme accepté.'],
    ],
    'Compte & Paramètres' => [
        ['Comment changer mon mot de passe ?', 'Allez dans <strong>Paramètres → Sécurité du compte</strong> et renseignez votre mot de passe actuel ainsi que le nouveau.'],
        ['Comment ajouter un logo à mes factures ?', 'Dans <strong>Paramètres → Informations entreprise</strong>, cliquez sur la zone de dépôt du logo et choisissez votre fichier (PNG, JPG, max 2 Mo).'],
        ['Comment gérer les membres de mon équipe ?', 'Disponible à partir du plan <strong>Starter</strong>. Dans <strong>Équipe</strong>, cliquez sur <em>Inviter un membre</em> et renseignez son email. Il recevra un lien de connexion.'],
        ['Où voir l\'historique de mes actions ?', 'Rendez-vous dans la section <strong>Cash Flow / Historique</strong> dans le menu. Toutes les actions (création, modification, impression, etc.) y sont enregistrées.'],
    ],
    'Plans & Facturation' => [
        ['Quelles sont les limites du plan Gratuit ?', 'Le plan Gratuit permet de créer jusqu\'à <strong>3 factures</strong>. Pour un usage illimité, passez au plan Starter, Pro ou Business.'],
        ['Comment passer au plan Pro ?', 'Cliquez sur le bouton <strong>Renouveler / Passer Pro</strong> dans le menu latéral ou dans <em>Paramètres → Plans</em>. Vous pouvez régler par carte bancaire ou virement.'],
        ['Mes données sont-elles conservées si je ne renouvelle pas ?', 'Oui, vos données restent accessibles en lecture. Vous ne pourrez simplement plus créer de nouvelles factures au-delà de la limite du plan Gratuit.'],
    ],
];
?>

<div id="faqContainer" style="display:flex;flex-direction:column;gap:20px;margin-bottom:40px;">
<?php foreach ($faqs as $section => $items): ?>
<div class="card" style="overflow:hidden;" data-section="<?= h($section) ?>">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);background:var(--bg3);">
        <h2 style="font-size:13px;font-weight:700;color:var(--text);margin:0;text-transform:uppercase;letter-spacing:0.5px;"><?= h($section) ?></h2>
    </div>
    <?php foreach ($items as $i => [$q, $a]): ?>
    <div class="faq-item" data-q="<?= h(strtolower($q)) ?>">
        <div class="faq-question" onclick="toggleFaq(this)">
            <span><?= h($q) ?></span>
            <span class="faq-chevron">▼</span>
        </div>
        <div class="faq-answer"><?= $a ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>

<!-- Contact section -->
<div style="margin-bottom:12px;">
    <h2 style="font-size:16px;font-weight:800;color:var(--text);margin:0 0 6px;">Vous n'avez pas trouvé votre réponse ?</h2>
    <p style="font-size:13px;color:var(--text-soft);margin:0 0 20px;">Notre équipe est disponible du lundi au vendredi, de 9h à 18h.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:32px;">

    <!-- Email -->
    <div class="contact-card">
        <div class="contact-icon" style="background:rgba(124,58,237,.15);">✉️</div>
        <div class="contact-title">Email</div>
        <div class="contact-desc">Réponse sous 24h ouvrées.<br>Pour toute demande complexe.</div>
        <a href="mailto:support@facturefacile.ma" class="contact-btn btn-primary">
            Envoyer un email
        </a>
    </div>

    <!-- Téléphone -->
    <div class="contact-card">
        <div class="contact-icon" style="background:rgba(16,185,129,.15);">📞</div>
        <div class="contact-title">Téléphone</div>
        <div class="contact-desc">Support téléphonique disponible.<br>Lun–Ven 9h–18h.</div>
        <a href="tel:+212522000000" class="contact-btn btn-secondary">
            +212 5 22 00 00 00
        </a>
    </div>

    <!-- Chat en direct -->
    <div class="contact-card">
        <div class="contact-icon" style="background:rgba(245,158,11,.15);">💬</div>
        <div class="contact-title">Chat en direct</div>
        <div class="contact-desc">Discutez avec un conseiller<br>en temps réel.</div>
        <button onclick="openLiveChat()" class="contact-btn btn-secondary">
            Démarrer le chat
        </button>
    </div>

</div>

<!-- Quick links -->
<div class="card" style="padding:20px 24px;">
    <h3 style="font-size:13px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:0.5px;margin:0 0 14px;">Liens utiles</h3>
    <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <a href="<?= APP_URL ?>/parametres.php" class="btn-secondary" style="font-size:12.5px;">⚙️ Paramètres</a>
        <a href="<?= APP_URL ?>/historique.php" class="btn-secondary" style="font-size:12.5px;">📜 Historique</a>
        <a href="<?= APP_URL ?>/facture-create.php" class="btn-secondary" style="font-size:12.5px;">➕ Nouvelle facture</a>
        <a href="<?= APP_URL ?>/devis-create.php" class="btn-secondary" style="font-size:12.5px;">📋 Nouveau devis</a>
        <a href="<?= APP_URL ?>/parametres.php#plans" class="btn-secondary" style="font-size:12.5px;">⭐ Voir les plans</a>
    </div>
</div>

<script>
function toggleFaq(el) {
    const item = el.closest('.faq-item');
    const wasOpen = item.classList.contains('open');
    // Close all in same card
    item.closest('.card').querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
}

function filterFaq(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.faq-item').forEach(item => {
        const text = (item.dataset.q || '') + ' ' + item.querySelector('.faq-answer').textContent.toLowerCase();
        item.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
    // Hide section cards if all items hidden
    document.querySelectorAll('#faqContainer .card').forEach(card => {
        const visible = [...card.querySelectorAll('.faq-item')].some(i => i.style.display !== 'none');
        card.style.display = visible ? '' : 'none';
    });
}

function openLiveChat() {
    // Redirect to internal messaging, or open Tawk.to / Crisp widget
    showToast('💬 Le chat en direct sera disponible prochainement.', true);
}
</script>

</div></div></body></html>
