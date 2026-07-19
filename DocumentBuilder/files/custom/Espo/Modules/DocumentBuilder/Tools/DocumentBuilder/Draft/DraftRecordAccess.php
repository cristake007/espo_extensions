<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use Espo\ORM\Entity;

interface DraftRecordAccess
{
    public function requireEdit(Entity $template): void;

    public function requireSpreadsheetSource(): void;
}
