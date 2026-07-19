<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security;

use RuntimeException;

final class PermissionDenied extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The requested Document Builder operation is not permitted.');
    }
}
