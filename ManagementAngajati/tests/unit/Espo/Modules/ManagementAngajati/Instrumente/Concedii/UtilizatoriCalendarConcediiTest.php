<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Select\Helpers\RelationQueryHelper;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\UtilizatoriCalendarConcedii;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\WhereItem;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class UtilizatoriCalendarConcediiTest extends TestCase
{
    public function testFaraPermisiunePastreazaDoarCalendarulPersonal(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('getPermissionLevel')->willReturn(Table::LEVEL_NO);
        $utilizator = $this->createStub(User::class);
        $utilizator->method('isAdmin')->willReturn(false);
        $manager = $this->createMock(EntityManager::class);
        $manager->expects(self::never())->method('getRDBRepositoryByClass');

        $serviciu = new UtilizatoriCalendarConcedii(
            $acl,
            $manager,
            $this->createStub(RelationQueryHelper::class),
            $utilizator,
        );

        self::assertSame([], $serviciu->obtineIduri());
    }

    public function testAdministratorulPrimesteTotiUtilizatoriiRegulariActivi(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('getPermissionLevel')->willReturn(Table::LEVEL_NO);
        $administrator = $this->createStub(User::class);
        $administrator->method('isAdmin')->willReturn(true);
        $administrator->method('getId')->willReturn('admin');

        $serviciu = new UtilizatoriCalendarConcedii(
            $acl,
            $this->managerCuRezultate(['angajat-1', 'angajat-2']),
            $this->createStub(RelationQueryHelper::class),
            $administrator,
        );

        self::assertSame(['angajat-1', 'angajat-2'], $serviciu->obtineIduri());
    }

    public function testNivelulTeamLimiteazaUtilizatoriiLaEchipeleComune(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('getPermissionLevel')->willReturn(Table::LEVEL_TEAM);
        $lider = $this->createStub(User::class);
        $lider->method('isAdmin')->willReturn(false);
        $lider->method('getId')->willReturn('lider');
        $lider->method('getLinkMultipleIdList')->willReturn(['echipa-1']);
        $conditieEchipe = $this->createStub(WhereItem::class);
        $ajutor = $this->createMock(RelationQueryHelper::class);
        $ajutor->expects(self::once())->method('prepareLinkWhereMany')->with(
            User::ENTITY_TYPE,
            User::LINK_TEAMS,
            ['echipa-1']
        )->willReturn($conditieEchipe);

        $serviciu = new UtilizatoriCalendarConcedii(
            $acl,
            $this->managerCuRezultate(['coleg-1']),
            $ajutor,
            $lider,
        );

        self::assertSame(['coleg-1'], $serviciu->obtineIduri());
    }

    /** @param string[] $iduri */
    private function managerCuRezultate(array $iduri): EntityManager
    {
        $angajati = [];

        foreach ($iduri as $id) {
            $angajat = $this->createStub(User::class);
            $angajat->method('getId')->willReturn($id);
            $angajati[] = $angajat;
        }

        $constructor = $this->createStub(RDBSelectBuilder::class);
        $constructor->method('where')->willReturnSelf();
        $constructor->method('find')->willReturn(new EntityCollection($angajati));
        $depozit = $this->createStub(RDBRepository::class);
        $depozit->method('select')->willReturn($constructor);
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getRDBRepositoryByClass')->willReturn($depozit);

        return $manager;
    }
}
