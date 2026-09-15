<?php

declare(strict_types=1);

namespace Iterp\Core;

use Iterp\Models\User;

/**
 * Lightweight auth: issue, validate & revoke API tokens, plus a middleware.
 *
 * Tokens are stored hashed (SHA-256) so a database leak doesn't expose them.
 */
class Auth
{
    public const TOKEN_LENGTH = 64;
    public const TOKEN_TTL    = 604800; // 7 days

    /**
     * @return mixed The authenticated user, or null.
     */
    public static function user(?Request $request = null): ?User
    {
        $request = $request ?: Request::capture();
        $token = $request->bearerToken();

        if ($token === null) {
            return null;
        }

        $hash = hash('sha256', $token);
        $stmt = Database::pdo()->prepare(
            'SELECT user_id FROM auth_tokens
             WHERE token_hash = :hash
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);

        $userId = $stmt->fetchColumn();
        if ($userId === false) {
            return null;
        }

        return User::find((int) $userId);
    }

    /**
     * Create a bearer token for a user and return the plaintext value.
     */
    public static function issueToken(User $user, int $ttl = self::TOKEN_TTL): string
    {
        $token    = bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
        $expires  = $ttl > 0 ? date('Y-m-d H:i:s', time() + $ttl) : null;

        Database::pdo()->prepare(
            'INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
        )->execute([$user->id, hash('sha256', $token), $expires]);

        return $token;
    }

    public static function revoke(string $token): void
    {
        Database::pdo()->prepare(
            'DELETE FROM auth_tokens WHERE token_hash = ?'
        )->execute([hash('sha256', $token)]);
    }

    public static function revokeAllFor(User $user): void
    {
        Database::pdo()->prepare(
            'DELETE FROM auth_tokens WHERE user_id = ?'
        )->execute([$user->id]);
    }

    /**
     * Route middleware: short-circuits with a 401 Response when unauthenticated.
     */
    public static function middleware(Request $request, array &$context): ?Response
    {
        $user = self::user($request);
        if ($user === null) {
            return Response::error('Unauthenticated.', 401);
        }
        $context['user'] = $user;
        return null;
    }
}