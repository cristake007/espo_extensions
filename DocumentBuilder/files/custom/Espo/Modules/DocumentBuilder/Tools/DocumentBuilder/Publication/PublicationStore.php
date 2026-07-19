<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion\TemplateVersionSnapshot;
use Espo\ORM\Entity;

interface PublicationStore
{
    /**
     * @param callable(Entity, int, list<string>): TemplateVersionSnapshot $snapshotFactory
     */
    public function publishLocked(string $templateId, callable $snapshotFactory): PublicationResult;
}
