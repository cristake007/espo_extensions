<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use InvalidArgumentException;

final readonly class CapabilityRegistry
{
    /** @var array<string, CapabilityStatus> */
    private array $statuses;

    /** @param array<string, CapabilityStatus> $statuses */
    public function __construct(array $statuses)
    {
        $normalized = [];

        foreach ($statuses as $key => $status) {
            if (
                Capability::tryFrom($key) === null ||
                !$status instanceof CapabilityStatus ||
                isset($normalized[$key])
            ) {
                throw new InvalidArgumentException('The capability registry contains an unknown or duplicate marker.');
            }

            $normalized[$key] = $status;
        }

        if (count($normalized) !== count(Capability::cases())) {
            throw new InvalidArgumentException('The capability registry must declare every canonical marker.');
        }

        $this->statuses = $normalized;
    }

    public static function phase08(): self
    {
        return new self(array_fill_keys(
            array_map(static fn (Capability $capability): string => $capability->value, Capability::cases()),
            CapabilityStatus::SchemaOnly,
        ));
    }

    public function status(Capability $capability): CapabilityStatus
    {
        return $this->statuses[$capability->value];
    }

    /** @param list<Capability> $capabilities */
    public function requirePublishable(array $capabilities): void
    {
        foreach ($capabilities as $capability) {
            if ($this->status($capability) !== CapabilityStatus::Publishable) {
                throw new CapabilityNotPublishable($capability);
            }
        }
    }
}
