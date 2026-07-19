<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\ORM\Entity;

interface PreviewTemplateStore
{
    public function find(string $templateId): ?Entity;
}
