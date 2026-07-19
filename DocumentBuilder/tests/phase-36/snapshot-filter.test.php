<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity
    {
        public function clear(string $attribute): void;
    }
}

namespace Espo\Core\Acl {
    final class Table
    {
        public const LEVEL_YES = 'yes';
    }
}

namespace Espo\Core\Record\Output {
    use Espo\ORM\Entity;

    interface Filter
    {
        public function filter(Entity $entity): void;
    }
}

namespace Espo\Core {
    final class Acl
    {
        public function __construct(private string $level)
        {}

        public function getPermissionLevel(string $permission): string
        {
            return $this->level;
        }
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use Espo\Core\Acl;
    use Espo\Modules\DocumentBuilder\Classes\Record\OutputFilters\DocumentBuilderDocument\Snapshot;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';
    $module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder';
    require "$module/Tools/DocumentBuilder/Security/ActionPermission.php";
    require "$module/Classes/Record/OutputFilters/DocumentBuilderDocument/Snapshot.php";

    $record = new class implements Entity {
        /** @var list<string> */
        public array $cleared = [];

        public function clear(string $attribute): void
        {
            $this->cleared[] = $attribute;
        }
    };
    (new Snapshot(new Acl('no')))->filter($record);
    Assert::same(['dataSnapshot', 'templateSnapshot'], $record->cleared, 'Unauthorized snapshot fields entered API output.');

    $allowed = new class implements Entity {
        /** @var list<string> */
        public array $cleared = [];

        public function clear(string $attribute): void
        {
            $this->cleared[] = $attribute;
        }
    };
    (new Snapshot(new Acl('yes')))->filter($allowed);
    Assert::same([], $allowed->cleared, 'Snapshot permission did not preserve authorized output.');

    echo "Phase 36 snapshot output-permission tests passed.\n";
}
