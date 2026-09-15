<?php

declare(strict_types=1);

namespace Iterp\Core;

/**
 * Framework-free PSR-4 style autoloader.
 *
 * Maps the leading namespace "Iterp\" to the src/ directory.
 */
class Autoloader
{
    /** @var string[] */
    private array $prefixes = [];

    public function __construct(array $prefixes = ['Iterp\\' => __DIR__ . '/..'])
    {
        $this->prefixes = $prefixes;
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    public function loadClass(string $class): bool
    {
        $file = $this->resolveFile($class);
        if ($file !== null && is_file($file)) {
            require $file;
            return true;
        }
        return false;
    }

    private function resolveFile(string $class): ?string
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            return rtrim($baseDir, '/') . '/' . str_replace('\\', '/', $relative) . '.php';
        }
        return null;
    }
}
