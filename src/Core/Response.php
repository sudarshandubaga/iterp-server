<?php

declare(strict_types=1);

namespace Iterp\Core;

/**
 * JSON-aware HTTP response helper.
 */
class Response
{
    private int $status;
    private mixed $body;
    private array $headers;

    public function __construct(int $status = 200, mixed $body = null, array $headers = [])
    {
        $this->status  = $status;
        $this->body    = $body;
        $this->headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
        ], $headers);
    }

    public static function json(int $status, mixed $data = null, array $headers = []): self
    {
        return new self($status, $data, $headers);
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): self
    {
        return new self($status, [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): self
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        return new self($status, $payload);
    }

    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): self
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->body !== null) {
            $json = json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            echo $json ?: json_encode(['error' => 'JSON encoding failed']);
        }
        return $this;
    }
}