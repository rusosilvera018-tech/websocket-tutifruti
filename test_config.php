<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Cache default: " . config('cache.default') . "\n";
echo "Tutti store: " . config('tutti.store') . "\n";
echo "Cache file driver: " . config('cache.stores.file.driver') . "\n";
echo "Cache database driver: " . config('cache.stores.database.driver') . "\n";
echo "Session driver: " . config('session.driver') . "\n";