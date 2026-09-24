<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Validation;

use Espo\Core\Utils\Metadata;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;

final readonly class ContractRegistry
{
    /** @var array<string, mixed> */
    private array $definition;

    public function __construct(Metadata $metadata)
    {
        $definition = $metadata->get(['app', 'contentFactory', 'generator']);

        if (!is_array($definition) || ($definition['schemaVersion'] ?? null) !== 1) {
            throw new GenerationException(
                'CONFIGURATION_ERROR',
                503,
                'Contractul Content Factory nu este configurat.',
            );
        }

        $this->definition = $definition;
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return $this->definition;
    }

    /** @return array<string, mixed>|null */
    public function platform(string $id): ?array
    {
        foreach ($this->definition['platforms'] ?? [] as $platform) {
            if (is_array($platform) && ($platform['id'] ?? null) === $id) {
                return $platform;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function format(string $id): ?array
    {
        $format = ($this->definition['formats'] ?? [])[$id] ?? null;

        return is_array($format) ? $format : null;
    }

    /** @return array<string, mixed>|null */
    public function style(string $id): ?array
    {
        foreach ($this->definition['styles'] ?? [] as $style) {
            if (is_array($style) && ($style['id'] ?? null) === $id) {
                return $style;
            }
        }

        return null;
    }

    /** @return array{aspectRatio: string, width: int, height: int}|null */
    public function videoProfile(string $platform, string $asset): ?array
    {
        $profile = $this->definition['mediaProfiles']['video']["$platform.$asset"] ?? null;

        if (
            !is_array($profile) ||
            !is_string($profile['aspectRatio'] ?? null) ||
            !is_int($profile['width'] ?? null) ||
            !is_int($profile['height'] ?? null)
        ) {
            return null;
        }

        return $profile;
    }
}
