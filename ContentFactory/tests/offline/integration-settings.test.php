<?php

declare(strict_types=1);

namespace Espo\Core\Utils {
    final class Config
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values = [])
        {}

        public function get(string $name): mixed
        {
            return $this->values[$name] ?? null;
        }
    }
}

namespace Espo\Entities {
    final class Integration
    {
        /** @param array<string, mixed> $values */
        public function __construct(
            private array $values,
            private bool $new = false,
        ) {}

        public function get(string $name): mixed
        {
            return $this->values[$name] ?? null;
        }

        public function isNew(): bool
        {
            return $this->new;
        }
    }
}

namespace Espo\ORM {
    final class EntityManager
    {
        public function __construct(private ?object $entity)
        {}

        public function getRDBRepositoryByClass(string $className): object
        {
            return new class ($this->entity) {
                public function __construct(private ?object $entity)
                {}

                public function getById(string $id): ?object
                {
                    return $this->entity;
                }
            };
        }
    }
}

namespace {
    use Espo\Core\Utils\Config;
    use Espo\Entities\Integration;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Config\SettingsProvider;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
    use Espo\ORM\EntityManager;

    $module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/ContentFactory';

    require $module . '/Tools/ContentFactory/Generation/GenerationException.php';
    require $module . '/Tools/ContentFactory/Config/Settings.php';
    require $module . '/Tools/ContentFactory/Config/SettingsProvider.php';

    /** @param array<string, mixed> $values */
    function provider(array $values, bool $developerMode = false, bool $new = false): SettingsProvider
    {
        return new SettingsProvider(
            new EntityManager(new Integration($values, $new)),
            new Config(['isDeveloperMode' => $developerMode]),
        );
    }

    /** @param callable(): void $operation */
    function expectConfigurationError(callable $operation): void
    {
        try {
            $operation();
            throw new RuntimeException('Expected configuration error was not thrown.');
        } catch (GenerationException $exception) {
            if ($exception->getErrorCode() !== 'CONFIGURATION_ERROR') {
                throw $exception;
            }
        }
    }

    $settings = provider([
        'enabled' => true,
        'webhookUrl' => 'https://n8n.example.test/webhook/content-factory',
        'authenticationToken' => 'server-side-secret',
        'connectTimeoutSeconds' => 5,
        'responseTimeoutSeconds' => 90,
    ])->get();

    if (
        !$settings->enabled ||
        $settings->webhookUrl !== 'https://n8n.example.test/webhook/content-factory' ||
        $settings->authToken !== 'server-side-secret' ||
        $settings->connectTimeoutSeconds !== 5 ||
        $settings->responseTimeoutSeconds !== 90
    ) {
        throw new RuntimeException('Native integration settings were not resolved correctly.');
    }

    $development = provider([
        'enabled' => true,
        'webhookUrl' => 'http://n8n.local/webhook/content-factory',
    ], true)->get();

    if ($development->connectTimeoutSeconds !== 5 || $development->responseTimeoutSeconds !== 90) {
        throw new RuntimeException('Integration timeout defaults were not applied.');
    }

    expectConfigurationError(static fn () => provider([
        'enabled' => true,
        'webhookUrl' => 'http://n8n.example.test/webhook',
    ])->get());
    expectConfigurationError(static fn () => provider([
        'enabled' => true,
        'webhookUrl' => ' https://n8n.example.test/webhook',
    ])->get());
    expectConfigurationError(static fn () => provider([
        'enabled' => true,
        'webhookUrl' => 'https://n8n.example.test/webhook',
        'connectTimeoutSeconds' => 31,
    ])->get());
    expectConfigurationError(static fn () => provider([], false, true)->get());

    echo "Integration settings tests passed.\n";
}
