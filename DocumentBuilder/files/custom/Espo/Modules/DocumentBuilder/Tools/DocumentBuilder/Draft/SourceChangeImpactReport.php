<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use InvalidArgumentException;

final readonly class SourceChangeImpactReport
{
    /**
     * @param array<string, mixed> $previousSource
     * @param array<string, mixed> $nextSource
     * @param list<UnresolvedSourceReference> $unresolvedReferences
     */
    public function __construct(
        public array $previousSource,
        public array $nextSource,
        public array $unresolvedReferences,
    ) {
        foreach ($unresolvedReferences as $reference) {
            if (!$reference instanceof UnresolvedSourceReference) {
                throw new InvalidArgumentException('A source impact reference has an invalid type.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'previousSource' => $this->previousSource,
            'nextSource' => $this->nextSource,
            'unresolvedReferences' => array_map(
                static fn (UnresolvedSourceReference $reference): array => $reference->toArray(),
                $this->unresolvedReferences,
            ),
        ];
    }
}
