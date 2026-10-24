<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Core\ORM\Entity as EntitatePrincipala;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\AccesAngajatiCondica;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class AccesAngajatiCondicaTest extends TestCase
{
    public function testEditareaEsteDelegataAcluluiNativCuProprietateaAngajatului(): void
    {
        $managerAcl = $this->createMock(AclManager::class);
        $managerEntitati = $this->createMock(EntityManager::class);
        $subiectAcl = $this->createMock(EntitatePrincipala::class);
        $utilizator = $this->creeazaUtilizator('manager', ['echipa-1']);
        $angajat = $this->creeazaUtilizator('angajat', ['echipa-2']);
        $managerEntitati
            ->expects(self::once())
            ->method('getNewEntity')
            ->with('Prezenta')
            ->willReturn($subiectAcl);
        $subiectAcl
            ->expects(self::once())
            ->method('set')
            ->with('assignedUserId', 'angajat');
        $subiectAcl
            ->expects(self::once())
            ->method('setLinkMultipleIdList')
            ->with('teams', ['echipa-2'])
            ->willReturnSelf();
        $managerAcl
            ->expects(self::once())
            ->method('checkEntity')
            ->with($utilizator, $subiectAcl, Table::ACTION_EDIT)
            ->willReturn(true);

        self::assertTrue(
            (new AccesAngajatiCondica($managerAcl, $managerEntitati, $utilizator))
                ->poateEdita($angajat)
        );
    }

    public function testCitireaEsteDelegataAcluluiNativFaraAlgoritmParalel(): void
    {
        $managerAcl = $this->createMock(AclManager::class);
        $managerEntitati = $this->createStub(EntityManager::class);
        $subiectAcl = $this->createStub(EntitatePrincipala::class);
        $utilizator = $this->creeazaUtilizator('manager', ['echipa-1']);
        $angajat = $this->creeazaUtilizator('angajat', ['echipa-1']);
        $managerEntitati->method('getNewEntity')->willReturn($subiectAcl);
        $managerAcl
            ->expects(self::once())
            ->method('checkEntity')
            ->with($utilizator, $subiectAcl, Table::ACTION_READ)
            ->willReturn(false);

        self::assertFalse(
            (new AccesAngajatiCondica($managerAcl, $managerEntitati, $utilizator))
                ->poateCiti($angajat)
        );
    }

    /** @param list<string> $echipe */
    private function creeazaUtilizator(string $id, array $echipe): User
    {
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn($id);
        $utilizator->method('getTeamIdList')->willReturn($echipe);

        return $utilizator;
    }
}
