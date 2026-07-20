<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity
    {
        public function set(string|array $field, mixed $value = null): void;
    }
}

namespace Espo\Core\Record {
    final class CreateParams {}
    final class DeleteParams {}
}

namespace Espo\Core\Record\Hook {
    use Espo\Core\Record\CreateParams;
    use Espo\Core\Record\DeleteParams;
    use Espo\ORM\Entity;

    interface CreateHook
    {
        public function process(Entity $entity, CreateParams $params): void;
    }

    interface DeleteHook
    {
        public function process(Entity $entity, DeleteParams $params): void;
    }
}

namespace Espo\Core\Exceptions {
    final class Forbidden extends \RuntimeException {}
}

namespace Espo\Core\Utils {
    final class Config
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data)
        {}

        public function get(string $name, mixed $default = null): mixed
        {
            return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
        }
    }

    final class Metadata
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data)
        {}

        /** @param string|list<string>|null $key */
        public function get(string|array|null $key = null, mixed $default = null): mixed
        {
            if ($key === null) {
                return $this->data;
            }

            $path = is_array($key) ? $key : [$key];
            $value = $this->data;

            foreach ($path as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return $default;
                }

                $value = $value[$segment];
            }

            return $value;
        }
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use DocumentBuilder\Tests\Support\FixtureLoader;
    use Espo\Core\Exceptions\Forbidden;
    use Espo\Core\Record\CreateParams;
    use Espo\Core\Record\DeleteParams;
    use Espo\Core\Utils\Config;
    use Espo\Core\Utils\Metadata;
    use Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplate\BeforeCreate;
    use Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplate\BeforeDelete;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\ConfigProvider;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';

    $extensionRoot = dirname(__DIR__, 2);
    $moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
    $resourceLoader = new FixtureLoader($moduleRoot . '/Resources');
    $definition = $resourceLoader->json('metadata/app/documentBuilder.json');

    foreach ([
        'Config/InvalidConfiguration.php',
        'Config/Settings.php',
        'Config/SettingsProvider.php',
        'Config/ConfigProvider.php',
        'Layout/SchemaVersion.php',
        'Layout/Unit.php',
        'Layout/Measurement.php',
        'Layout/Source/SourceType.php',
        'Layout/Source/SourceDescriptor.php',
        'Layout/Source/NoSourceDescriptor.php',
        'Layout/LayoutDefaults.php',
    ] as $relativePath) {
        require "$moduleRoot/Tools/DocumentBuilder/$relativePath";
    }

    require "$moduleRoot/Classes/Record/Hooks/DocumentBuilderTemplate/BeforeCreate.php";
    require "$moduleRoot/Classes/Record/Hooks/DocumentBuilderTemplate/BeforeDelete.php";

    final class TestTemplateEntity implements Entity
    {
        /** @param array<string, mixed> $values */
        public function __construct(public array $values)
        {}

        public function set(string|array $field, mixed $value = null): void
        {
            if (is_array($field)) {
                $this->values = array_replace($this->values, $field);

                return;
            }

            $this->values[$field] = $value;
        }
    }

    $configProvider = new ConfigProvider(
        new Config(['documentBuilder' => [
            'allowedFontList' => ['DejaVu Sans', 'DejaVu Serif'],
            'defaultFont' => 'DejaVu Serif',
            'defaultLocale' => 'ro_RO',
            'defaultPageSize' => 'A3',
        ]]),
        new Metadata(['app' => ['documentBuilder' => $definition]]),
    );
    $entity = new TestTemplateEntity([
        'status' => 'Published',
        'sourceType' => 'entity',
        'entityType' => 'User',
        'revision' => 999,
        'draftChangeNote' => 'untrusted note',
        'isActive' => false,
        'currentPublishedVersionId' => 'untrusted-version-id',
    ]);

    (new BeforeCreate($configProvider))->process($entity, new CreateParams());

    Assert::same('Draft', $entity->values['status'] ?? null, 'Create hook must force the draft lifecycle state.');
    Assert::same('none', $entity->values['sourceType'] ?? null, 'Create hook must force a source-neutral draft.');
    Assert::same(null, $entity->values['entityType'] ?? null, 'Create hook must clear an untrusted entity type.');
    Assert::same(0, $entity->values['revision'] ?? null, 'Create hook must reset the draft revision.');
    Assert::same(null, $entity->values['draftChangeNote'] ?? null, 'Create hook must clear an untrusted change note.');
    Assert::same(true, $entity->values['isActive'] ?? null, 'Create hook must activate a new draft.');
    Assert::same(null, $entity->values['currentPublishedVersionId'] ?? null, 'Create hook must clear a version link.');
    Assert::isTrue($entity->values['spreadsheetSchema'] instanceof \stdClass, 'Empty JSON-object storage must remain an object.');

    $layout = $entity->values['currentDraftLayout'] ?? null;
    Assert::isTrue(is_array($layout), 'Create hook must persist a canonical layout object.');
    Assert::same('DejaVu Serif', $layout['document']['defaults']['fontFamily'] ?? null, 'Configured default font was ignored.');
    Assert::same('ro_RO', $layout['document']['defaults']['locale'] ?? null, 'Configured default locale was ignored.');
    Assert::same('A3', $layout['document']['page']['size'] ?? null, 'Configured page size was ignored.');
    Assert::same('A3', $entity->values['pageSize'] ?? null, 'Page summary must match the canonical layout.');
    Assert::same('portrait', $entity->values['orientation'] ?? null, 'Orientation summary must match the canonical layout.');

    Assert::throws(
        fn () => (new BeforeDelete())->process($entity, new DeleteParams()),
        Forbidden::class,
        'Direct template hard-delete must be denied.',
    );

    echo "Phase 10 template hook tests passed.\n";
}
