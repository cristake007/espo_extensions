<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node;

use DomainException;

final class UnknownNodeType extends DomainException
{
    public function __construct(NodeKind $kind, string $type)
    {
        parent::__construct(sprintf('Unknown %s node type "%s".', $kind->value, $type));
    }
}
