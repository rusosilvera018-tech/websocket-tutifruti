<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "min_players: " . config('tutti.room.min_players') . "\n";
echo "TUTTI_MIN_PLAYERS env: " . env('TUTTI_MIN_PLAYERS') . "\n";