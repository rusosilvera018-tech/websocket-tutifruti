<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "min_players: " . config('tutti.room.min_players') . "\n";
echo "room config: ";
print_r(config('tutti.room'));