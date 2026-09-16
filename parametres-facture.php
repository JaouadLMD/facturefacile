<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
requireSettingsAccess();
$user = getCurrentUser();

$_ic  = json_decode($user['invoice_colors']      ?? '{}', true) ?: [];
$_fi  = json_decode($user['invoice_footer_info'] ?? '{}', true) ?: [];
$_invCols = json_decode($user['invoice_columns'] ?? '{}', true) ?: [];

$defCols  = ['description' => 'Description', 'qty' => 'Qté', 'price' => 'Prix U.', 'tva' => 'TVA %', 'total' => 'Total'];
$colOrder = $_invCols['order'] ?? array_keys($defCols);
// Ensure all keys present
foreach (array_keys($defCols) as $k) {
    if (!in_array($k, $colOrder, true)) $colOrder[] = $k;
}

$_icd = [
    'primary'    => ['label' => 'Couleur principale',      'default' => '#6366f1', 'hint' => 'En-têtes, totaux, accents'],
    'accent'     => ['label' => 'Accentuation',            'default' => '#4f46e5', 'hint' => 'Liens, survols'],
    'text'       => ['label' => 'Texte principal',         'default' => '#1e293b', 'hint' => 'Corps de texte'],
    'text_soft'  => ['label' => 'Texte secondaire',        'default' => '#64748b', 'hint' => 'Libellés, métadonnées'],
    'bg'         => ['label' => 'Fond de la facture',      'default' => '#ffffff', 'hint' => 'Fond principal'],
    'parties_bg' => ['label' => 'Fond zones d\'info',      'default' => '#f8fafc', 'hint' => 'Encadrés client/émetteur'],
    'border'     => ['label' => 'Bordures',                'default' => '#e2e8f0', 'hint' => 'Séparateurs, tableaux'],
    'table_alt'  => ['label' => 'Lignes alternées',        'default' => '#f8fafc', 'hint' => 'Lignes paires du tableau'],
];
$logoUrl = !empty($user['logo_path']) ? h(APP_URL . '/' . $user['logo_path']) : '';
$pageTitle = 'Paramètres Facture';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-soft);margin-bottom:20px;">
    <a href="<?= APP_URL ?>/parametres.php" style="color:var(--text-mid);text-decoration:none;">Paramètres</a>
    <span>›</span>
    <span style="font-weight:500;color:var(--text);">Paramètres Facture</span>
</div>

<h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0 0 24px;">🧾 Paramètres Facture</h1>

<!-- ══ LOGO + COLONNES ══ -->
<div class="card" style="padding:24px;margin-bottom:16px;">
    <h3 style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 4px;">Logo & Libellés des colonnes</h3>
    <p style="font-size:12px;color:var(--text-soft);margin:0 0 20px;">Apparaissent sur toutes vos factures et devis imprimés.</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <!-- Logo -->
        <div>
            <label style="font-size:12px;font-weight:600;color:var(--text);display:block;margin-bottom:8px;">Logo de l'entreprise</label>
            <div id="logoPreview" style="width:100%;height:96px;border:2px dashed var(--border2);border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;background:var(--bg3);overflow:hidden;position:relative;">
                <?php if ($logoUrl): ?>
                <img src="<?= $logoUrl ?>" id="logoImg" style="max-height:84px;max-width:90%;object-fit:contain;">
                <?php else: ?>
                <span id="logoPlaceholder" style="font-size:12px;color:var(--text-soft);">Aucun logo · JPEG, PNG, SVG · max 2 Mo</span>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;">
                <label style="flex:1;padding:8px 12px;background:var(--bg3);border-radius:9px;font-size:12.5px;font-weight:600;color:var(--text);text-align:center;cursor:pointer;border:1px solid var(--border);">
                    📁 Choisir un logo
                    <input type="file" accept="image/*" id="logoInput" style="display:none;" onchange="uploadLogo(this)">
                </label>
                <button onclick="deleteLogo()" id="logoDeleteBtn" style="padding:8px 14px;background:rgba(239,68,68,.12);color:#EF4444;border:none;border-radius:9px;font-size:12.5px;font-weight:600;cursor:pointer;<?= $logoUrl ? '' : 'display:none;' ?>">🗑️ Supprimer</button>
            </div>

            <!-- Affichage en-tête facture -->
            <?php $_hdMode = $_invCols['header_display'] ?? 'logo_name'; ?>
            <div style="margin-top:14px;padding:12px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;">
                <p style="font-size:11.5px;font-weight:700;color:var(--text);margin:0 0 10px;">Affichage en-tête de la facture</p>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text);margin-bottom:8px;">
                    <input type="radio" name="header_display" value="logo_name" <?= $_hdMode === 'logo_name' ? 'checked' : '' ?>
                           style="accent-color:var(--primary);width:15px;height:15px;">
                    🖼️ Logo + nom de la société
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text);">
                    <input type="radio" name="header_display" value="logo_only" <?= $_hdMode === 'logo_only' ? 'checked' : '' ?>
                           style="accent-color:var(--primary);width:15px;height:15px;">
                    🖼️ Logo uniquement (sans nom)
                </label>
            </div>
        </div>

        <!-- Column labels + order -->
        <div>
            <label style="font-size:12px;font-weight:600;color:var(--text);display:block;margin-bottom:6px;">Libellés & ordre des colonnes</label>
            <p style="font-size:11.5px;color:var(--text-soft);margin:0 0 10px;">Glissez pour réordonner. Modifiez le texte pour renommer.</p>
            <div id="colSortable" style="display:flex;flex-direction:column;gap:6px;">
                <?php foreach ($colOrder as $k): if (!isset($defCols[$k])) continue; ?>
                <div class="col-row" data-key="<?= $k ?>" style="display:flex;align-items:center;gap:8px;background:var(--bg3);border:1px solid var(--border);border-radius:9px;padding:7px 10px;cursor:grab;">
                    <span style="color:var(--text-soft);font-size:16px;cursor:grab;user-select:none;">⠿</span>
                    <span style="font-size:11px;color:var(--text-soft);width:78px;flex-shrink:0;"><?= $defCols[$k] ?></span>
                    <input type="text" id="col_<?= $k ?>" value="<?= h($_invCols[$k] ?? $defCols[$k]) ?>"
                           style="flex:1;padding:5px 9px;font-size:12.5px;border:1px solid var(--border);border-radius:7px;background:var(--card);color:var(--text);">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Header / Footer text -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
        <div>
            <label style="font-size:12px;font-weight:600;color:var(--text);display:block;margin-bottom:6px;">Entête de facture <span style="font-weight:400;color:var(--text-soft);">(texte haut)</span></label>
            <textarea id="invoiceHeader" rows="4" placeholder="Ex : Conditions générales, N° SIRET, message d'accueil..."
                      style="width:100%;padding:9px 12px;font-size:12.5px;resize:vertical;box-sizing:border-box;"><?= h($user['invoice_header'] ?? '') ?></textarea>
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;color:var(--text);display:block;margin-bottom:6px;">Pied de page de facture <span style="font-weight:400;color:var(--text-soft);">(texte bas)</span></label>
            <textarea id="invoiceFooter" rows="4" placeholder="Ex : Merci pour votre confiance ! Paiement à 30 jours. IBAN : FR76..."
                      style="width:100%;padding:9px 12px;font-size:12.5px;resize:vertical;box-sizing:border-box;"><?= h($user['invoice_footer'] ?? '') ?></textarea>
        </div>
    </div>

    <div style="margin-top:16px;text-align:right;">
        <button onclick="saveColsAndText()" class="btn-primary">💾 Enregistrer logo & colonnes</button>
    </div>
</div>

<!-- ══ INVOICE COLORS ══ -->
<div class="card" style="padding:24px;margin-bottom:16px;" id="invoice-colors">
    <h3 style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 4px;">🎨 Couleurs de la facture</h3>
    <p style="font-size:12px;color:var(--text-soft);margin:0 0 20px;">La couleur principale se répercute sur toute l'interface. Personnalisez selon votre identité visuelle.</p>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
        <?php foreach ($_icd as $key => $meta): $val = $_ic[$key] ?? $meta['default']; ?>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:5px;font-weight:600;"><?= $meta['label'] ?></label>
            <div style="display:flex;align-items:center;gap:8px;">
                <input type="color" id="ic_<?= $key ?>" value="<?= h($val) ?>"
                       oninput="document.getElementById('ic_hex_<?= $key ?>').value=this.value;updateColorPreview();<?= $key === 'primary' ? 'liveAppColor(this.value);' : '' ?>"
                       style="width:40px;height:36px;padding:2px;border:1px solid var(--border);border-radius:8px;cursor:pointer;background:var(--bg3);">
                <input type="text" id="ic_hex_<?= $key ?>" value="<?= h($val) ?>" maxlength="9"
                       oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)){document.getElementById('ic_<?= $key ?>').value=this.value;updateColorPreview();<?= $key === 'primary' ? 'liveAppColor(this.value);' : '' ?>}"
                       style="flex:1;padding:7px 10px;font-size:12px;font-family:monospace;">
            </div>
            <p style="font-size:11px;color:var(--text-soft);margin:4px 0 0;"><?= $meta['hint'] ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Live preview -->
    <div style="background:var(--bg3);border-radius:12px;padding:16px;margin-bottom:16px;">
        <p style="font-size:11.5px;font-weight:600;color:var(--text-mid);margin:0 0 12px;">Aperçu</p>
        <div id="colorPreview" style="border-radius:10px;overflow:hidden;border:1px solid var(--border);max-width:500px;">
            <div id="prev-header" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p id="prev-num" style="font-size:16px;font-weight:800;margin:0;">FAC-2026-0001</p>
                    <p style="font-size:11px;margin:2px 0 0;" id="prev-date-txt">Date : 01/01/2026</p>
                </div>
                <div id="prev-company" style="text-align:right;font-size:13px;font-weight:600;">Mon Entreprise</div>
            </div>
            <div id="prev-parties" style="padding:10px 16px;font-size:12px;">
                <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.6;">Facturé à</span>
                <p style="font-weight:700;margin:2px 0 0;">Client Exemple</p>
            </div>
            <div id="prev-total-bar" style="padding:10px 16px;display:flex;justify-content:space-between;font-size:13px;font-weight:800;border-top:1px solid;">
                <span>Total TTC</span>
                <span id="prev-total-val">1 200,00 MAD</span>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <button onclick="saveInvoiceColors()" class="btn-primary">💾 Enregistrer les couleurs</button>
        <button onclick="resetColors()" class="btn-secondary">↺ Réinitialiser</button>
    </div>
</div>

<!-- ══ FOOTER INFO ══ -->
<div class="card" style="padding:24px;margin-bottom:16px;" id="invoice-footer-info">
    <h3 style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 4px;">📋 Informations de pied de page</h3>
    <p style="font-size:12px;color:var(--text-soft);margin:0 0 18px;">Adresse, contacts et numéros légaux affichés sur vos factures.</p>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;padding:14px;background:var(--bg3);border-radius:10px;border:1px solid var(--border);">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--text);">
            <input type="checkbox" id="fi_show" <?= !empty($_fi['show']) ? 'checked' : '' ?>
                   style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer;">
            Afficher les informations de pied de page sur les factures
        </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
        <div style="grid-column:1/-1;">
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Adresse</label>
            <input type="text" id="fi_adresse" value="<?= h($_fi['adresse'] ?? '') ?>" placeholder="123 Rue Mohammed V, Casablanca" style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Téléphone</label>
            <input type="text" id="fi_telephone" value="<?= h($_fi['telephone'] ?? '') ?>" placeholder="+212 6 XX XX XX XX" style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Email</label>
            <input type="email" id="fi_email" value="<?= h($_fi['email'] ?? '') ?>" placeholder="contact@entreprise.ma" style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Site web</label>
            <input type="text" id="fi_site_web" value="<?= h($_fi['site_web'] ?? '') ?>" placeholder="www.entreprise.ma" style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">CNSS</label>
            <input type="text" id="fi_cnss" value="<?= h($_fi['cnss'] ?? '') ?>" placeholder="Numéro CNSS" style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">ICE</label>
            <input type="text" id="fi_ice" value="<?= h($_fi['ice'] ?? '') ?>" placeholder="001234567000012" style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">RC (Registre du commerce)</label>
            <input type="text" id="fi_rc" value="<?= h($_fi['rc'] ?? '') ?>" placeholder="RC 12345" style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Patente</label>
            <input type="text" id="fi_patente" value="<?= h($_fi['patente'] ?? '') ?>" placeholder="N° de patente" style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
    </div>

    <h4 style="font-size:12px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.5px;margin:0 0 12px;">Mise en forme & Position</h4>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px;">
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Position</label>
            <select id="fi_position" style="width:100%;padding:8px 12px;font-size:13px;">
                <option value="bottom" <?= ($_fi['position'] ?? 'bottom') === 'bottom' ? 'selected' : '' ?>>Bas de page</option>
                <option value="top"    <?= ($_fi['position'] ?? '') === 'top'    ? 'selected' : '' ?>>Haut de page</option>
                <option value="both"   <?= ($_fi['position'] ?? '') === 'both'   ? 'selected' : '' ?>>Haut et bas</option>
            </select>
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Taille police (px)</label>
            <input type="number" id="fi_font_size" value="<?= (int)($_fi['font_size'] ?? 11) ?>" min="9" max="16"
                   style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Couleur texte</label>
            <div style="display:flex;align-items:center;gap:6px;">
                <input type="color" id="fi_font_color" value="<?= h($_fi['font_color'] ?? '#64748b') ?>"
                       oninput="document.getElementById('fi_font_color_hex').value=this.value"
                       style="width:36px;height:36px;padding:2px;border:1px solid var(--border);border-radius:8px;cursor:pointer;background:var(--bg3);">
                <input type="text" id="fi_font_color_hex" value="<?= h($_fi['font_color'] ?? '#64748b') ?>" maxlength="9"
                       oninput="if(/^#[0-9a-fA-F]{3,8}$/.test(this.value)){document.getElementById('fi_font_color').value=this.value;}"
                       style="flex:1;padding:7px 10px;font-size:12px;font-family:monospace;">
            </div>
        </div>
        <div>
            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Séparateur</label>
            <input type="text" id="fi_separator" value="<?= h($_fi['separator'] ?? ' · ') ?>" maxlength="10"
                   style="width:100%;padding:8px 12px;font-size:13px;">
        </div>
    </div>

    <div style="text-align:right;">
        <button onclick="saveFooterInfo()" class="btn-primary">💾 Enregistrer le pied de page</button>
    </div>
</div>

<script>
// ── Drag-and-drop column sort ──
(function() {
    const list = document.getElementById('colSortable');
    if (!list) return;
    let dragging = null;

    list.addEventListener('dragstart', e => {
        dragging = e.target.closest('.col-row');
        if (dragging) { dragging.style.opacity = '0.4'; e.dataTransfer.effectAllowed = 'move'; }
    });
    list.addEventListener('dragend', e => {
        if (dragging) { dragging.style.opacity = ''; dragging = null; }
    });
    list.addEventListener('dragover', e => {
        e.preventDefault();
        const row = e.target.closest('.col-row');
        if (row && row !== dragging) {
            const rect = row.getBoundingClientRect();
            const mid  = rect.top + rect.height / 2;
            if (e.clientY < mid) list.insertBefore(dragging, row);
            else list.insertBefore(dragging, row.nextSibling);
        }
    });

    // Make rows draggable
    list.querySelectorAll('.col-row').forEach(r => r.setAttribute('draggable', 'true'));
})();

// ── Logo ──
async function uploadLogo(input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('logo', input.files[0]);
    const res  = await fetch(APP_URL + '/api/invoice_settings.php?action=logo', {method: 'POST', body: fd});
    const json = await res.json();
    if (json.success) {
        const ph = document.getElementById('logoPlaceholder');
        if (ph) ph.style.display = 'none';
        let img = document.getElementById('logoImg');
        if (!img) {
            img = document.createElement('img');
            img.id = 'logoImg';
            img.style.cssText = 'max-height:84px;max-width:90%;object-fit:contain;';
            document.getElementById('logoPreview').appendChild(img);
        }
        img.src = json.logo_url + '?t=' + Date.now();
        document.getElementById('logoDeleteBtn').style.display = '';
        showToast('✅ Logo enregistré');
    } else {
        showToast('❌ ' + json.error, false);
    }
}

async function deleteLogo() {
    const res  = await fetch(APP_URL + '/api/invoice_settings.php?action=logo_delete', {method: 'POST'});
    const json = await res.json();
    if (json.success) {
        const img = document.getElementById('logoImg');
        if (img) img.remove();
        const ph = document.getElementById('logoPlaceholder');
        if (ph) { ph.style.display = ''; }
        else {
            const s = document.createElement('span');
            s.id = 'logoPlaceholder';
            s.style.cssText = 'font-size:12px;color:var(--text-soft);';
            s.textContent = 'Aucun logo · JPEG, PNG, SVG · max 2 Mo';
            document.getElementById('logoPreview').appendChild(s);
        }
        document.getElementById('logoDeleteBtn').style.display = 'none';
        showToast('✅ Logo supprimé');
    }
}

// ── Columns + header/footer ──
async function saveColsAndText() {
    const rows  = document.querySelectorAll('#colSortable .col-row');
    const order = Array.from(rows).map(r => r.dataset.key);
    const payload = {
        description: document.getElementById('col_description').value.trim() || 'Description',
        qty:         document.getElementById('col_qty').value.trim()         || 'Qté',
        price:       document.getElementById('col_price').value.trim()       || 'Prix U.',
        tva:         document.getElementById('col_tva').value.trim()         || 'TVA %',
        total:       document.getElementById('col_total').value.trim()       || 'Total',
        order,
        header: document.getElementById('invoiceHeader').value.trim(),
        footer: document.getElementById('invoiceFooter').value.trim(),
        header_display: document.querySelector('input[name="header_display"]:checked')?.value || 'logo_name',
    };
    const res  = await fetch(APP_URL + '/api/invoice_settings.php?action=save', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) showToast('✅ Colonnes & textes enregistrés');
    else showToast('❌ ' + (json.error || 'Erreur'), false);
}

// ── Invoice colors ──
function updateColorPreview() {
    const get = id => document.getElementById(id)?.value || '';
    const bg = get('ic_bg'), primary = get('ic_primary'), textCol = get('ic_text'),
          textSoft = get('ic_text_soft'), partBg = get('ic_parties_bg'), border = get('ic_border');
    const p = document.getElementById('colorPreview');
    if (!p) return;
    p.style.background = bg;
    const header = document.getElementById('prev-header');
    if (header) { header.style.background = bg; header.style.borderBottom = '2px solid ' + border; }
    const num = document.getElementById('prev-num');
    if (num) num.style.color = primary;
    const dateTxt = document.getElementById('prev-date-txt');
    if (dateTxt) dateTxt.style.color = textSoft;
    const company = document.getElementById('prev-company');
    if (company) company.style.color = textCol;
    const parties = document.getElementById('prev-parties');
    if (parties) { parties.style.background = partBg; parties.style.color = textCol; }
    const totalBar = document.getElementById('prev-total-bar');
    if (totalBar) { totalBar.style.background = bg; totalBar.style.color = textCol; totalBar.style.borderColor = border; }
    const totalVal = document.getElementById('prev-total-val');
    if (totalVal) totalVal.style.color = primary;
}
updateColorPreview();

function liveAppColor(hex) {
    if (!/^#[0-9a-fA-F]{6}$/.test(hex)) return;
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
    const mix = (c,w) => Math.round(c + (255 - c) * w);
    const r2 = mix(r,.38), g2 = mix(g,.38), b2 = mix(b,.38);
    const toHex = v => v.toString(16).padStart(2,'0');
    const p2 = '#' + toHex(r2) + toHex(g2) + toHex(b2);
    const root = document.documentElement;
    root.style.setProperty('--primary', hex);
    root.style.setProperty('--primary-2', p2);
    root.style.setProperty('--primary-bg', hex + '1a');
    root.style.setProperty('--primary-glow', hex + '40');
}

async function saveInvoiceColors() {
    const keys = ['primary','accent','text','text_soft','bg','parties_bg','border','table_alt'];
    const payload = {};
    keys.forEach(k => { payload[k] = document.getElementById('ic_' + k)?.value || ''; });
    const res  = await fetch(APP_URL + '/api/invoice_settings.php?action=colors', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) showToast('✅ Couleurs enregistrées');
    else showToast('❌ ' + (json.error || 'Erreur'), false);
}

function resetColors() {
    const defaults = {primary:'#6366f1',accent:'#4f46e5',text:'#1e293b',text_soft:'#64748b',bg:'#ffffff',parties_bg:'#f8fafc',border:'#e2e8f0',table_alt:'#f8fafc'};
    Object.entries(defaults).forEach(([k, v]) => {
        const el = document.getElementById('ic_' + k);
        const hex = document.getElementById('ic_hex_' + k);
        if (el) el.value = v;
        if (hex) hex.value = v;
    });
    updateColorPreview();
    liveAppColor(defaults.primary);
}

// ── Footer info ──
async function saveFooterInfo() {
    const payload = {
        show:      document.getElementById('fi_show').checked,
        position:  document.getElementById('fi_position').value,
        adresse:   document.getElementById('fi_adresse').value.trim(),
        telephone: document.getElementById('fi_telephone').value.trim(),
        email:     document.getElementById('fi_email').value.trim(),
        site_web:  document.getElementById('fi_site_web').value.trim(),
        cnss:      document.getElementById('fi_cnss').value.trim(),
        ice:       document.getElementById('fi_ice').value.trim(),
        rc:        document.getElementById('fi_rc').value.trim(),
        patente:   document.getElementById('fi_patente').value.trim(),
        font_size: document.getElementById('fi_font_size').value,
        font_color:document.getElementById('fi_font_color').value,
        separator: document.getElementById('fi_separator').value,
    };
    const res  = await fetch(APP_URL + '/api/invoice_settings.php?action=footer_info', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) showToast('✅ Pied de page enregistré');
    else showToast('❌ ' + (json.error || 'Erreur'), false);
}
</script>

</div></div></body></html>
