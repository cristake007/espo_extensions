<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Hooks\Prezenta;

use Espo\Core\ORM\Entity as EntitatePrincipala;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Hooks\Prezenta\PregatesteLaCreare;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\DenumirePrezenta;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

class PregatesteLaCreareTest extends TestCase
{
    public function testEntitateaNouaPrimesteNumeleSiEchipeleAngajatului(): void
    {
        $prezenta = $this->createMock(EntitatePrincipala::class);
        $angajat = $this->createStub(User::class);
        $managerEntitati = $this->createMock(EntityManager::class);
        $denumire = $this->createMock(DenumirePrezenta::class);

        $prezenta->method('isNew')->willReturn(true);
        $prezenta->method('get')->with('assignedUserId')->willReturn('angajat-1');
        $angajat->method('getTeamIdList')->willReturn(['echipa-1', 'echipa-2']);
        $managerEntitati->expects(self::once())->method('getEntityById')
            ->with(User::ENTITY_TYPE, 'angajat-1')
            ->willReturn($angajat);
        $denumire->expects(self::once())->method('aplica')->with($prezenta);
        $prezenta
            ->expects(self::once())
            ->method('setLinkMultipleIdList')
            ->with('teams', ['echipa-1', 'echipa-2'])
            ->willReturnSelf();

        (new PregatesteLaCreare($managerEntitati, $denumire))->beforeSave(
            $prezenta,
            SaveOptions::fromAssoc([])
        );
    }

    public function testActualizareaNuRescrieNumeleSauEchipele(): void
    {
        $prezenta = $this->createStub(EntitatePrincipala::class);
        $prezenta->method('isNew')->willReturn(false);
        $managerEntitati = $this->createMock(EntityManager::class);
        $managerEntitati->expects(self::never())->method('getEntityById');
        $denumire = $this->createMock(DenumirePrezenta::class);
        $denumire->expects(self::never())->method('aplica');

        (new PregatesteLaCreare($managerEntitati, $denumire))->beforeSave(
            $prezenta,
            SaveOptions::fromAssoc([])
        );
    }
}
