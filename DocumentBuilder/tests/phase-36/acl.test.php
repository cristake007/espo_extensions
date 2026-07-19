<?php

declare(strict_types=1);

namespace Espo\ORM { interface Entity {} }
namespace Espo\Entities { final class User {} }

namespace Espo\Core\Acl {
    use Espo\Entities\User;
    use Espo\ORM\Entity;

    final class Table { public const LEVEL_YES = 'yes'; }
    final class ScopeData {}
    interface AccessChecker { public function check(User $user, ScopeData $data): bool; }
    interface AccessCreateChecker extends AccessChecker { public function checkCreate(User $user, ScopeData $data): bool; }
    interface AccessReadChecker extends AccessChecker { public function checkRead(User $user, ScopeData $data): bool; }
    interface AccessEditChecker extends AccessChecker { public function checkEdit(User $user, ScopeData $data): bool; }
    interface AccessDeleteChecker extends AccessChecker { public function checkDelete(User $user, ScopeData $data): bool; }
    interface AccessEntityCreateChecker extends AccessChecker { public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool; }
    interface AccessEntityReadChecker extends AccessChecker { public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool; }
    interface AccessEntityEditChecker extends AccessChecker { public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool; }
    interface AccessEntityDeleteChecker extends AccessChecker { public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool; }

    final class DefaultAccessChecker
    {
        public function __construct(private bool $allowed) {}
        public function check(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkRead(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkEdit(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkDelete(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool { return $this->allowed; }
        public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool { return $this->allowed; }
        public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool { return $this->allowed; }
    }
}

namespace Espo\Core {
    use Espo\Entities\User;

    final class AclManager
    {
        public ?string $requestedPermission = null;
        public function __construct(private string $level) {}
        public function getPermissionLevel(User $user, string $permission): string
        {
            $this->requestedPermission = $permission;

            return $this->level;
        }
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use Espo\Core\Acl\DefaultAccessChecker;
    use Espo\Core\Acl\ScopeData;
    use Espo\Core\AclManager;
    use Espo\Entities\User;
    use Espo\Modules\DocumentBuilder\Classes\Acl\DocumentBuilderDocumentAccessChecker;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';
    $module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder';
    require "$module/Tools/DocumentBuilder/Security/ActionPermission.php";
    require "$module/Classes/Acl/DocumentBuilderDocumentAccessChecker.php";

    $user = new User();
    $data = new ScopeData();
    $entity = new class implements Entity {};
    $normal = new DocumentBuilderDocumentAccessChecker(new AclManager('no'), new DefaultAccessChecker(true));
    Assert::isTrue($normal->checkRead($user, $data), 'Dedicated generation permissions incorrectly gate normal history reads.');
    Assert::isTrue($normal->checkEdit($user, $data), 'Normal ACL cannot edit history notes or ownership.');
    Assert::isTrue($normal->checkEntityRead($user, $entity, $data), 'Own/team history read was denied.');
    Assert::isFalse($normal->checkCreate($user, $data), 'Native history creation was allowed.');
    Assert::isFalse($normal->checkEntityCreate($user, $entity, $data), 'Native record creation was allowed.');
    Assert::isFalse($normal->checkDelete($user, $data), 'History delete omitted its dedicated permission.');

    $manager = new AclManager('yes');
    $allowed = new DocumentBuilderDocumentAccessChecker($manager, new DefaultAccessChecker(true));
    Assert::isTrue($allowed->checkDelete($user, $data), 'Authorized history deletion was denied.');
    Assert::isTrue($allowed->checkEntityDelete($user, $entity, $data), 'Authorized record deletion was denied.');
    Assert::same('documentBuilderDeleteGeneratedDocuments', $manager->requestedPermission, 'History delete checked the wrong permission.');

    $nativeDenied = new DocumentBuilderDocumentAccessChecker(new AclManager('yes'), new DefaultAccessChecker(false));
    Assert::isFalse($nativeDenied->checkRead($user, $data), 'Custom ACL bypassed native scope read.');
    Assert::isFalse($nativeDenied->checkDelete($user, $data), 'Delete permission bypassed native delete ACL.');

    echo "Phase 36 generated-document ACL composition tests passed.\n";
}
