<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ProcessedLayout;
use Espo\ORM\Entity;

final readonly class PublicationValidationContext
{
    public function __construct(
        public Entity $template,
        public ProcessedLayout $processedLayout,
    ) {}
}
