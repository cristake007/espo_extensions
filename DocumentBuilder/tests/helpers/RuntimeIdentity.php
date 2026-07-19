<?php

declare(strict_types=1);

namespace DocumentBuilder\Tests\Support;

use InvalidArgumentException;

final class RuntimeIdentity
{
    public const SUITE = 'document-builder';

    private const IDENTIFIER_PATTERN = '/\A[a-z0-9][a-z0-9-]{2,47}\z/D';

    public function __construct(private string $runId)
    {
        self::assertIdentifier($runId, 'run');
    }

    public function recordName(string $fixtureId): string
    {
        self::assertIdentifier($fixtureId, 'fixture');

        return sprintf('DBT %s %s', $this->runId, $fixtureId);
    }

    /** @return array{suite: string, runId: string, fixtureId: string, ownershipToken: string} */
    public function marker(string $fixtureId): array
    {
        self::assertIdentifier($fixtureId, 'fixture');

        return [
            'suite' => self::SUITE,
            'runId' => $this->runId,
            'fixtureId' => $fixtureId,
            'ownershipToken' => hash(
                'sha256',
                implode("\0", [self::SUITE, $this->runId, $fixtureId]),
            ),
        ];
    }

    /** @param array<string, mixed> $candidate */
    public function owns(array $candidate, string $fixtureId): bool
    {
        $expected = $this->marker($fixtureId);
        ksort($candidate);
        ksort($expected);

        return $candidate === $expected;
    }

    /** @return array{suite: string, runId: string, fixtureId: string, ownershipToken: string} */
    public function cleanupCriteria(string $fixtureId): array
    {
        return $this->marker($fixtureId);
    }

    private static function assertIdentifier(string $identifier, string $label): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidArgumentException(
                "$label identifier must be 3-48 lowercase letters, digits, or hyphens.",
            );
        }
    }
}
