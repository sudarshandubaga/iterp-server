<?php

declare(strict_types=1);

namespace Iterp\Core;

/**
 * Base class for mailables. Subclasses provide the recipient, subject and view/body.
 */
abstract class Mailable
{
    protected string $to;
    protected array  $data = [];

    public function build(string $to, array $data = []): self
    {
        $this->to   = $to;
        $this->data = $data;
        return $this;
    }

    public function to(): string
    {
        return $this->to;
    }

    public function fromAddress(): ?string
    {
        return null; // fall back to mail.from.address
    }

    abstract public function subject(): string;

    abstract public function view(): string;

    public function html(): string
    {
        return render($this->view(), $this->data);
    }

    public function send(): void
    {
        Mailer::send($this);
    }
}