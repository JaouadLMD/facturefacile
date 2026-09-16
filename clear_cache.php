<?php
// Script temporaire pour vider le cache OPcache WAMP
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo '✅ OPcache vidé avec succès.';
} else {
    echo 'ℹ️ OPcache non actif.';
}
echo '<br><a href="factures.php">→ Retour aux factures</a>';
