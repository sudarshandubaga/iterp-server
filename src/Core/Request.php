<?php

declare(strict_types=1);

/**
 * Request wrapper around the superglobals + JSON body.
 */
namespace Iterp\Core;

class Request
{
    private array $query;
    private array $post;
    private array $file;
    private array $json;
    private array $server;

    public function __construct()
    {
        $this->query  = $_GET;
        $this->post   = $_POST;
        $this->file   = $_FILES;
        $this->server = $_SERVER;
        $this->json   = $this->parseJsonBody();
    }

    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function capture(): self
    {
        return new self();
    }

    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . ltrim($path, '/');
    }

    public function isJson(): bool
    {
        return str_contains($this->server['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public function header(string $name, $default = null)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? $default;
    }

    /**
     * Bearer token from Authorization header, if present.
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function userAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    public function ip(): ?string
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }
}