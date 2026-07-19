<?php

declare(strict_types=1);

namespace DocumentBuilder\Tests\Support;

use RuntimeException;
use Throwable;

final class Assert
{
    public static function isTrue(bool $value, string $message): void
    {
        if (!$value) {
            throw new RuntimeException($message);
        }
    }

    public static function isFalse(bool $value, string $message): void
    {
        self::isTrue(!$value, $message);
    }

    public static function same(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . sprintf(
                "\nExpected: %s\nActual: %s",
                var_export($expected, true),
                var_export($actual, true),
            ));
        }
    }

    public static function contains(string $needle, string $haystack, string $message): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException($message . "\nMissing text: $needle");
        }
    }

    /** @param class-string<Throwable> $expectedClass */
    public static function throws(callable $callback, string $expectedClass, string $message): void
    {
        try {
            $callback();
        } catch (Throwable $throwable) {
            if ($throwable instanceof $expectedClass) {
                return;
            }

            throw new RuntimeException(
                $message . sprintf(
                    '\nExpected exception: %s\nActual exception: %s',
                    $expectedClass,
                    $throwable::class,
                ),
                previous: $throwable,
            );
        }

        throw new RuntimeException($message . "\nNo exception was thrown.");
    }
}
