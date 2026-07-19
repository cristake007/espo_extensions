<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity {}
}

namespace Espo\Core\Record {
    final class CreateParams {}
    final class UpdateParams {}
    final class DeleteParams {}
}

namespace Espo\Core\Record\Hook {
    use Espo\Core\Record\CreateParams;
    use Espo\Core\Record\DeleteParams;
    use Espo\Core\Record\UpdateParams;
    use Espo\ORM\Entity;

    interface CreateHook { public function process(Entity $entity, CreateParams $params): void; }
    interface UpdateHook { public function process(Entity $entity, UpdateParams $params): void; }
    interface DeleteHook { public function process(Entity $entity, DeleteParams $params): void; }
}

namespace Espo\Core\Exceptions {
    final class Forbidden extends \RuntimeException {}
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use Espo\Core\Exceptions\Forbidden;
    use Espo\Core\Record\CreateParams;
    use Espo\Core\Record\DeleteParams;
    use Espo\Core\Record\UpdateParams;
    use Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplateVersion\BeforeCreate;
    use Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplateVersion\BeforeDelete;
    use Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplateVersion\BeforeUpdate;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';

    $hookRoot = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Classes/Record/Hooks/DocumentBuilderTemplateVersion';
    require "$hookRoot/BeforeCreate.php";
    require "$hookRoot/BeforeUpdate.php";
    require "$hookRoot/BeforeDelete.php";

    $entity = new class implements Entity {};

    Assert::throws(
        fn () => (new BeforeCreate())->process($entity, new CreateParams()),
        Forbidden::class,
        'Normal record creation must not fabricate a published snapshot.',
    );
    Assert::throws(
        fn () => (new BeforeUpdate())->process($entity, new UpdateParams()),
        Forbidden::class,
        'Published snapshot records must not be updated through normal record APIs.',
    );
    Assert::throws(
        fn () => (new BeforeDelete())->process($entity, new DeleteParams()),
        Forbidden::class,
        'Published snapshot records must not be deleted through normal record APIs.',
    );

    echo "Phase 11 template-version hook tests passed.\n";
}
