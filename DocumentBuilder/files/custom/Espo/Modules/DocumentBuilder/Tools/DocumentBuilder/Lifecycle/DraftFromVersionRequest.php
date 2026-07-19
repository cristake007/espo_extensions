<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

use InvalidArgumentException;

final readonly class DraftFromVersionRequest
{
    public ?string $changeNote;

    public function __construct(
        public int $expectedRevision,
        public string $versionId,
        ?string $changeNote = null,
    ) {
        $changeNote = $changeNote === null ? null : trim($changeNote);
        $this->changeNote = $changeNote === '' ? null : $changeNote;

        if (
            $expectedRevision < 0 ||
            trim($versionId) === '' ||
            ($this->changeNote !== null && mb_strlen($this->changeNote) > 4000)
        ) {
            throw new InvalidArgumentException('Draft-from-version input is invalid.');
        }
    }
}
