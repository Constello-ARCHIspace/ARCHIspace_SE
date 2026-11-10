<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$config = require dirname(__DIR__) . '/config.php';
$db = new PDO('sqlite:' . $config['database'], null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->prepare('DELETE FROM rate_limits WHERE expires_at<?')->execute([time()]);
$db->prepare('DELETE FROM verification_tokens WHERE expires_at<?')->execute([time()]);
$db->prepare('DELETE FROM invitations WHERE expires_at<?')->execute([time()]);
echo "Expired limits and tokens removed.\n";
