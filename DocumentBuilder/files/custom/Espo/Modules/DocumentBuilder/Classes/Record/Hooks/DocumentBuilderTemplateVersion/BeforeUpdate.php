<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplateVersion;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\ORM\Entity;

final class BeforeUpdate implements UpdateHook
{
    public function process(Entity $entity, UpdateParams $params): void
    {
        throw new Forbidden('Published Document Builder template versions are immutable.');
    }
}
