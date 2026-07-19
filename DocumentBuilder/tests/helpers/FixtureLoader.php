<?php

declare(strict_types=1);

namespace DocumentBuilder\Tests\Support;

use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class FixtureLoader
{
    private string $root;

    public function __construct(string $root)
    {
        $resolved = realpath($root);

        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException("Fixture root does not exist: $root");
        }

        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    /** @return array<mixed> */
    public function json(string $relativePath): array
    {
        try {
            $value = json_decode($this->text($relativePath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Invalid JSON fixture: $relativePath",
                previous: $exception,
            );
        }

        if (!is_array($value)) {
            throw new RuntimeException("JSON fixture root must be an object or array: $relativePath");
        }

        return $value;
    }

    public function text(string $relativePath): string
    {
        $path = $this->resolveFile($relativePath);
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Could not read fixture: $relativePath");
        }

        return $contents;
    }

    /** @return list<string> */
    public function relativeFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        $files = [];

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[] = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($file->getPathname(), strlen($this->root) + 1),
            );
        }

        sort($files);

        return $files;
    }

    private function resolveFile(string $relativePath): string
    {
        if (
            $relativePath === '' ||
            str_starts_with($relativePath, '/') ||
            str_contains($relativePath, '\\') ||
            preg_match('~(?:^|/)\.\.(?:/|$)~', $relativePath) === 1 ||
            preg_match('~\A[A-Za-z0-9._/-]+\z~D', $relativePath) !== 1
        ) {
            throw new RuntimeException("Unsafe fixture path: $relativePath");
        }

        $resolved = realpath($this->root . DIRECTORY_SEPARATOR . $relativePath);

        if (
            $resolved === false ||
            !is_file($resolved) ||
            !str_starts_with($resolved, $this->root . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException("Fixture is outside the fixture root or missing: $relativePath");
        }

        return $resolved;
    }
}
