<?php
declare(strict_types=1);
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Admin.php';
require_once __DIR__ . '/Mailer.php';
$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) { http_response_code(503); exit('ARCHIspace setup required. Follow README.md to create config.php and initialize the database.'); }
$config = require $configPath;
$base = rtrim($config['base_url'], '/');
$url = parse_url($base);
$production = ($config['environment'] ?? 'production') !== 'local';
if (!$url || !isset($url['host']) || !in_array($url['scheme'] ?? '', ['https', 'http'], true) || ($production && $url['scheme'] !== 'https')) {
    http_response_code(503); exit('Invalid application URL configuration.');
}
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('archispace_session');
session_set_cookie_params(['lifetime'=>0, 'path'=>($url['path'] ?? '') . '/', 'secure'=>$url['scheme']==='https', 'httponly'=>true, 'samesite'=>'Lax']);
session_start();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
if ($production) header('Strict-Transport-Security: max-age=31536000');
if (isset($_SESSION['user_id']) && (time() - ($_SESSION['last_seen'] ?? 0) > 1800 || time() - ($_SESSION['signed_in'] ?? 0) > 28800)) {
    $_SESSION = []; session_regenerate_id(true);
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
if (isset($_SESSION['user_id'])) $_SESSION['last_seen'] = time();
try {
    if (!is_file($config['database'])) throw new RuntimeException('Database not initialized');
    $db = new PDO('sqlite:' . $config['database'], null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    $db->query('SELECT role,status,session_version FROM users LIMIT 0');
    if (!$db->query('SELECT version FROM app_migrations WHERE version=6')->fetchColumn()) throw new RuntimeException('Database migration required');
} catch (Throwable $error) { http_response_code(503); exit('Account service unavailable. The administrator should check configuration and run php bin/migrate.php after upgrading.'); }
$auth = new Auth($db, function(string $email, string $token, string $purpose = 'verify') use ($config, $base): void {
    if ($purpose === 'reset') {
        $link = $base . '/auth.php?view=reset&token=' . rawurlencode($token);
        sendAppMail($config,$email,'Reset your ARCHIspace password',"We received a request to reset your ARCHIspace password.\n\nChoose a new password here:\n" . $link . "\n\nThis link expires in one hour and can be used once. If you did not ask for this, ignore this email; your password stays the same.");
        return;
    }
    sendAppMail($config,$email,"$token is your ARCHIspace verification code","Welcome to ARCHIspace!\n\nEnter this code on the verification page to confirm your email:\n\n    $token\n\nThe code expires in 15 minutes and can be used once. If you did not register, ignore this email.");
});
function esc(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrfCheck(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        $back = esc((string)($_SERVER['REQUEST_URI'] ?? 'auth.php'));
        exit('<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Form expired | ARCHIspace</title><link rel="stylesheet" href="assets/fonts.css"><link rel="stylesheet" href="assets/theme.css"><link rel="stylesheet" href="assets/portal.css"><main class="portal-main narrow"><section class="panel"><h1>Your form expired.</h1><p>This usually happens when a page stays open for a long time or is opened from an old tab. Nothing was saved.</p><a class="button primary" href="' . $back . '">Reload the form</a></section></main></html>');
    }
}
function requireUser(bool $json = false): array {
    global $db;
    $q = $db->prepare("SELECT id,public_id,name,first_name,email,role,status,session_version FROM users WHERE id=? AND verified_at IS NOT NULL AND status='active'");
    $q->execute([$_SESSION['user_id'] ?? 0]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int)$user['session_version'] !== (int)($_SESSION['session_version'] ?? -1)) {
        $_SESSION = []; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
        if ($json) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['error'=>'Sign in to continue.']); }
        else header('Location: auth.php');
        exit;
    }
    return $user;
}

function requireStaff(bool $adminOnly=false): array {
    $u=requireUser();
    if(!in_array($u['role'],$adminOnly?['admin']:['admin','sub_admin'],true)){
        http_response_code(403);exit('Access denied. This page is restricted to authorized staff.');
    }
    return $u;
}
function accountHome(array $user): string { return $user['role']==='admin'?'admin.php':($user['role']==='sub_admin'?'subadmin.php':'dashboard.php'); }
function sensitiveAction(array $actor,string $password): void {
    global $auth,$db;
    $auth->limit('reauth:user:'.$actor['id'],5,900);
    $auth->limit('reauth:ip:'.($_SERVER['REMOTE_ADDR']??'unknown'),20,900);
    $q=$db->prepare("SELECT password_hash FROM users WHERE id=? AND status='active'");$q->execute([$actor['id']]);$hash=$q->fetchColumn();
    if(!$hash||!password_verify($password,$hash))throw new DomainException('Your current password is required to authorize this change.');
    session_regenerate_id(true);
}
