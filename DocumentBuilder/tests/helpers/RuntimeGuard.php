<?php

declare(strict_types=1);

namespace DocumentBuilder\Tests\Support;

use InvalidArgumentException;
use RuntimeException;

final class RuntimeGuard
{
    public const ESPO_VERSION = '10.0.0';
    public const PRODUCTION_PATH = '/opt/crm.cursurituv.ro';

    public static function assertExplicitNonProductionPath(string $candidate): string
    {
        if ($candidate === '' || !str_starts_with($candidate, '/')) {
            throw new InvalidArgumentException('An explicit absolute test-instance path is required.');
        }

        $normalized = self::normalizeAbsolutePath($candidate);

        if (self::isProductionPath($normalized)) {
            throw new InvalidArgumentException('The production EspoCRM path is prohibited.');
        }

        return $normalized;
    }

    public static function resolveEspoRoot(string $candidate): string
    {
        self::assertExplicitNonProductionPath($candidate);
        $resolved = realpath($candidate);

        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException('The explicit test-instance path does not exist.');
        }

        if (self::isProductionPath($resolved)) {
            throw new RuntimeException('The resolved path points to the prohibited production instance.');
        }

        $packagePath = $resolved . DIRECTORY_SEPARATOR . 'package.json';
        $packageContents = file_get_contents($packagePath);

        if ($packageContents === false) {
            throw new RuntimeException('The test-instance package.json could not be read.');
        }

        $package = json_decode($packageContents, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($package) || ($package['version'] ?? null) !== self::ESPO_VERSION) {
            throw new RuntimeException('The explicit test instance must be EspoCRM 10.0.0.');
        }

        return $resolved;
    }

    private static function isProductionPath(string $path): bool
    {
        return $path === self::PRODUCTION_PATH ||
            str_starts_with($path, self::PRODUCTION_PATH . DIRECTORY_SEPARATOR);
    }

    private static function normalizeAbsolutePath(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}
