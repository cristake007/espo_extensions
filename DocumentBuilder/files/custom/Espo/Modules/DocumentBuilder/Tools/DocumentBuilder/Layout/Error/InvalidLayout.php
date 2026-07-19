<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ValidationResult;

final class InvalidLayout extends LayoutProcessingException
{
    public function __construct(private readonly ValidationResult $result)
    {
        parent::__construct('The layout failed schema validation.');
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}
