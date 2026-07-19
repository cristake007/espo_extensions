<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

interface MediaPublicationValidator
{
    public function validate(PublicationValidationContext $context): void;
}
