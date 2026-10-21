<?php

declare(strict_types=1);

namespace DocumentBuilder\Tests\Support;

use InvalidArgumentException;
use RuntimeException;

final class RuntimeGuard
{
    public const ESPO_VERSION = '10.0.10';
    public const APPROVED_SITE_URL = 'http://178.104.183.29';
    public const APPROVED_RUNTIME_PATH = '/var/www/html';
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

        if ($resolved !== self::APPROVED_RUNTIME_PATH) {
            throw new RuntimeException('The path is not the approved persistent EspoCRM runtime.');
        }

        $statePath = $resolved . '/data/state.php';
        $configPath = $resolved . '/data/config.php';

        if (!is_file($statePath) || !is_file($configPath)) {
            throw new RuntimeException('The approved runtime identity files are missing.');
        }

        $state = require $statePath;
        $config = require $configPath;

        if (($state['version'] ?? null) !== self::ESPO_VERSION ||
            ($config['siteUrl'] ?? null) !== self::APPROVED_SITE_URL) {
            throw new RuntimeException('The path does not match the approved EspoCRM test instance.');
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
