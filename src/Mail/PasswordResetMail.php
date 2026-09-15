<?php

declare(strict_types=1);

namespace Iterp\Mail;

use Iterp\Core\Mailable;

/**
 * Password reset mail - includes a one-time password reset URL (1h expiry).
 */
class PasswordResetMail extends Mailable
{
    public const VIEW = 'emails.password-reset';

    public function __construct(protected string $toAddress, protected string $resetUrl, protected string $name = '')
    {
        $this->build($toAddress, [
            'name'     => $name,
            'url'     => $resetUrl,
            'expires' => '1 hour',
        ]);
    }

    public function subject(): string
    {
        return 'Reset your password';
    }

    public function view(): string
    {
        return self::VIEW;
    }
}