<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Auth;
use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Mail\PasswordResetMail;
use Iterp\Mail\VerifyEmailMail;
use Iterp\Models\User;

/**
 * Authentication API controller: register, login, logout, profile,
 * email verification, forgot/reset password.
 */
class AuthController
{
    /**
     * POST /api/auth/register
     *
     * Creates an account, issues an API token and emails a verification link.
     * The users table stores `first_name`, `username` and `role_id`, so we map
     * the friendly `name` input onto those columns.
     */
    public function register(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $email    = strtolower(trim((string) $data['email']));
        $username = $data['username'] ?? $this->generateUsername((string) $data['name'], $email);

        $user = new User([
            'first_name' => trim((string) $data['name']),
            'username'   => $username,
            'email'      => $email,
            'password'   => $data['password'],
            'role_id'    => $this->defaultRoleId(),
            'is_active'  => 'y',
        ]);
        $user->save();

        $token = Auth::issueToken($user);

        $this->sendVerificationEmail($user);

        return Response::success([
            'user'  => $this->userPayload($user),
            'token' => $token,
        ], 'Account created. Please verify your email.', 201);
    }

    /** Pick a sensible starter role (Admin, then Super Admin,failing, leave unassigned). */
    private function defaultRoleId(): ?int
    {
        $stmt = Database::pdo()->query(
            "SELECT id FROM roles
             WHERE name IN ('Admin', 'Super Admin')
             ORDER BY FIELD(name, 'Admin', 'Super Admin')
             LIMIT 1"
        );
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** Derive a unique username from the display name / email address. */
    private function generateUsername(string $name, string $email): string
    {
        $base = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($name));
        $base = trim($base, '_');
        if ($base === '') {
            $base = explode('@', $email)[0];
        }
        $base = $base ?: 'user';
        $username = $base;
        $i = 1;
        while (User::query()->where('username', $username)->first() !== null) {
            $username = $base . '_' . $i++;
        }
        return $username;
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'email'    => 'nullable|string|max:255',
            'username' => 'nullable|string|max:150',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        // Accept the login identifier as either an email address or a username.
        $identifier = trim((string) ($data['email'] ?? $data['username'] ?? ''));
        if ($identifier === '') {
            return Response::error('An email or username is required.', 422);
        }

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if ($user === null || !$user->verifyPassword((string) $data['password'])) {
            return Response::error('Invalid credentials.', 401);
        }

        $token = Auth::issueToken($user);

        return Response::success([
            'user'  => $this->userPayload($user),
            'token' => $token,
        ], 'Logged in.');
    }
/**
     * POST /api/auth/logout  (auth: api)
     */
    public function logout(Request $request): Response
    {
        $token = $request->bearerToken();
        if ($token !== null) {
            Auth::revoke($token);
        }

        return Response::success(null, 'Logged out.');
    }

    /**
     * GET /api/auth/me  (auth: api)
     */
    public function me(Request $request, array $context): Response
    {
        return Response::success([
            'user' => $this->userPayload($context['user']),
        ]);
    }

    /**
     * GET /api/auth/email/verify/{token}
     *
     * Executed when the user clicks the emailed verification link.
     */
    public function verifyEmail(Request $request, array $context, array $params): Response
    {
        $token = $params['token'] ?? '';
        if ($token === '') {
            return Response::error('Verification token is missing.', 400);
        }

        $hash = hash('sha256', $token);
        $stmt = Database::pdo()->prepare(
            'SELECT user_id, expires_at FROM email_verifications WHERE token_hash = :hash'
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();

        if ($row === false) {
            return Response::error('Invalid or expired verification token.', 400);
        }

        $user = User::find((int) $row['user_id']);
        if ($user === null) {
            return Response::error('Account not found.', 404);
        }

        $user->markEmailAsVerified();

        Database::pdo()->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$user->id()]);

        return Response::success(['email_verified_at' => $user->email_verified_at], 'Email verified successfully.');
    }

    /**
     * POST /api/auth/email/resend - sends a fresh verification link.
     */
    public function resendVerification(Request $request, array $context): Response
    {
        $user = $context['user'];
        if ($user->isVerified()) {
            return Response::error('Email is already verified.', 400);
        }

        $this->sendVerificationEmail($user);

        return Response::success(null, 'Verification email sent.');
    }
/**
     * POST /api/auth/password/forgot
     */
    public function forgotPassword(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $email = strtolower(trim($data['email']));
        $user  = User::findByEmail($email);

        // Always respond the same way to avoid user enumeration; only send
        // an email when the account actually exists.
        if ($user !== null) {
            $this->sendPasswordResetEmail($user);
        }

        return Response::success(null, 'If that email exists, a reset link has been sent.');
    }

    /**
     * POST /api/auth/password/reset - body: { token, email, password, password_confirmation }
     */
    public function resetPassword(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $email = strtolower(trim($data['email']));
        $hash  = hash('sha256', $data['token']);

        $stmt = Database::pdo()->prepare(
            'SELECT id FROM password_resets
             WHERE email = :email AND token_hash = :hash AND expires_at > NOW()'
        );
        $stmt->execute(['email' => $email, 'hash' => $hash]);
        $resetId = $stmt->fetchColumn();

        if ($resetId === false) {
            return Response::error('Invalid or expired reset token.', 400);
        }

        $user = User::findByEmail($email);
        if ($user === null) {
            return Response::error('Account not found.', 404);
        }

        $user->setPassword($data['password']);
        $user->save();

        // Invalidate the reset token and all existing sessions.
        Database::pdo()->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
        Auth::revokeAllFor($user);

        return Response::success(null, 'Password reset successfully. Please login again.');
    }

    protected function sendVerificationEmail(User $user): void
    {
        $token = $user->createEmailVerificationToken();
        $url   = rtrim((string) config('app.url'), '/')
            . '/api/auth/email/verify/' . $token;

        try {
            (new VerifyEmailMail($user->email, $url, (string) $user->name))->send();
        } catch (\Throwable $e) {
            // Mail failure should not block registration - log and continue.
            error_log('[mail] verify-email failed: ' . $e->getMessage());
        }
    }

    protected function sendPasswordResetEmail(User $user): void
    {
        $token = $user->createPasswordResetToken();
        $url   = rtrim((string) config('mail.client_url'), '/')
            . '/reset-password?token=' . $token . '&email=' . rawurlencode((string) $user->email);

        try {
            (new PasswordResetMail($user->email, $url, (string) $user->name))->send();
        } catch (\Throwable $e) {
            error_log('[mail] password-reset failed: ' . $e->getMessage());
        }
    }

    protected function userPayload(User $user): array
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($name === '') $name = $user->name ?? '';

        $role = null;
        if (!empty($user->role_id)) {
            $stmt = Database::pdo()->prepare(
                'SELECT name FROM roles WHERE id = :id'
            );
            $stmt->execute(['id' => $user->role_id]);
            $role = $stmt->fetchColumn() ?: null;
        } elseif (!empty($user->id())) {
            $stmt = Database::pdo()->prepare(
                'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :uid LIMIT 1'
            );
            $stmt->execute(['uid' => $user->id()]);
            $role = $stmt->fetchColumn() ?: null;
        }

        return [
            'id'                => $user->id(),
            'name'              => $name,
            'email'             => $user->email,
            'role'              => $role,
            'username'          => $user->username,
            'email_verified_at' => $user->email_verified_at,
            'created_at'         => $user->created_at,
        ];
    }
}
