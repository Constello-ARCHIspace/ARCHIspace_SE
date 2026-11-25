<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Auth.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec(file_get_contents(dirname(__DIR__) . '/app/schema.sql'));
$emails = [];
$auth = new Auth($db, function($email,$token,$purpose) use (&$emails) { $emails[] = [$email,$token,$purpose]; });
$checks = 0;
function check(bool $value, string $label): void { global $checks; if (!$value) throw new RuntimeException('FAIL: '.$label); $checks++; echo "PASS: $label\n"; }
function rejects(Closure $call, string $label): void { try { $call(); } catch (InvalidArgumentException | DomainException $e) { check(true,$label); return; } throw new RuntimeException('FAIL: '.$label); }
$password = 'A long test passphrase 123!';
$phone = '+63 (917) 123-4567';
$birthday = new DateTimeImmutable('today');
$birthdate = $birthday->modify('-18 years')->format('Y-m-d');
if (substr($birthdate, 5) > $birthday->format('m-d')) $birthdate = substr($birthdate, 0, 4).'-02-28';
$auth->register(' Test ', ' Student ', ' Student@example.com ', $password, $phone, $birthdate);
check($db->query('SELECT phone,birthdate FROM users')->fetch(PDO::FETCH_NUM) === ['+639171234567', $birthdate], 'adult registration stores normalized phone and birthdate');
foreach (['', '123456', '1234567890123456', '+63 call me'] as $invalidPhone) {
    rejects(fn()=>$auth->register('Test','Student','x@example.com',$password,$invalidPhone,$birthdate), 'missing or malformed phone rejected');
}
foreach (['', '2001-02-29', "2000-01-01\0", $birthday->modify('+1 day')->format('Y-m-d'), $birthday->modify('-17 years')->format('Y-m-d')] as $invalidDate) {
    rejects(fn()=>$auth->register('Test','Student','x@example.com',$password,$phone,$invalidDate), 'missing, invalid or ineligible birthdate rejected');
}
check($emails[0][0] === 'student@example.com','email normalization');
check($db->query('SELECT first_name,middle_name,last_name FROM users')->fetch(PDO::FETCH_NUM) === ['Test', null, 'Student'], 'required names are trimmed and omitted middle name is NULL');
rejects(fn()=>$auth->register(" \t", 'Student', 'x@example.com', $password, $phone, $birthdate), 'blank first name rejected');
rejects(fn()=>$auth->register('Test', "\u{00a0}", 'x@example.com', $password, $phone, $birthdate), 'blank last name rejected');
rejects(fn()=>$auth->register('Test', 'Student', 'x@example.com', $password, $phone, $birthdate, str_repeat('x',81)), 'overlong middle name rejected');
$hash = $db->query('SELECT password_hash FROM users')->fetchColumn();
check($hash !== $password && password_verify($password,$hash),'password hashing');
rejects(fn()=>$auth->login('student@example.com',$password),'unverified account cannot log in');
rejects(fn()=>$auth->register('Duplicate','Applicant','STUDENT@example.com','Another long passphrase!', $phone, $birthdate), 'duplicate email registration rejected');
check((int)$db->query('SELECT count(*) FROM users')->fetchColumn() === 1 && count($emails)===1,'duplicate email does not overwrite account or send mail');
check($db->query('SELECT token_hash FROM verification_tokens')->fetchColumn() !== $emails[0][1],'verification token stored as hash');
check(!$auth->verify('student@example.com','abc123'),'malformed code rejected');
check(!$auth->verify('student@example.com',sprintf('%06d',($emails[0][1]+1)%1000000)),'wrong code rejected');
check(preg_match('/^\d{6}$/D',$emails[0][1])===1,'verification email carries a 6-digit code');
check($auth->verify('student@example.com',$emails[0][1]),'valid verification succeeds');
check(!$auth->verify('student@example.com',$emails[0][1]),'verification token cannot be replayed');
check($auth->login('student@example.com',$password)['name'] === 'Test Student','verified login succeeds');
rejects(fn()=>$auth->login('student@example.com','wrong'),'wrong password rejected');
rejects(fn()=>$auth->login('missing@example.com',$password),'unknown account rejected');
rejects(fn()=>$auth->register('Test','Student','x@example.com','short', $phone, $birthdate),'short password rejected');
rejects(fn()=>$auth->register('Test','Student','x@example.com',str_repeat('x',73), $phone, $birthdate),'long password rejected before bcrypt truncation');
$auth->register('María José', 'de la Cruz', 'next@example.com', $password, $phone, $birthdate, ' A ');
check($db->query("SELECT first_name,middle_name,last_name,name FROM users WHERE email='next@example.com'")->fetch(PDO::FETCH_NUM) === ['María José', 'A', 'de la Cruz', 'María José A de la Cruz'], 'compound names and optional middle name round-trip');
$expired = $emails[array_key_last($emails)][1];
$db->exec('UPDATE verification_tokens SET expires_at=0');
check(!$auth->verify('next@example.com',$expired),'expired verification rejected');
$auth->resend('next@example.com');
check($auth->verify('next@example.com',$emails[array_key_last($emails)][1]),'resend allows verification');
$before = count($emails); $auth->resend('missing@example.com'); $auth->resend('next@example.com');
check(count($emails)===$before,'resend does not email missing or verified users');
$auth->limit('test',2,900); $auth->limit('test',2,900);
rejects(fn()=>$auth->limit('test',2,900),'rate limit enforced');
$db->exec('UPDATE rate_limits SET expires_at=0'); $auth->limit('test',2,900);
check(true,'expired rate limit resets');
$failing = new Auth($db, function() { throw new RuntimeException('SMTP failure'); });
try { $failing->register('Mail','Failure','failure@example.com',$password,$phone,$birthdate," \t "); throw new LogicException('Expected delivery failure'); } catch (RuntimeException $e) { check(str_contains($e->getMessage(),'delivery'),'delivery failure is reported'); }
check($db->query("SELECT middle_name FROM users WHERE email='failure@example.com'")->fetchColumn() === null, 'blank optional name is stored as NULL');
check((int)$db->query("SELECT count(*) FROM verification_tokens JOIN users ON users.id=user_id WHERE email='failure@example.com'")->fetchColumn()===0,'failed delivery token removed');
$auth->resend('failure@example.com');
check($auth->verify('failure@example.com',$emails[array_key_last($emails)][1]),'pending account recoverable after email failure');
$newPassword = 'A brand new passphrase 456!';
$before = count($emails); $auth->requestReset('missing@example.com');
check(count($emails)===$before,'reset request for unknown email sends nothing');
$auth->requestReset('Student@example.com'); $old = $emails[array_key_last($emails)][1];
$auth->requestReset('student@example.com'); $reset = $emails[array_key_last($emails)];
check($reset[0]==='student@example.com' && $reset[2]==='reset','reset email sent with reset purpose');
check(!$auth->resetPassword($old,$newPassword),'older reset link invalidated by newer request');
rejects(fn()=>$auth->resetPassword($reset[1],'short'),'weak reset password rejected');
check($auth->resetPassword($reset[1],$newPassword),'valid reset succeeds');
check(!$auth->resetPassword($reset[1],$newPassword),'reset link cannot be replayed');
rejects(fn()=>$auth->login('student@example.com',$password),'old password stops working after reset');
check($auth->login('student@example.com',$newPassword)['session_version']==1,'new password works and other sessions are revoked');
$auth->register('Pending','User','pending@example.com',$password,$phone,$birthdate);
$auth->requestReset('pending@example.com');
$auth->resetPassword($emails[array_key_last($emails)][1],$newPassword);
check($auth->login('pending@example.com',$newPassword)['verified_at'] !== null,'reset verifies a pending account');
echo "$checks checks passed.\n";
