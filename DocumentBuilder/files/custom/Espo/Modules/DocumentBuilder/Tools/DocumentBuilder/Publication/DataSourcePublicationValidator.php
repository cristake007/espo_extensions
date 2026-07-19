<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

interface DataSourcePublicationValidator
{
    public function validate(PublicationValidationContext $context): void;
}
