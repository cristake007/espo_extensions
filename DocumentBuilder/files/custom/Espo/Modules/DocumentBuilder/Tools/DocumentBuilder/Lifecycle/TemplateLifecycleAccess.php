<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

use Espo\ORM\Entity;

interface TemplateLifecycleAccess
{
    public function requireDuplicate(Entity $template): void;

    public function requireArchive(Entity $template): void;

    public function requireDraftFromVersionTemplate(Entity $template): void;

    public function requireVersionRead(Entity $version): void;
}
