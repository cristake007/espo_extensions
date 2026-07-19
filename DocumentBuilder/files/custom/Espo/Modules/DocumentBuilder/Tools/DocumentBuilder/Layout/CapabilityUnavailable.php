<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use DomainException;

final class CapabilityUnavailable extends DomainException
{
    public function __construct(Capability $capability)
    {
        parent::__construct(sprintf('Layout capability "%s" is not implemented.', $capability->value));
    }
}
