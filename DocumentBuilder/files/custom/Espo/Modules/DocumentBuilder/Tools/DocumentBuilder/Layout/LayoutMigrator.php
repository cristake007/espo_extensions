<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\UnsupportedSchemaVersion;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Migration\LayoutMigration;
use InvalidArgumentException;

final readonly class LayoutMigrator
{
    /** @var array<int, LayoutMigration> */
    private array $migrations;

    public function __construct(LayoutMigration ...$migrations)
    {
        $indexed = [];

        foreach ($migrations as $migration) {
            $from = $migration->fromVersion();

            if ($from < 1 || $migration->toVersion() !== $from + 1 || isset($indexed[$from])) {
                throw new InvalidArgumentException('Layout migrations must be unique, consecutive forward steps.');
            }

            $indexed[$from] = $migration;
        }

        ksort($indexed, SORT_NUMERIC);
        $this->migrations = $indexed;
    }

    /**
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    public function migrate(array $layout): array
    {
        $version = $layout['schemaVersion'] ?? null;
        $current = SchemaVersion::current()->value;

        if (!is_int($version) || $version < 1 || $version > $current) {
            throw new UnsupportedSchemaVersion();
        }

        while ($version < $current) {
            $migration = $this->migrations[$version] ?? throw new UnsupportedSchemaVersion();
            $layout = $migration->migrate($layout);
            $nextVersion = $layout['schemaVersion'] ?? null;

            if ($nextVersion !== $migration->toVersion()) {
                throw new UnsupportedSchemaVersion();
            }

            $version = $nextVersion;
        }

        return $layout;
    }
}
