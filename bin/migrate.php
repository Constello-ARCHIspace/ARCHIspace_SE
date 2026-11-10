<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/migrate.php';
$config = require dirname(__DIR__) . '/config.php';
if (!is_file($config['database'])) { fwrite(STDERR,"Run php bin/init.php first.\n"); exit(1); }
$db = new PDO('sqlite:' . $config['database'], null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
migrateAccounts($db);
echo "Admin schema ready. Existing users remain standard users.\n";
