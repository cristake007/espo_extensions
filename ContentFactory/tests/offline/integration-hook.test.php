<?php

declare(strict_types=1);

namespace Espo\Core\Exceptions {
    final class BadRequest extends \RuntimeException {}
}

namespace Espo\Core\Utils {
    final class Config
    {
        public function __construct(private bool $developerMode = false)
        {}

        public function get(string $name): mixed
        {
            return $name === 'isDeveloperMode' ? $this->developerMode : null;
        }
    }
}

namespace Espo\ORM\Repository\Option {
    final class SaveOptions {}
}

namespace Espo\ORM {
    class Entity
    {
        /** @param array<string, mixed> $values */
        public function __construct(
            private string $id,
            public array $values,
        ) {}

        public function getId(): string
        {
            return $this->id;
        }

        public function get(string $name): mixed
        {
            return $this->values[$name] ?? null;
        }

        public function set(string $name, mixed $value): void
        {
            $this->values[$name] = $value;
        }
    }
}

namespace Espo\Core\Hook\Hook {
    use Espo\ORM\Entity;
    use Espo\ORM\Repository\Option\SaveOptions;

    interface BeforeSave
    {
        public function beforeSave(Entity $entity, SaveOptions $options): void;
    }
}

namespace {
    use Espo\Core\Exceptions\BadRequest;
    use Espo\Core\Utils\Config;
    use Espo\Modules\ContentFactory\Hooks\Integration\ValidateContentFactoryN8n;
    use Espo\Modules\ContentFactory\Hooks\Integration\ValidateContentFactoryWordPress;
    use Espo\ORM\Entity;
    use Espo\ORM\Repository\Option\SaveOptions;

    require dirname(__DIR__, 2) .
        '/files/custom/Espo/Modules/ContentFactory/Hooks/Integration/ValidateContentFactoryN8n.php';
    require dirname(__DIR__, 2) .
        '/files/custom/Espo/Modules/ContentFactory/Hooks/Integration/ValidateContentFactoryWordPress.php';

    /** @param callable(): void $operation */
    function expectBadRequest(callable $operation): void
    {
        try {
            $operation();
            throw new RuntimeException('Expected integration validation failure was not thrown.');
        } catch (BadRequest) {
        }
    }

    $hook = new ValidateContentFactoryN8n(new Config());
    $entity = new Entity('ContentFactoryN8n', [
        'enabled' => true,
        'webhookUrl' => 'https://n8n.example.test/webhook',
        'authenticationToken' => 'secret-token',
    ]);
    $hook->beforeSave($entity, new SaveOptions());

    if (
        $entity->values['connectTimeoutSeconds'] !== 5 ||
        $entity->values['responseTimeoutSeconds'] !== 90
    ) {
        throw new RuntimeException('Integration hook did not apply timeout defaults.');
    }

    expectBadRequest(static fn () => $hook->beforeSave(new Entity('ContentFactoryN8n', [
        'enabled' => true,
        'webhookUrl' => '',
    ]), new SaveOptions()));
    expectBadRequest(static fn () => $hook->beforeSave(new Entity('ContentFactoryN8n', [
        'enabled' => true,
        'webhookUrl' => 'http://n8n.example.test/webhook',
    ]), new SaveOptions()));
    expectBadRequest(static fn () => $hook->beforeSave(new Entity('ContentFactoryN8n', [
        'enabled' => true,
        'webhookUrl' => 'https://n8n.example.test/webhook',
        'authenticationToken' => "secret\nheader",
    ]), new SaveOptions()));

    $developmentHook = new ValidateContentFactoryN8n(new Config(true));
    $developmentHook->beforeSave(new Entity('ContentFactoryN8n', [
        'enabled' => true,
        'webhookUrl' => 'http://n8n.local/webhook',
        'connectTimeoutSeconds' => 1,
        'responseTimeoutSeconds' => 180,
    ]), new SaveOptions());

    $wordpressHook = new ValidateContentFactoryWordPress(new Config());
    $wordpress = new Entity('ContentFactoryWordPress', [
        'enabled' => true,
        'baseUrl' => 'https://wordpress.example.test',
        'username' => 'content-factory',
        'applicationPassword' => 'abcd efgh ijkl',
    ]);
    $wordpressHook->beforeSave($wordpress, new SaveOptions());

    if (
        $wordpress->values['connectTimeoutSeconds'] !== 5 ||
        $wordpress->values['responseTimeoutSeconds'] !== 20
    ) {
        throw new RuntimeException('WordPress integration hook did not apply timeout defaults.');
    }

    expectBadRequest(static fn () => $wordpressHook->beforeSave(new Entity('ContentFactoryWordPress', [
        'enabled' => true,
        'baseUrl' => 'https://wordpress.example.test',
        'username' => 'content-factory',
        'applicationPassword' => '',
    ]), new SaveOptions()));

    echo "Integration save-hook tests passed.\n";
}
