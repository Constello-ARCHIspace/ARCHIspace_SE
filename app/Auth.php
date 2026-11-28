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

    public function register(string $firstName, string $lastName, string $email, string $password, string $phone, string $birthdate, ?string $middleName = null): void
    {
        $firstName = self::namePart($firstName, 'first name', true);
        $lastName = self::namePart($lastName, 'last name', true);
        $middleName = self::namePart($middleName ?? '', 'middle name', false);
        $email = self::email($email);
        $phone = self::phone($phone);
        self::birthdate($birthdate);
        $hash = self::passwordHash($password);
        $q =$this->db->prepare('INSERT INTO users(public_id,first_name,middle_name,last_name,email,phone,birthdate,password_hash,created_at) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(email) DO NOTHING');
        $q->execute([bin2hex(random_bytes(16)), $firstName, $middleName === '' ? null : $middleName, $lastName, $email, $phone, $birthdate, $hash, time()]);
        // Existing registrations never overwrite a password or trigger another email.
        if ($q->rowCount() !== 1) throw new DomainException('An account with this email already exists. Log in, or use Forgot password if you cannot access it.');
        $this->sendVerification((int)$this->db->lastInsertId(), $email);
    }

    private static function phone(string $phone): string
    {
        $phone = trim($phone);
        if (strlen($phone) > 24 || !preg_match('/^\+?[0-9 ().-]+$/D', $phone)) {
            throw new InvalidArgumentException('Enter a phone number with 7 to 15 digits. You may include a leading +.');
        }
        $phone = preg_replace('/[ ().-]/', '', $phone);
        if (!preg_match('/^\+?[0-9]{7,15}$/D', $phone)) {
            throw new InvalidArgumentException('Enter a phone number with 7 to 15 digits.');
        }
        return $phone;
    }

    private static function birthdate(string $birthdate): void
    {
        $dob = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $birthdate) ? DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate) : false;
        $today = new DateTimeImmutable('today');
        if (!$dob || $dob->format('Y-m-d') !== $birthdate || $birthdate < '1900-01-01' || $dob > $today || $dob->diff($today)->y > 120) {
            throw new InvalidArgumentException('Enter a valid date of birth.');
        }
        if ($dob->diff($today)->y < 18) {
            throw new InvalidArgumentException('You must be at least 18 years old to create an account.');
        }
    }

    // Email is not editable here: changing it would need a fresh inbox verification.
    public function updateProfile(int $id, string $firstName, string $lastName, string $phone, string $birthdate, ?string $middleName = null): void
    {
        $firstName = self::namePart($firstName, 'first name', true);
        $lastName = self::namePart($lastName, 'last name', true);
        $middleName = self::namePart($middleName ?? '', 'middle name', false);
        $phone = self::phone($phone);
        self::birthdate($birthdate);
        $this->db->prepare('UPDATE users SET first_name=?, middle_name=?, last_name=?, phone=?, birthdate=? WHERE id=?')
            ->execute([$firstName, $middleName === '' ? null : $middleName, $lastName, $phone, $birthdate, $id]);
    }

    private static function passwordHash(string $password): string
    {
        // 72-byte ceiling prevents bcrypt truncation on PHP installations without Argon2.
        if (mb_strlen($password) < 15 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new InvalidArgumentException('Use at least 15 characters and no more than 72 bytes for your password.');
        }
        return password_hash($password, PASSWORD_DEFAULT);
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
        // Only the newest code stays valid, which keeps the guessable space to one code per account.
        $this->db->prepare('DELETE FROM verification_tokens WHERE user_id=?')->execute([$id]);
        $this->sendToken('verification_tokens', 'verify', $id, $email);
    }

    public function requestReset(string $email): void
    {
        $email = self::email($email);
        $q = $this->db->prepare("SELECT id FROM users WHERE email=? AND status='active'");
        $q->execute([$email]);
        $id = $q->fetchColumn();
        if ($id === false) return;
        // Only the newest reset link stays valid.
        $this->db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([$id]);
        $this->sendToken('password_reset_tokens', 'reset', (int)$id, $email);
    }

    public function resetPassword(string $raw, string $password): bool
    {
        $hash = self::passwordHash($password); // Validate first so a rejected password does not burn the link.
        if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) return false;
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('DELETE FROM password_reset_tokens WHERE token_hash=? AND expires_at>? RETURNING user_id');
            $q->execute([hash('sha256', $raw), time()]);
            $id = $q->fetchColumn();
            $q->closeCursor();
            if ($id === false) { $this->db->rollBack(); return false; }
            // The emailed link proves inbox ownership, so it also verifies a pending account; the session_version bump signs out other sessions.
            $this->db->prepare('UPDATE users SET password_hash=?, session_version=session_version+1, verified_at=coalesce(verified_at,?) WHERE id=?')->execute([$hash, time(), $id]);
            $this->db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM verification_tokens WHERE user_id=?')->execute([$id]);
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    /** @param 'verification_tokens'|'password_reset_tokens' $table */
    private function sendToken(string $table, string $purpose, int $id, string $email): void
    {
        $code = $purpose === 'verify';
        $raw = $code ? sprintf('%06d', random_int(0, 999999)) : bin2hex(random_bytes(32));
        // Codes are salted with the user id so two accounts with the same code don't collide on the primary key.
        $hash = hash('sha256', $code ? "$id:$raw" : $raw);
        $q = $this->db->prepare("INSERT INTO $table(token_hash,user_id,expires_at,created_at) VALUES(?,?,?,?)");
        $q->execute([$hash, $id, time() + ($code ? 900 : 3600), time()]);
        try {
            ($this->send)($email, $raw, $purpose);
        } catch (Throwable $error) {
            $this->db->prepare("DELETE FROM $table WHERE token_hash=?")->execute([$hash]);
            // No SMTP credentials, email addresses, or account links in logs.
            error_log("ARCHIspace: $purpose email transport failed.");
            throw new RuntimeException('Email delivery is temporarily unavailable. Please try again later.');
        }
    }

    /** Brute-force protection is the caller's rate limit (public/auth.php); a code is 1 in 1,000,000. */
    public function verify(string $email, string $code): bool
    {
        $email = self::email($email);
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/D', $code)) return false;
        $q = $this->db->prepare('SELECT id FROM users WHERE email=? AND verified_at IS NULL');
        $q->execute([$email]);
        $id = $q->fetchColumn();
        if ($id === false) return false;
        $this->db->beginTransaction();
        try {
            // DELETE RETURNING makes one-time redemption atomic even under concurrent requests.
            $q = $this->db->prepare('DELETE FROM verification_tokens WHERE token_hash=? AND expires_at>? RETURNING user_id');
            $q->execute([hash('sha256', "$id:$code"), time()]);
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
        if (!$user['verified_at']) throw new DomainException('Verify your email before signing in. Enter the code from your email on the verification page, or request a new code.');
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        unset($user['password_hash']);
        return $user;
    }
}
