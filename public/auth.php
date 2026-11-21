<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
$view = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'login';
if (!in_array($view, ['login','register','resend','verify'], true)) $view = 'login';
if ($view === 'verify' && isset($_GET['token'])) {
    $token = is_string($_GET['token']) ? $_GET['token'] : '';
    $_SESSION['verification_token'] = preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : '';
    // Remove the secret from the address bar. GET never consumes the token.
    header('Location: auth.php?view=verify', true, 303); exit;
}
if (isset($_SESSION['user_id']) && $view !== 'verify') { header('Location: ' . accountHome(requireUser())); exit; }
$error = ''; $message = $_SESSION['notice'] ?? ''; unset($_SESSION['notice']);
$email = ''; $firstName = ''; $middleName = ''; $lastName = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    try {
        foreach (['email','first_name','middle_name','last_name','password','confirmation'] as $key) {
            if (isset($_POST[$key]) && !is_string($_POST[$key])) throw new InvalidArgumentException('Invalid form input.');
        }
        $email = trim($_POST['email'] ?? '');
        $firstName = $_POST['first_name'] ?? '';
        $middleName = $_POST['middle_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; // Never trust client-provided proxy headers.
        $auth->limit('ip:' . $view . ':' . $ip, $view === 'login' ? 30 : 10, 900);
        if ($view !== 'verify') {
            $email = Auth::email($email);
            $auth->limit('email:' . $view . ':' . $email, $view === 'login' ? 10 : 3, 900);
        }
        if ($view === 'register') {
            if (($_POST['password'] ?? '') !== ($_POST['confirmation'] ?? '')) throw new InvalidArgumentException('Passwords do not match.');
            $auth->register($firstName, $lastName, $email, $_POST['password'] ?? '', $middleName);
            $_SESSION['notice'] = 'If this address is eligible, a verification email has been sent. Check your inbox and spam folder. Already registered? Sign in or resend verification.';
            header('Location: auth.php?view=resend', true, 303); exit;
        } elseif ($view === 'resend') {
            $auth->resend($email);
            $_SESSION['notice'] = 'If an unverified account exists, a new verification link has been sent. Check your inbox and spam folder.';
            header('Location: auth.php?view=resend', true, 303); exit;
        } elseif ($view === 'verify') {
            if (!$auth->verify($_SESSION['verification_token'] ?? '')) throw new DomainException('This link is invalid, expired, or already used. Request a new link below.');
            unset($_SESSION['verification_token']);
            $_SESSION['notice'] = 'Email verified. Sign in to start designing.';
            header('Location: auth.php', true, 303); exit;
        } else {
            $user = $auth->login($email, $_POST['password'] ?? '');
            $pendingInvitation = $_SESSION['invitation_token'] ?? null;
            $_SESSION = []; session_regenerate_id(true);
            if ($pendingInvitation) $_SESSION['invitation_token'] = $pendingInvitation;
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['session_version'] = (int)$user['session_version'];
            $_SESSION['signed_in'] = $_SESSION['last_seen'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: ' . (isset($_SESSION['invitation_token']) ? 'invite.php' : accountHome($user)), true, 303); exit;
        }
    } catch (InvalidArgumentException | DomainException $e) {
        $error = $e->getMessage();
        if (str_starts_with($error, 'Too many')) { http_response_code(429); header('Retry-After: 900'); }
    } catch (RuntimeException $e) {
        $error = 'Account or email service is temporarily unavailable. Please try again later or use Resend verification.';
        http_response_code(503);
    }
}
$titles = ['login'=>'Welcome back.', 'register'=>'Create your workspace.', 'resend'=>'Check your inbox.', 'verify'=>'One step closer.'];
$subtitles = ['login'=>'Pick up where your last idea left off.', 'register'=>'A place to explore, review and refine your ideas.', 'resend'=>'Enter your email to request a new verification link.', 'verify'=>'Confirm your email address to activate your account.'];
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= esc($titles[$view]) ?> | ARCHIspace</title><link rel="stylesheet" href="assets/fonts.css"><link rel="stylesheet" href="assets/auth.css"><script src="assets/auth.js" defer></script></head>
<body><main class="shell">
<?php require dirname(__DIR__) . '/app/auth-story.php'; ?>
<section class="form-panel <?= $view === 'register' ? 'registration' : '' ?>">
<nav class="account-nav" aria-label="Account navigation"><span><?= $view === 'login' ? 'New to Archispace?' : 'Already have an account?' ?></span><a class="outline-button" href="<?= $view === 'login' ? 'auth.php?view=register' : 'auth.php' ?>"><?= $view === 'login' ? 'Create an account' : 'Log in' ?></a></nav>
<div class="form-wrap"><div class="form-heading"><p class="eyebrow"><?= $view === 'register' ? 'START WITH A BETTER PLAN' : 'YOUR ARCHITECTURAL WORKSPACE' ?></p><h1><?= esc($titles[$view]) ?></h1><p class="intro"><?= esc($subtitles[$view]) ?></p></div>
<?php if ($error): ?><div class="notice error" role="alert"><?= esc($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="notice" role="status"><?= esc($message) ?></div><?php endif; ?>
<form method="post" action="auth.php?view=<?= esc($view) ?>"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
<?php if ($view === 'register'): ?>
<div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" autocomplete="given-name" maxlength="80" value="<?= esc($firstName) ?>" required></div>
<div class="field"><label for="middle_name">Middle name (optional)</label><input id="middle_name" name="middle_name" autocomplete="additional-name" maxlength="80" value="<?= esc($middleName) ?>"></div>
<div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" autocomplete="family-name" maxlength="80" value="<?= esc($lastName) ?>" required></div>
<?php endif; ?>
<?php if ($view !== 'verify'): ?><div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" autocomplete="email" maxlength="254" placeholder="you@example.com" value="<?= esc($email) ?>" required></div><?php endif; ?>
<?php if (in_array($view, ['login','register'], true)): ?><div class="field"><label for="password">Password</label><div class="password-field"><input id="password" type="password" name="password" autocomplete="<?= $view === 'register' ? 'new-password' : 'current-password' ?>" <?= $view === 'register' ? 'minlength="15" aria-describedby="password-help"' : '' ?> required><button type="button" class="reveal" data-toggle="password" aria-label="Show password" aria-pressed="false"><?= $view === 'login' ? 'Show password' : 'Show' ?></button></div></div>
<?php if ($view === 'register'): ?><p id="password-help" class="hint">Use at least 15 characters, up to 72 bytes. A memorable passphrase works well. <span id="password-count" class="pw-status" aria-live="polite"></span></p><div class="field"><label for="confirmation">Confirm password</label><div class="password-field"><input id="confirmation" type="password" name="confirmation" autocomplete="new-password" required><button type="button" class="reveal" data-toggle="confirmation" aria-label="Show confirmation password" aria-pressed="false">Show</button></div><p id="password-match" class="hint pw-status" aria-live="polite"></p></div>
<div class="account-note"><span>USER ACCOUNT</span><p>Create your own planning workspace. Verify your email before your first login.</p></div>
<?php else: ?><div class="login-options"><span class="session-note">Private account access</span><a href="auth.php?view=resend">Resend verification email</a></div><?php endif; ?>
<?php endif; ?>
<button class="submit" type="submit"><img src="assets/figma/login-imgArrowRight.svg" alt=""><span><?= ['login'=>'Log in', 'register'=>'Create account', 'resend'=>'Send verification link', 'verify'=>'Verify my email'][$view] ?></span></button></form>
<?php if ($view === 'login'): ?><p class="guidance"><img src="assets/figma/login-imgCornerDownRight.svg" alt="">Return straight to your design workspace after signing in.</p><p class="account-info">Use your verified account to access your workspace. Your house plans are saved in this browser; blueprint images are stored on the server.</p>
<?php elseif ($view === 'register'): ?><p class="hint next-step">Next: verify your email, then start planning your space.</p>
<?php else: ?><p class="return-link"><a href="auth.php">Back to log in</a><?php if ($view === 'verify'): ?> · <a href="auth.php?view=resend">Request a new link</a><?php endif; ?></p><?php endif; ?>
</div>
<footer class="account-footer"><span>© <?= date('Y') ?> Archispace</span><button type="button" id="accountHelp">Privacy &amp; help</button></footer>
</section></main>
<dialog id="helpDialog"><div class="dialog-heading"><h2>Your account &amp; privacy</h2><button type="button" id="closeHelp" aria-label="Close help">×</button></div><p>ARCHIspace stores your name, email, hashed password, and verification status on the server. Working plans stay in this browser. Submitted review snapshots and uploaded blueprint images are stored on the server. Admins and assigned reviewers can access submitted designs.</p><p>Email links expire after one hour. Use “Resend verification email” if you need a new link. Your session ends after 30 minutes of inactivity or 8 hours in total.</p><p>This concept-planning tool does not replace professional architecture or engineering advice. For account assistance, contact your project administrator.</p></dialog>
</body></html>
