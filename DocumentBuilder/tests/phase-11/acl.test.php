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
        public function __construct(public bool $allowed) {}
        public function check(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkRead(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool { return $this->allowed; }
    }
}

namespace Espo\Core {
    use Espo\Entities\User;
    final class AclManager
    {
        public ?string $requestedPermission = null;
        public function __construct(public string $level) {}
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
    use Espo\Modules\DocumentBuilder\Classes\Acl\DocumentBuilderTemplateVersionAccessChecker;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';
    $moduleRoot = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder';
    require "$moduleRoot/Tools/DocumentBuilder/Security/ActionPermission.php";
    require "$moduleRoot/Classes/Acl/DocumentBuilderTemplateVersionAccessChecker.php";

    $user = new User();
    $data = new ScopeData();
    $entity = new class implements Entity {};

    $permissionDeniedManager = new AclManager('no');
    $permissionDenied = new DocumentBuilderTemplateVersionAccessChecker($permissionDeniedManager, new DefaultAccessChecker(true));
    Assert::isFalse($permissionDenied->checkRead($user, $data), 'Version reads require the design permission.');
    Assert::isFalse($permissionDenied->checkEntityRead($user, $entity, $data), 'Record reads require the design permission.');
    Assert::same('documentBuilderDesignTemplates', $permissionDeniedManager->requestedPermission, 'Version ACL checked the wrong permission.');

    $nativeDenied = new DocumentBuilderTemplateVersionAccessChecker(new AclManager('yes'), new DefaultAccessChecker(false));
    Assert::isFalse($nativeDenied->checkRead($user, $data), 'Design permission must not bypass native scope ACL.');
    Assert::isFalse($nativeDenied->checkEntityRead($user, $entity, $data), 'Design permission must not bypass own/team ACL.');

    $allowed = new DocumentBuilderTemplateVersionAccessChecker(new AclManager('yes'), new DefaultAccessChecker(true));
    Assert::isTrue($allowed->check($user, $data), 'Enabled designers need version scope access.');
    Assert::isTrue($allowed->checkRead($user, $data), 'Composed scope read should be allowed.');
    Assert::isTrue($allowed->checkEntityRead($user, $entity, $data), 'Composed record read should be allowed.');

    Assert::isFalse($allowed->checkCreate($user, $data), 'Normal scope create must always be denied.');
    Assert::isFalse($allowed->checkEdit($user, $data), 'Normal scope edit must always be denied.');
    Assert::isFalse($allowed->checkDelete($user, $data), 'Normal scope delete must always be denied.');
    Assert::isFalse($allowed->checkEntityCreate($user, $entity, $data), 'Normal record create must always be denied.');
    Assert::isFalse($allowed->checkEntityEdit($user, $entity, $data), 'Normal record edit must always be denied.');
    Assert::isFalse($allowed->checkEntityDelete($user, $entity, $data), 'Normal record delete must always be denied.');

    echo "Phase 11 template-version ACL tests passed.\n";
}
