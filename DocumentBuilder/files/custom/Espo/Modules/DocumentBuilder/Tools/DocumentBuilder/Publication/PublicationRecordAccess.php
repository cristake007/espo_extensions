<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use Espo\ORM\Entity;

interface PublicationRecordAccess
{
    public function requirePublish(Entity $template): void;
}
