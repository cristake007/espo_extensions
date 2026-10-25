<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Acl;
use Espo\Core\Acl\Permission;
use Espo\Core\Acl\Table;
use Espo\Core\Name\Field;
use Espo\Core\Select\Helpers\RelationQueryHelper;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;

/** Stabilește utilizatorii ale căror concedii pot fi suprapuse în Calendar. */
class UtilizatoriCalendarConcedii
{
    public function __construct(
        private Acl $acl,
        private EntityManager $managerEntitati,
        private RelationQueryHelper $ajutorInterogareRelatii,
        private User $utilizator,
    ) {}

    /** @return string[] */
    public function obtineIduri(): array
    {
        $nivel = $this->acl->getPermissionLevel(Permission::USER_CALENDAR);

        if ($this->utilizator->isAdmin() || $nivel === Table::LEVEL_ALL) {
            return $this->obtineIduriUtilizatoriActivi();
        }

        if ($nivel !== Table::LEVEL_TEAM) {
            return [];
        }

        $iduriEchipe = $this->utilizator->getLinkMultipleIdList(Field::TEAMS);

        if ($iduriEchipe === []) {
            return [];
        }

        return $this->obtineIduriUtilizatoriActivi($iduriEchipe);
    }

    /**
     * @param string[]|null $iduriEchipe
     * @return string[]
     */
    private function obtineIduriUtilizatoriActivi(?array $iduriEchipe = null): array
    {
        $interogare = $this->managerEntitati
            ->getRDBRepositoryByClass(User::class)
            ->select([Attribute::ID])
            ->where([
                User::ATTR_IS_ACTIVE => true,
                User::ATTR_TYPE => User::TYPE_REGULAR,
                Attribute::ID . '!=' => $this->utilizator->getId(),
            ]);

        if ($iduriEchipe !== null) {
            $interogare->where(
                $this->ajutorInterogareRelatii->prepareLinkWhereMany(
                    User::ENTITY_TYPE,
                    User::LINK_TEAMS,
                    $iduriEchipe
                )
            );
        }

        $iduri = [];

        foreach ($interogare->find() as $angajat) {
            $iduri[] = $angajat->getId();
        }

        return $iduri;
    }
}
