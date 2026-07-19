<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use RuntimeException;

final class PreviewRateLimitExceeded extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The preview request limit was reached.');
    }
}
