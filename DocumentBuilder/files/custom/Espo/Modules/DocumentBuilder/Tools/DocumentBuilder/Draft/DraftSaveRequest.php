<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use InvalidArgumentException;

final readonly class DraftSaveRequest
{
    public ?string $changeNote;

    public function __construct(
        public string $layoutJson,
        public int $expectedRevision,
        public bool $confirmSourceChange = false,
        ?string $changeNote = null,
    ) {
        if (trim($layoutJson) === '' || $expectedRevision < 0) {
            throw new InvalidArgumentException('Draft save input is invalid.');
        }

        $changeNote = $changeNote === null ? null : trim($changeNote);
        $this->changeNote = $changeNote === '' ? null : $changeNote;

        if ($this->changeNote !== null && mb_strlen($this->changeNote) > 4000) {
            throw new InvalidArgumentException('The draft change note is too long.');
        }
    }
}
