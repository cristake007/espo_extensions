<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use Espo\ORM\Entity;

interface DraftTemplateStore
{
    /** @template T @param callable(Entity): T $updater @return T */
    public function updateLocked(string $templateId, callable $updater): mixed;
}
