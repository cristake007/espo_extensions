<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplateVersion;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\Hook\DeleteHook;
use Espo\ORM\Entity;

final class BeforeDelete implements DeleteHook
{
    public function process(Entity $entity, DeleteParams $params): void
    {
        throw new Forbidden('Published Document Builder template versions cannot be deleted.');
    }
}
