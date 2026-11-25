<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
$view = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'login';
if (!in_array($view, ['login','register','resend','verify','forgot','reset'], true)) $view = 'login';
if ($view === 'reset' && isset($_GET['token'])) {
    $token = is_string($_GET['token']) ? $_GET['token'] : '';
    $_SESSION['reset_token'] = preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : '';
    // Remove the secret from the address bar. GET never consumes the token.
    header('Location: auth.php?view=reset', true, 303); exit;
}
if (isset($_SESSION['user_id']) && $view !== 'reset') { header('Location: ' . accountHome(requireUser())); exit; }
$error = ''; $message = $_SESSION['notice'] ?? ''; unset($_SESSION['notice']);
$email = $view === 'verify' ? ($_SESSION['verify_email'] ?? '') : ''; $firstName = ''; $middleName = ''; $lastName = ''; $birthdate = ''; $phone = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    try {
        foreach (['email','first_name','middle_name','last_name','phone','birthdate','password','confirmation','code'] as $key) {
            if (isset($_POST[$key]) && !is_string($_POST[$key])) throw new InvalidArgumentException('Invalid form input.');
        }
        $email = trim($_POST['email'] ?? '');
        $firstName = $_POST['first_name'] ?? '';
        $middleName = $_POST['middle_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $birthdate = $_POST['birthdate'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; // Never trust client-provided proxy headers.
        $auth->limit('ip:' . $view . ':' . $ip, $view === 'login' ? 30 : 10, 900);
        if ($view !== 'reset') {
            $email = Auth::email($email);
            // The verify limit is what makes a 6-digit code unguessable: at most 5 tries per 15-minute code.
            $auth->limit('email:' . $view . ':' . $email, ['login'=>10, 'verify'=>5][$view] ?? 3, 900);
        }
        if ($view === 'register') {
            if (($_POST['password'] ?? '') !== ($_POST['confirmation'] ?? '')) throw new InvalidArgumentException('Passwords do not match.');
            $auth->register($firstName, $lastName, $email, $_POST['password'] ?? '', $phone, $birthdate, $middleName);
            $_SESSION['verify_email'] = $email;
            $_SESSION['notice'] = 'Account created. We sent a 6-digit code to ' . $email . '. Check your inbox and spam folder.';
            header('Location: auth.php?view=verify', true, 303); exit;
        } elseif ($view === 'forgot') {
            $auth->requestReset($email);
            $_SESSION['notice'] = 'If an account uses this address, a password reset link has been sent. Check your inbox and spam folder.';
            header('Location: auth.php?view=forgot', true, 303); exit;
        } elseif ($view === 'reset') {
            if (($_POST['password'] ?? '') !== ($_POST['confirmation'] ?? '')) throw new InvalidArgumentException('Passwords do not match.');
            if (!$auth->resetPassword($_SESSION['reset_token'] ?? '', $_POST['password'] ?? '')) throw new DomainException('This reset link is invalid, expired, or already used. Request a new link below.');
            unset($_SESSION['reset_token']);
            $_SESSION['notice'] = 'Password updated. Log in with your new password.';
            header('Location: auth.php', true, 303); exit;
        } elseif ($view === 'resend') {
            $auth->resend($email);
            $_SESSION['verify_email'] = $email;
            $_SESSION['notice'] = 'If this account still needs verifying, a new 6-digit code has been sent. Earlier codes no longer work.';
            header('Location: auth.php?view=verify', true, 303); exit;
        } elseif ($view === 'verify') {
            if (!$auth->verify($email, $_POST['code'] ?? '')) throw new DomainException('That code is incorrect or has expired. Check the latest email, or send a new code below.');
            unset($_SESSION['verify_email']);
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
        $error = 'Account or email service is temporarily unavailable. Please try again later.';
        http_response_code(503);
    }
}
$titles = ['login'=>'Sign in to ARCHIspace', 'register'=>'Create your account', 'resend'=>'Send a new verification code', 'verify'=>'Verify your email', 'forgot'=>'Forgot password', 'reset'=>'Choose a new password'];
$subtitles =['login'=>'Enter your account details to open your workspace.', 'register'=>'Use an email address you can verify to start planning.', 'resend'=>'Enter the email address you used to create your account.', 'verify'=>'Enter the 6-digit code we emailed you to activate your account.', 'forgot'=>'Enter your account email and we’ll send you a link to set a new password.', 'reset'=>'Your new password signs you out everywhere else.'];
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= esc($titles[$view]) ?> | ARCHIspace</title><link rel="stylesheet" href="assets/fonts.css"><link rel="stylesheet" href="assets/theme.css"><link rel="stylesheet" href="assets/auth.css"><script src="assets/auth.js" defer></script><script defer src="assets/vendor/three.min.js"></script><script defer src="assets/vendor/gsap.min.js"></script><script defer src="assets/auth-scene.js"></script></head>
<body><main class="shell">
<?php require dirname(__DIR__) . '/app/auth-story.php'; ?>
<section class="form-panel <?= $view === 'register' ? 'registration' : '' ?>">
<div class="form-wrap">
<?php if ($view === 'login' || $view === 'register'): ?>
<nav class="account-nav" aria-label="Account access">
    <a href="auth.php" <?= $view === 'login' ? 'aria-current="page"' : '' ?>>Log in</a>
    <a href="auth.php?view=register" <?= $view === 'register' ? 'aria-current="page"' : '' ?>>Create account</a>
</nav>
<?php endif; ?>
<div class="form-heading"><h1><?= esc($titles[$view]) ?></h1><p class="intro"><?= esc($subtitles[$view]) ?></p></div>
<?php if ($error): ?><div class="notice error" role="alert"><?= esc($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="notice" role="status"><?= esc($message) ?></div><?php endif; ?>
<form method="post" action="auth.php?view=<?= esc($view) ?>"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
<?php if ($view === 'register'): ?>
<div class="name-row">
<div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" autocomplete="given-name" maxlength="80" value="<?= esc($firstName) ?>" required></div>
<div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" autocomplete="family-name" maxlength="80" value="<?= esc($lastName) ?>" required></div>
</div>
<div class="field"><label for="middle_name">Middle name <span>(optional)</span></label><input id="middle_name" name="middle_name" autocomplete="additional-name" maxlength="80" value="<?= esc($middleName) ?>"></div>
<div class="birth-row">
    <div class="field"><label for="birthdate">Date of birth</label><input type="date" id="birthdate" name="birthdate" autocomplete="bday" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= esc($birthdate) ?>" required aria-describedby="age-help"></div>
    <div class="field"><label for="age">Age</label><output id="age" for="birthdate" aria-live="polite">—</output></div>
</div>
<p class="hint" id="age-help">You must be at least 18 to create an account.</p>
<?php endif; ?>
<?php if ($view !== 'reset'): ?><div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" autocomplete="email" maxlength="254" placeholder="you@example.com" value="<?= esc($email) ?>" required></div><?php endif; ?>
<?php if ($view === 'verify'): ?><div class="field"><label for="code">Verification code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="123456" aria-describedby="code-help" required autofocus><p id="code-help" class="hint">6 digits, valid for 15 minutes.</p></div><?php endif; ?>
<?php if ($view === 'register'): ?>
<div class="field"><label for="phone">Phone number</label><input type="tel" id="phone" name="phone" autocomplete="tel" maxlength="24" placeholder="0917 123 4567" value="<?= esc($phone) ?>" required></div>
<?php endif; ?>
<?php $newPassword = $view === 'register' || $view === 'reset'; ?>
<?php if ($newPassword || $view === 'login'): ?><div class="field"><label for="password"><?= $view === 'reset' ? 'New password' : 'Password' ?></label><div class="password-field"><input id="password" type="password" name="password" autocomplete="<?= $newPassword ? 'new-password' : 'current-password' ?>" <?= $newPassword ? 'minlength="15" aria-describedby="password-help"' : '' ?> required><button type="button" class="reveal" data-toggle="password" aria-label="Show password" aria-pressed="false"><?= $view === 'login' ? 'Show password' : 'Show' ?></button></div></div>
<?php if ($newPassword): ?><p id="password-help" class="hint">Use at least 15 characters, up to 72 bytes. <span id="password-count" class="pw-status" aria-live="polite"></span></p><div class="field"><label for="confirmation">Confirm password</label><div class="password-field"><input id="confirmation" type="password" name="confirmation" autocomplete="new-password" aria-describedby="password-match" required><button type="button" class="reveal" data-toggle="confirmation" aria-label="Show confirmation password" aria-pressed="false">Show</button></div><p id="password-match" class="hint pw-status" aria-live="polite"></p></div>
<?php endif; ?>
<?php endif; ?>
<button class="submit" type="submit"><span><?= ['login'=>'Log in', 'register'=>'Create account', 'resend'=>'Send new code', 'verify'=>'Verify my email', 'forgot'=>'Send reset link', 'reset'=>'Save new password'][$view] ?></span><img width="16" height="16" src="assets/figma/login-imgArrowRight.svg" alt=""></button></form>
<?php if ($view === 'login'): ?><p class="return-link"><a href="auth.php?view=forgot">Forgot password?</a> · <a href="auth.php?view=verify">Enter verification code</a></p>
<?php elseif ($view === 'register'): ?><p class="next-step">We’ll email you a 6-digit code to verify before your first login.</p>
<?php else: ?><p class="return-link"><a href="auth.php">Back to log in</a><?php if ($view === 'verify'): ?> · <a href="auth.php?view=resend">Didn’t get a code? Send a new one</a><?php elseif ($view === 'reset'): ?> · <a href="auth.php?view=forgot">Request a new link</a><?php endif; ?></p><?php endif; ?>
</div>
<footer class="account-footer"><span>© <?= date('Y') ?> Archispace</span><button type="button" id="accountHelp">Privacy &amp; help</button></footer>
</section></main>
<dialog id="helpDialog"><div class="dialog-heading"><h2>Your account &amp; privacy</h2><button type="button" id="closeHelp" aria-label="Close help">×</button></div><p>ARCHIspace stores your name, email, phone number, date of birth, hashed password, and verification status on the server. Working plans stay in this browser. Submitted review snapshots and uploaded blueprint images are stored on the server. Admins and assigned reviewers can access submitted designs.</p><p>Verification codes expire after 15 minutes; password reset links after one hour. Use “Forgot password?” on the login page to set a new password; it also verifies an account that is still pending. Your session ends after 30 minutes of inactivity or 8 hours in total.</p><p>This concept-planning tool does not replace professional architecture or engineering advice. For account assistance, contact your project administrator.</p></dialog>
</body></html>
