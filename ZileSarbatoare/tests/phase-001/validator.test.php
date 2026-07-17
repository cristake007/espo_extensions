<?php

declare(strict_types=1);

require_once __DIR__ . '/../../files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereValidator.php';

use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereValidator;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function assertInvalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$validator = new ZileLibereValidator();

assertSameValue('Zi liberă', $validator->normalizeName('  Zi liberă  '), 'Name normalization failed.');
assertSameValue('2026-02-24', $validator->normalizeDate('2026-02-24'), 'Date normalization failed.');
assertSameValue('RO', $validator->normalizeCountryCode(' ro '), 'Country normalization failed.');

assertInvalid(fn () => $validator->normalizeName(' '), 'An empty name must be rejected.');
assertInvalid(fn () => $validator->normalizeDate('2026-02-30'), 'An invalid date must be rejected.');
assertInvalid(fn () => $validator->normalizeDate('24-02-2026'), 'A non-ISO date must be rejected.');
assertInvalid(fn () => $validator->normalizeCountryCode('ROU'), 'A three-letter country code must be rejected.');
assertInvalid(fn () => $validator->normalizeCountryCode('R1'), 'A non-letter country code must be rejected.');

echo "PHASE-001 validator tests passed.\n";
