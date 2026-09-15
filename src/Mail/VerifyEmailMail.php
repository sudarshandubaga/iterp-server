<?php

declare(strict_types=1);

namespace Iterp\Mail;

use Iterp\Core\Mailable;

/**
 * Email verification mail - includes a one-time verify URL (24h expiry).
 */
class VerifyEmailMail extends Mailable
{
    public const VIEW = 'emails.verify-email';

    public function __construct(protected string $toAddress, protected string $verificationUrl, protected string $name = '')
    {
        $this->build($toAddress, [
            'name'            => $name,
            'url'            => $verificationUrl,
        ]);
    }

    public function subject(): string
    {
        return 'Verify your email address';
    }

    public function view(): string
    {
        return self::VIEW;
    }
}