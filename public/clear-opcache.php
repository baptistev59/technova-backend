<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OPcache cleared successfully\n";
} else {
    echo "⚠ OPcache not enabled\n";
}

if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "✓ APCu cleared successfully\n";
}

echo "\nNow delete this file: rm public/clear-opcache.php\n";
