<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplateVersion;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\ORM\Entity;

final class BeforeCreate implements CreateHook
{
    public function process(Entity $entity, CreateParams $params): void
    {
        throw new Forbidden('Document Builder template versions can only be created by publication.');
    }
}
