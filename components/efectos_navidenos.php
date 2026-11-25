<?php
require_once __DIR__ . '/../controllers/featureFlags.php';
$prefijoEfectosNavidenos = $prefijoEfectosNavidenos ?? '';
if (FEATURE_EFECTOS_NAVIDENOS):
?>
<script src="<?= htmlspecialchars($prefijoEfectosNavidenos, ENT_QUOTES, 'UTF-8') ?>assets/js/efectos-navidenos.js?v=20261125-2" defer></script>
<?php endif; ?>
