<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity {}
}

namespace Espo\Entities {
    final class User {}
}

namespace Espo\Core\Acl {
    use Espo\Entities\User;
    use Espo\ORM\Entity;

    final class Table
    {
        public const LEVEL_YES = 'yes';
    }

    final class ScopeData {}

    interface AccessChecker
    {
        public function check(User $user, ScopeData $data): bool;
    }

    interface AccessCreateChecker extends AccessChecker
    {
        public function checkCreate(User $user, ScopeData $data): bool;
    }

    interface AccessReadChecker extends AccessChecker
    {
        public function checkRead(User $user, ScopeData $data): bool;
    }

    interface AccessEditChecker extends AccessChecker
    {
        public function checkEdit(User $user, ScopeData $data): bool;
    }

    interface AccessDeleteChecker extends AccessChecker
    {
        public function checkDelete(User $user, ScopeData $data): bool;
    }

    interface AccessStreamChecker extends AccessChecker
    {
        public function checkStream(User $user, ScopeData $data): bool;
    }

    interface AccessEntityCreateChecker extends AccessChecker
    {
        public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool;
    }

    interface AccessEntityReadChecker extends AccessChecker
    {
        public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool;
    }

    interface AccessEntityEditChecker extends AccessChecker
    {
        public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool;
    }

    interface AccessEntityDeleteChecker extends AccessChecker
    {
        public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool;
    }

    interface AccessEntityStreamChecker extends AccessChecker
    {
        public function checkEntityStream(User $user, Entity $entity, ScopeData $data): bool;
    }

    final class DefaultAccessChecker
    {
        public function __construct(public bool $allowed = true)
        {}

        public function check(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkCreate(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkRead(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkEdit(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkStream(User $user, ScopeData $data): bool { return $this->allowed; }
        public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool { return $this->allowed; }
        public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool { return $this->allowed; }
        public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool { return $this->allowed; }
        public function checkEntityStream(User $user, Entity $entity, ScopeData $data): bool { return $this->allowed; }
    }
}

namespace Espo\Core {
    use Espo\Entities\User;

    final class AclManager
    {
        public ?string $requestedPermission = null;

        public function __construct(public string $permissionLevel)
        {}

        public function getPermissionLevel(User $user, string $permission): string
        {
            $this->requestedPermission = $permission;

            return $this->permissionLevel;
        }
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use Espo\Core\Acl\DefaultAccessChecker;
    use Espo\Core\Acl\ScopeData;
    use Espo\Core\AclManager;
    use Espo\Entities\User;
    use Espo\Modules\DocumentBuilder\Classes\Acl\DocumentBuilderTemplateAccessChecker;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';

    $extensionRoot = dirname(__DIR__, 2);
    $moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
    require "$moduleRoot/Tools/DocumentBuilder/Security/ActionPermission.php";
    require "$moduleRoot/Classes/Acl/DocumentBuilderTemplateAccessChecker.php";

    $user = new User();
    $scopeData = new ScopeData();
    $entity = new class implements Entity {};

    /** @return list<callable(DocumentBuilderTemplateAccessChecker): bool> */
    function phase10AllowedChecks(User $user, ScopeData $scopeData, Entity $entity): array
    {
        return [
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->check($user, $scopeData),
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->checkCreate($user, $scopeData),
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->checkRead($user, $scopeData),
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->checkEdit($user, $scopeData),
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->checkStream($user, $scopeData),
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->checkEntityCreate($user, $entity, $scopeData),
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->checkEntityRead($user, $entity, $scopeData),
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->checkEntityEdit($user, $entity, $scopeData),
            fn (DocumentBuilderTemplateAccessChecker $checker): bool => $checker->checkEntityStream($user, $entity, $scopeData),
        ];
    }

    $permissionDeniedManager = new AclManager('no');
    $permissionDenied = new DocumentBuilderTemplateAccessChecker(
        $permissionDeniedManager,
        new DefaultAccessChecker(true),
    );

    foreach (phase10AllowedChecks($user, $scopeData, $entity) as $check) {
        Assert::isFalse($check($permissionDenied), 'Scope ACL must not bypass the design permission.');
    }

    Assert::same(
        'documentBuilderDesignTemplates',
        $permissionDeniedManager->requestedPermission,
        'Template ACL checked the wrong action permission.',
    );

    $nativeDenied = new DocumentBuilderTemplateAccessChecker(
        new AclManager('yes'),
        new DefaultAccessChecker(false),
    );

    foreach (phase10AllowedChecks($user, $scopeData, $entity) as $check) {
        Assert::isFalse($check($nativeDenied), 'Design permission must not bypass native scope or own/team ACL.');
    }

    $allowed = new DocumentBuilderTemplateAccessChecker(
        new AclManager('yes'),
        new DefaultAccessChecker(true),
    );

    foreach (phase10AllowedChecks($user, $scopeData, $entity) as $check) {
        Assert::isTrue($check($allowed), 'Composed design and native ACL should allow the operation.');
    }

    Assert::isFalse($allowed->checkDelete($user, $scopeData), 'Scope hard-delete must always be denied.');
    Assert::isFalse($allowed->checkEntityDelete($user, $entity, $scopeData), 'Record hard-delete must always be denied.');

    echo "Phase 10 template ACL tests passed.\n";
}
