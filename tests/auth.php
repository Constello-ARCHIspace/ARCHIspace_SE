<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Auth.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec(file_get_contents(dirname(__DIR__) . '/app/schema.sql'));
$emails = [];
$auth = new Auth($db, function($email,$token) use (&$emails) { $emails[] = [$email,$token]; });
$checks = 0;
function check(bool $value, string $label): void { global $checks; if (!$value) throw new RuntimeException('FAIL: '.$label); $checks++; echo "PASS: $label\n"; }
function rejects(Closure $call, string $label): void { try { $call(); } catch (InvalidArgumentException | DomainException $e) { check(true,$label); return; } throw new RuntimeException('FAIL: '.$label); }
$password = 'A long test passphrase 123!';
$auth->register('Test Student', ' Student@example.com ', $password);
check($emails[0][0] === 'student@example.com','email normalization');
$hash = $db->query('SELECT password_hash FROM users')->fetchColumn();
check($hash !== $password && password_verify($password,$hash),'password hashing');
rejects(fn()=>$auth->login('student@example.com',$password),'unverified account cannot log in');
$auth->register('Attacker','STUDENT@example.com','Another long passphrase!');
check((int)$db->query('SELECT count(*) FROM users')->fetchColumn() === 1 && count($emails)===1,'duplicate email does not overwrite account or send mail');
check($db->query('SELECT token_hash FROM verification_tokens')->fetchColumn() !== $emails[0][1],'verification token stored as hash');
check(!$auth->verify('invalid'),'malformed token rejected');
check($auth->verify($emails[0][1]),'valid verification succeeds');
check(!$auth->verify($emails[0][1]),'verification token cannot be replayed');
check($auth->login('student@example.com',$password)['name'] === 'Test Student','verified login succeeds');
rejects(fn()=>$auth->login('student@example.com','wrong'),'wrong password rejected');
rejects(fn()=>$auth->login('missing@example.com',$password),'unknown account rejected');
rejects(fn()=>$auth->register('Test','x@example.com','short'),'short password rejected');
rejects(fn()=>$auth->register('Test','x@example.com',str_repeat('x',73)),'long password rejected before bcrypt truncation');
$auth->register('Next Student','next@example.com',$password);
$expired = $emails[array_key_last($emails)][1];
$db->exec('UPDATE verification_tokens SET expires_at=0');
check(!$auth->verify($expired),'expired verification rejected');
$auth->resend('next@example.com');
check($auth->verify($emails[array_key_last($emails)][1]),'resend allows verification');
$before = count($emails); $auth->resend('missing@example.com'); $auth->resend('next@example.com');
check(count($emails)===$before,'resend does not email missing or verified users');
$auth->limit('test',2,900); $auth->limit('test',2,900);
rejects(fn()=>$auth->limit('test',2,900),'rate limit enforced');
$db->exec('UPDATE rate_limits SET expires_at=0'); $auth->limit('test',2,900);
check(true,'expired rate limit resets');
$failing = new Auth($db, function() { throw new RuntimeException('SMTP failure'); });
try { $failing->register('Mail Failure','failure@example.com',$password); throw new LogicException('Expected delivery failure'); } catch (RuntimeException $e) { check(str_contains($e->getMessage(),'delivery'),'delivery failure is reported'); }
check((int)$db->query("SELECT count(*) FROM verification_tokens JOIN users ON users.id=user_id WHERE email='failure@example.com'")->fetchColumn()===0,'failed delivery token removed');
$auth->resend('failure@example.com');
check($auth->verify($emails[array_key_last($emails)][1]),'pending account recoverable after email failure');
echo "$checks checks passed.\n";
