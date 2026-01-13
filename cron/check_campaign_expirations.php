<?php
require_once dirname(__DIR__) . '/includes/config.php';

// Esegui il controllo delle scadenze
$expired_count = checkExpiredPausedCampaigns();

// Log del risultato
file_put_contents(dirname(__DIR__) . '/logs/cron.log', 
    date('Y-m-d H:i:s') . " - Expired $expired_count campaigns\n", 
    FILE_APPEND | LOCK_EX
);

echo "Checked campaign expirations. Expired: $expired_count\n";