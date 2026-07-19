<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderDocument;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\ORM\Entity;

final class BeforeCreate implements CreateHook
{
    public function process(Entity $entity, CreateParams $params): void
    {
        throw new Forbidden('Generated documents can only be created by the generation service.');
    }
}
