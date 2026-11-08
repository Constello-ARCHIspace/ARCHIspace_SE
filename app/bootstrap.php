<?php
declare(strict_types=1);
require_once __DIR__ . '/Auth.php';
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
} catch (Throwable $error) { http_response_code(503); exit('Account service unavailable. Please contact the administrator.'); }
$auth = new Auth($db, function(string $email, string $token) use ($config, $base): void {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $smtp = $config['smtp'];
    if (!in_array($smtp['encryption'], ['tls','ssl'], true)) throw new RuntimeException('SMTP TLS required');
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP(); $mail->SMTPDebug = 0; $mail->Timeout = 15;
    $mail->Host = $smtp['host']; $mail->Port = (int)$smtp['port'];
    $mail->SMTPAuth = true; $mail->Username = $smtp['username']; $mail->Password = $smtp['password'];
    $mail->SMTPSecure = $smtp['encryption']; $mail->CharSet = 'UTF-8';
    $mail->setFrom($smtp['from_email'], $smtp['from_name']); $mail->addAddress($email);
    $link = $base . '/auth.php?view=verify&token=' . rawurlencode($token);
    $mail->Subject = 'Verify your ARCHIspace email';
    $mail->Body = "Welcome to ARCHIspace!\n\nConfirm your email to start designing:\n" . $link . "\n\nThis link expires in one hour and can be used once. If you did not register, ignore this email.";
    $mail->send();
});
function esc(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrfCheck(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Your form expired. Reload the page and try again.');
    }
}
function requireUser(bool $json = false): array {
    global $db;
    $q = $db->prepare('SELECT id,public_id,name,email FROM users WHERE id=? AND verified_at IS NOT NULL');
    $q->execute([$_SESSION['user_id'] ?? 0]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        if ($json) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['error'=>'Sign in to continue.']); }
        else header('Location: auth.php');
        exit;
    }
    return $user;
}
