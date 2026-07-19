<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;
use InvalidArgumentException;

final readonly class NodeDefinition
{
    /** @param list<Capability> $requiredCapabilities */
    public function __construct(
        private NodeKind $kind,
        private string $type,
        private array $requiredCapabilities,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $type) !== 1) {
            throw new InvalidArgumentException('A node type must use the canonical safe type format.');
        }

        if ($requiredCapabilities === []) {
            throw new InvalidArgumentException('A node definition must declare its capability gate.');
        }

        $seenCapabilities = [];

        foreach ($requiredCapabilities as $capability) {
            if (!$capability instanceof Capability || isset($seenCapabilities[$capability->value])) {
                throw new InvalidArgumentException('A node definition contains an invalid capability gate.');
            }

            $seenCapabilities[$capability->value] = true;
        }
    }

    public function kind(): NodeKind
    {
        return $this->kind;
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return list<Capability> */
    public function requiredCapabilities(): array
    {
        return $this->requiredCapabilities;
    }
}
