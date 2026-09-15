<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;

/**
 * User model (backed by the users table).
 */
class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'username',
        'password',
        'title_id',
        'gender',
        'role_id',
        'dob',
        'doj',
        'email',
        'mobile_no',
        'city_id',
        'academic_year_id',
        'firm_id',
        'tenant_id',
        'is_active',
    ];

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER   = 'user';

    public function id(): int
    {
        return (int) $this->attributes['id'];
    }

    public function save(): bool
    {
        // Hash plaintext passwords that are going to be stored.
        if (isset($this->attributes['password'])
            && strlen($this->attributes['password']) > 0
            && !password_get_info($this->attributes['password'])['algo']) {
            $this->attributes['password'] = password_hash($this->attributes['password'], PASSWORD_DEFAULT);
        }
        return parent::save();
    }

    public function setPassword(string $password): void
    {
        $this->attributes['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $password): bool
    {
        if (empty($this->attributes['password'])) {
            return false;
        }
        return password_verify($password, $this->attributes['password']);
    }

    public function isVerified(): bool
    {
        return !empty($this->attributes['email_verified_at']);
    }

    public function markEmailAsVerified(): void
    {
        $this->attributes['email_verified_at'] = date('Y-m-d H:i:s');
        $this->save();
    }

    public function isAdmin(): bool
    {
        return ($this->attributes['role'] ?? null) === self::ROLE_ADMIN;
    }

    /**
     * Create a fresh email verification token (hashed) for this user.
     *
     * The plaintext token is returned once; only the hash is stored.
     */
    public function createEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24h

        Database::pdo()->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
        )->execute([$this->id(), hash('sha256', $token), $expiresAt]);

        return $token;
    }

    public function markAsVerifiedIfTokenValid(string $token): bool
    {
        $hash = hash('sha256', $token);
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM email_verifications
             WHERE user_id = :uid AND token_hash = :hash AND expires_at > NOW()'
        );
        $stmt->execute(['uid' => $this->id(), 'hash' => $hash]);

        $id = (int) $stmt->fetchColumn();
        if ($id > 0) {
            $this->markEmailAsVerified();
            Database::pdo()->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$this->id()]);
            return true;
        }

        return false;
    }

    /**
     * Generate a password reset token for this user.
     */
    public function createPasswordResetToken(): string
    {
        $token = bin2hex(random_bytes(32));

        Database::pdo()->prepare(
            'INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)'
        )->execute([$this->attributes['email'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
        return $token; // plaintext, sent by email
    }

    public static function findByEmail(string $email): ?self
    {
        return self::query()->where('email', $email)->first() ?: null;
    }

    /**
     * Get all roles assigned to this user through the user_roles junction table.
     */
    public function roles(): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT r.* FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :uid AND r.deleted_at IS NULL
             ORDER BY r.name ASC'
        );
        $stmt->execute(['uid' => $this->id()]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return list of assigned role IDs.
     */
    public function roleIds(): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT role_id FROM user_roles WHERE user_id = :uid'
        );
        $stmt->execute(['uid' => $this->id()]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Sync multiple roles for this user in user_roles.
     * Also updates users.role_id with the primary (first) role for backwards compatibility.
     */
    public function syncRoles(array $roleIds): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :uid')->execute(['uid' => $this->id()]);

        $validIds = [];
        $insert = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (:uid, :rid, NOW())');
        foreach ($roleIds as $rid) {
            $rid = (int) $rid;
            if ($rid > 0) {
                $insert->execute(['uid' => $this->id(), 'rid' => $rid]);
                $validIds[] = $rid;
            }
        }

        $primaryRole = !empty($validIds) ? $validIds[0] : null;
        $pdo->prepare('UPDATE users SET role_id = :rid WHERE id = :uid')
            ->execute(['rid' => $primaryRole, 'uid' => $this->id()]);
        $this->attributes['role_id'] = $primaryRole;
    }
}