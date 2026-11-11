<?php
// usage: php boot.php <laravel_root> <script.php>
$root = $argv[1];
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app['session']->setDefaultDriver('array');
$store = $app['session']->driver();
$store->start();
$app['request']->setLaravelSession($store);
session(['expense_connection' => 'expense', 'year_id' => 8, 'company_id' => 1]);
require $argv[2];
