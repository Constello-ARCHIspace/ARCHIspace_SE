<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$config = require dirname(__DIR__) . '/config.php';
$dir = dirname($config['database']);
if (!is_dir($dir)) mkdir($dir, 0700, true);
$db = new PDO('sqlite:' . $config['database'], null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec(file_get_contents(dirname(__DIR__) . '/app/schema.sql'));
chmod($config['database'], 0600);
echo "ARCHIspace account database initialized.\n";
