<?php

declare(strict_types=1);

namespace Espo\Core\Acl {
    interface AccessCreateChecker {}
    interface AccessDeleteChecker {}
    interface AccessEditChecker {}
    interface AccessEntityCREDChecker {}
    interface AccessReadChecker {}
    final class ScopeData {}
}

namespace Espo\ORM {
    class Entity
    {
        /** @param array<string, mixed> $values */
        public function __construct(protected array $values = [])
        {}

        public function get(string $name): mixed
        {
            return $this->values[$name] ?? null;
        }
    }
}

namespace Espo\Entities {
    use Espo\ORM\Entity;

    class User extends Entity
    {
        public const TYPE_REGULAR = 'regular';
        public const TYPE_ADMIN = 'admin';

        public function isAdmin(): bool
        {
            return $this->get('type') === self::TYPE_ADMIN;
        }
    }
}

namespace Espo\Modules\ZileSarbatoare\Entities {
    final class ZileLibere
    {
        public const SOURCE_NAGER_DATE = 'nager-date';
    }
}

namespace {
    use Espo\Core\Acl\ScopeData;
    use Espo\Entities\User;
    use Espo\Modules\ZileSarbatoare\Classes\Acl\ZileLibere\AccessChecker;
    use Espo\ORM\Entity;

    require_once __DIR__ . '/../../files/custom/Espo/Modules/ZileSarbatoare/Classes/Acl/ZileLibere/AccessChecker.php';

    function assertDecision(bool $expected, bool $actual, string $message): void
    {
        if ($actual !== $expected) {
            throw new RuntimeException($message);
        }
    }

    $checker = new AccessChecker();
    $scopeData = new ScopeData();
    $record = new Entity(['managed' => false, 'source' => 'manual']);

    foreach ([User::TYPE_REGULAR, User::TYPE_ADMIN] as $type) {
        $user = new User(['isActive' => true, 'type' => $type]);

        assertDecision(true, $checker->check($user, $scopeData), "$type scope read was denied.");
        assertDecision(true, $checker->checkRead($user, $scopeData), "$type read was denied.");
        assertDecision(
            true,
            $checker->checkEntityRead($user, $record, $scopeData),
            "$type record read was denied.",
        );
    }

    foreach ([
        new User(['isActive' => false, 'type' => User::TYPE_REGULAR]),
        new User(['isActive' => true, 'type' => 'portal']),
        new User(['isActive' => true, 'type' => 'api']),
        new User(['isActive' => true, 'type' => 'system']),
    ] as $user) {
        assertDecision(false, $checker->check($user, $scopeData), 'Excluded user received scope access.');
        assertDecision(false, $checker->checkRead($user, $scopeData), 'Excluded user received read access.');
        assertDecision(
            false,
            $checker->checkEntityRead($user, $record, $scopeData),
            'Excluded user received record read access.',
        );
    }

    $regular = new User(['isActive' => true, 'type' => User::TYPE_REGULAR]);
    assertDecision(false, $checker->checkCreate($regular, $scopeData), 'Regular user can create.');
    assertDecision(false, $checker->checkEdit($regular, $scopeData), 'Regular user can edit.');
    assertDecision(false, $checker->checkDelete($regular, $scopeData), 'Regular user can delete.');

    $admin = new User(['isActive' => true, 'type' => User::TYPE_ADMIN]);
    assertDecision(true, $checker->checkCreate($admin, $scopeData), 'Administrator cannot create.');
    assertDecision(true, $checker->checkEdit($admin, $scopeData), 'Administrator cannot edit.');
    assertDecision(true, $checker->checkDelete($admin, $scopeData), 'Administrator cannot delete.');
    assertDecision(true, $checker->checkEntityEdit($admin, $record, $scopeData), 'Administrator cannot edit manual record.');
    assertDecision(true, $checker->checkEntityDelete($admin, $record, $scopeData), 'Administrator cannot delete manual record.');

    $managed = new Entity(['managed' => true, 'source' => 'nager-date']);
    assertDecision(false, $checker->checkEntityEdit($admin, $managed, $scopeData), 'Managed record can be edited.');
    assertDecision(false, $checker->checkEntityDelete($admin, $managed, $scopeData), 'Managed record can be deleted.');

    echo "PHASE-001 access-checker tests passed.\n";
}
