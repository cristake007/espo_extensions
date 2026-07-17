<?php

declare(strict_types=1);

namespace Espo\Core {
    class Container
    {
        /** @param array<class-string, object> $services */
        public function __construct(private array $services)
        {}

        public function getByClass(string $className): object
        {
            return $this->services[$className];
        }
    }

    class InjectableFactory
    {
        public function __construct(private object $instance)
        {}

        public function create(string $className): object
        {
            return $this->instance;
        }
    }
}

namespace Espo\Core\Utils {
    class Config
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values)
        {}

        public function get(string $name): mixed
        {
            return $this->values[$name] ?? null;
        }
    }
}

namespace Espo\Core\Utils\Config {
    class ConfigWriter
    {
        /** @var array<string, mixed> */
        public array $changes = [];
        public int $saveCount = 0;

        public function set(string $name, mixed $value): void
        {
            $this->changes[$name] = $value;
        }

        public function save(): void
        {
            $this->saveCount++;
        }
    }
}

namespace {
    use Espo\Core\Container;
    use Espo\Core\InjectableFactory;
    use Espo\Core\Utils\Config;
    use Espo\Core\Utils\Config\ConfigWriter;

    require_once __DIR__ . '/../../scripts/AfterInstall.php';

    function makeContainer(mixed $calendarEntityList, ConfigWriter $writer): Container
    {
        return new Container([
            Config::class => new Config(['calendarEntityList' => $calendarEntityList]),
            InjectableFactory::class => new InjectableFactory($writer),
        ]);
    }

    $writer = new ConfigWriter();
    (new AfterInstall())->run(makeContainer(['Meeting', 'Call'], $writer));

    if ($writer->changes['calendarEntityList'] !== ['Meeting', 'Call', 'ZileLibere']) {
        throw new RuntimeException('Calendar registration must preserve existing entries and append ZileLibere.');
    }

    if ($writer->saveCount !== 1) {
        throw new RuntimeException('A changed calendar list must be saved once.');
    }

    $writer = new ConfigWriter();
    (new AfterInstall())->run(makeContainer(['Meeting', 'ZileLibere', 'Call'], $writer));

    if ($writer->saveCount !== 0 || $writer->changes !== []) {
        throw new RuntimeException('Existing calendar registration must not be rewritten.');
    }

    $writer = new ConfigWriter();

    try {
        (new AfterInstall())->run(makeContainer('Meeting', $writer));
        throw new RuntimeException('Invalid calendar configuration must be rejected.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'calendarEntityList must be an array.') {
            throw $e;
        }
    }

    echo "PHASE-001 installation tests passed.\n";
}
