<?php
/**
 * OmniPOS - 1-Click Server Update & Deployment
 */
header('Content-Type: text/plain; charset=utf-8');

$dir = __DIR__;
$output = shell_exec("cd {$dir} && git pull origin main 2>&1");
echo "=== OmniPOS Auto-Update ===\n\n";
echo $output . "\n";
echo "\nStatus: Complete.\n";
