<?php
declare(strict_types=1);

final class Auth
{
    public function __construct(private PDO $db, private Closure $send) {}

    public static function email(string $email): string
    {
        // Explicit case-insensitive identity policy; do not strip dots or plus tags.
        $email = strtolower(trim($email));
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        return $email;
    }

    public function limit(string $bucket, int $maximum, int $seconds): void
    {
        $key = hash('sha256', $bucket);
        $now = time();
        $q = $this->db->prepare('INSERT INTO rate_limits(bucket,count,expires_at) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET count=CASE WHEN expires_at<=? THEN 1 ELSE count+1 END, expires_at=CASE WHEN expires_at<=? THEN excluded.expires_at ELSE expires_at END RETURNING count');
        $q->execute([$key, $now + $seconds, $now, $now]);
        $count = (int)$q->fetchColumn();
        $q->closeCursor();
        if ($count > $maximum) throw new DomainException('Too many attempts. Please try again later.');
    }

    private static function namePart(string $value, string $label, bool $required): string
    {
        $normalized = preg_replace('/\s+/u', ' ', $value);
        if ($normalized === null || str_contains($normalized, "\0")) throw new InvalidArgumentException("Enter a valid $label.");
        $value = trim($normalized);
        if ($required && $value === '') throw new InvalidArgumentException("Enter your $label.");
        if (mb_strlen($value) > 80) throw new InvalidArgumentException("Use up to 80 characters for your $label.");
        return $value;
    }

    public function register(string $firstName, string $lastName, string $email, string $password, ?string $middleName = null): void
    {
        $firstName = self::namePart($firstName, 'first name', true);
        $lastName = self::namePart($lastName, 'last name', true);
        $middleName = self::namePart($middleName ?? '', 'middle name', false);
        $email = self::email($email);
        // 72-byte ceiling prevents bcrypt truncation on PHP installations without Argon2.
        if (mb_strlen($password) < 15 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new InvalidArgumentException('Use at least 15 characters and no more than 72 bytes for your password.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $q = $this->db->prepare('INSERT INTO users(public_id,first_name,middle_name,last_name,email,password_hash,created_at) VALUES(?,?,?,?,?,?,?) ON CONFLICT(email) DO NOTHING');
        $q->execute([bin2hex(random_bytes(16)), $firstName, $middleName === '' ? null : $middleName, $lastName, $email, $hash, time()]);
        // Existing registrations never overwrite a password or trigger another email.
        if ($q->rowCount() === 1) $this->sendVerification((int)$this->db->lastInsertId(), $email);
    }

    public function resend(string $email): void
    {
        $email = self::email($email);
        $q = $this->db->prepare('SELECT id FROM users WHERE email=? AND verified_at IS NULL');
        $q->execute([$email]);
        $id = $q->fetchColumn();
        if ($id !== false) $this->sendVerification((int)$id, $email);
    }

    private function sendVerification(int $id, string $email): void
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $q = $this->db->prepare('INSERT INTO verification_tokens(token_hash,user_id,expires_at,created_at) VALUES(?,?,?,?)');
        $q->execute([$hash, $id, time() + 3600, time()]);
        try {
            ($this->send)($email, $raw);
        } catch (Throwable $error) {
            $this->db->prepare('DELETE FROM verification_tokens WHERE token_hash=?')->execute([$hash]);
            // No SMTP credentials, email addresses, or verification links in logs.
            error_log('ARCHIspace: verification email transport failed.');
            throw new RuntimeException('Email delivery is temporarily unavailable. Please use Resend verification later.');
        }
    }

    public function verify(string $raw): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) return false;
        $this->db->beginTransaction();
        try {
            // DELETE RETURNING makes one-time redemption atomic even under concurrent requests.
            $q = $this->db->prepare('DELETE FROM verification_tokens WHERE token_hash=? AND expires_at>? RETURNING user_id');
            $q->execute([hash('sha256', $raw), time()]);
            $id = $q->fetchColumn();
            $q->closeCursor();
            if ($id === false) { $this->db->rollBack(); return false; }
            $this->db->prepare('UPDATE users SET verified_at=? WHERE id=? AND verified_at IS NULL')->execute([time(), $id]);
            $this->db->prepare('DELETE FROM verification_tokens WHERE user_id=?')->execute([$id]);
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function login(string $email, string $password): array
    {
        $email = self::email($email);
        $q = $this->db->prepare('SELECT * FROM users WHERE email=?');
        $q->execute([$email]);
        $user = $q->fetch(PDO::FETCH_ASSOC);
        // Perform the same password-hash work for known and unknown accounts.
        $dummy = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $valid = password_verify($password, $user ? $user['password_hash'] : $dummy);
        if (!$user || !$valid || $user['status'] !== 'active') throw new DomainException('Email or password is incorrect.');
        if (!$user['verified_at']) throw new DomainException('Verify your email before signing in. You can request a new verification link below.');
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        unset($user['password_hash']);
        return $user;
    }
}
