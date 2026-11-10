<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Server-console-only bootstrap; no web endpoint and no default credentials.
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/migrate.php';
if ($argc !== 2) { fwrite(STDERR,"Usage: php bin/promote-admin.php verified-email@example.com\n"); exit(1); }
try {
    $email = Auth::email($argv[1]);
    $config = require dirname(__DIR__) . '/config.php';
    if (!is_file($config['database'])) throw new RuntimeException('Initialize the database first.');
    $db = new PDO('sqlite:' . $config['database'], null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    migrateAccounts($db);
    $db->exec('BEGIN IMMEDIATE');
    if ((int)$db->query("SELECT count(*) FROM users WHERE role='admin'")->fetchColumn() !== 0) throw new RuntimeException('An Admin already exists. Use the protected Users & roles page.');
    $q = $db->prepare("SELECT id FROM users WHERE email=? AND verified_at IS NOT NULL AND status='active'");$q->execute([$email]);$id=$q->fetchColumn();
    if (!$id) throw new RuntimeException('Register and verify this account first. No account was changed.');
    $db->prepare("UPDATE users SET role='admin',session_version=session_version+1 WHERE id=?")->execute([$id]);
    $db->prepare("INSERT INTO audit_log(actor_id,action,target_type,target_id,details,created_at) VALUES(NULL,'bootstrap_admin','user',?,'Server console bootstrap',?)")->execute([$id,time()]);
    $db->exec('COMMIT');
    echo "First Admin promoted. Log out, then log in again at /auth.php.\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR,$e->getMessage()."\n"); exit(1);
}
