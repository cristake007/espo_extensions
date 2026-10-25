<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CalculatorZileConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CereriConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\DocumenteConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\InstantaneuDocumenteConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\NotificatorCereriConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ProfiluriConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SetariConcedii;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SolduriConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\ORM\BaseEntity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class CereriConcediuAprobariTest extends TestCase
{
    public function testListaManagerialaClasificaInBackendInclusivAnularileFinalizate(): void
    {
        $cereri = [
            $this->creeazaCerere('cerere-asteptare', 'InAsteptare'),
            $this->creeazaCerere('cerere-respinsa', 'Respinsa'),
            $this->creeazaCerere('cerere-anulare', 'AnulareSolicitata'),
            $this->creeazaCerere('cerere-anulata', 'Anulata'),
            $this->creeazaCerere('cerere-aprobata', 'Aprobata'),
        ];
        $selectieCereri = $this->createStub(RDBSelectBuilder::class);
        $selectieCereri->method('find')->willReturn(new EntityCollection($cereri));
        $depozitCereri = $this->createStub(RDBRepository::class);
        $depozitCereri->method('order')->willReturn($selectieCereri);

        $selectieDocumente = $this->createStub(RDBSelectBuilder::class);
        $selectieDocumente->method('order')->willReturnSelf();
        $selectieDocumente->method('find')->willReturn(new EntityCollection([]));
        $depozitDocumente = $this->createStub(RDBRepository::class);
        $depozitDocumente->method('where')->willReturn($selectieDocumente);

        $angajatActiv = $this->createStub(User::class);
        $angajatActiv->method('getId')->willReturn('angajat-1');
        $angajatActiv->method('get')->willReturnMap([
            ['dataAngajare', '2020-01-15'],
        ]);
        $selectieAngajati = $this->createStub(RDBSelectBuilder::class);
        $selectieAngajati->method('find')->willReturn(new EntityCollection([$angajatActiv]));
        $depozitAngajati = $this->createStub(RDBRepository::class);
        $depozitAngajati->method('where')->willReturn($selectieAngajati);

        $managerEntitati = $this->createStub(EntityManager::class);
        $managerEntitati->method('getRDBRepository')->willReturn($depozitCereri);
        $managerEntitati->method('getRDBRepositoryByClass')->willReturnCallback(
            static fn (string $clasa): RDBRepository =>
                $clasa === User::class ? $depozitAngajati : $depozitDocumente
        );
        $utilizator = $this->createStub(User::class);
        $utilizator->method('isAdmin')->willReturn(true);
        $utilizator->method('getId')->willReturn('administrator-1');

        $serviciu = new CereriConcediu(
            $managerEntitati,
            $utilizator,
            $this->createStub(Acl::class),
            new DateTime(timeZone: 'UTC'),
            $this->createStub(CalculatorZileConcediu::class),
            $this->createStub(ProfiluriConcediu::class),
            $this->createStub(SolduriConcediu::class),
            $this->createStub(SetariConcedii::class),
            $this->createStub(InstantaneuDocumenteConcediu::class),
            $this->createStub(DocumenteConcediu::class),
            $this->createStub(NotificatorCereriConcediu::class),
            $this->createStub(Language::class),
            $this->createStub(ConfiguratiiCondicaAngajati::class),
            new EligibilitateAngajat(),
        );

        $rezultat = $serviciu->listaManageriala('2027-01');

        self::assertSame(5, $rezultat['total']);
        self::assertSame(0, $rezultat['deSolutionat']);
        self::assertSame(
            ['InAsteptare', 'Respinsa'],
            array_column($rezultat['sectiuni']['cereri'], 'stare')
        );
        self::assertSame(
            ['AnulareSolicitata', 'Anulata'],
            array_column($rezultat['sectiuni']['anulari'], 'stare')
        );
        self::assertSame(
            ['Aprobata'],
            array_column($rezultat['sectiuni']['aprobate'], 'stare')
        );
        self::assertSame([
            'luna' => '2027-01',
            'angajatiInConcediu' => 1,
            'concediiAprobate' => 2,
            'cereriInAsteptare' => 1,
            'anulariSolicitate' => 1,
        ], array_diff_key($rezultat['rezumatLunar'], ['detalii' => true]));
        self::assertSame([[
            'id' => 'angajat-1',
            'nume' => 'Angajat Test',
            'numarConcedii' => 2,
        ]], $rezultat['rezumatLunar']['detalii']['angajatiInConcediu']);
        self::assertSame(
            ['cerere-anulare', 'cerere-aprobata'],
            array_column($rezultat['rezumatLunar']['detalii']['concediiAprobate'], 'id')
        );
        self::assertSame(
            ['cerere-asteptare'],
            array_column($rezultat['rezumatLunar']['detalii']['cereriInAsteptare'], 'id')
        );
        self::assertSame(
            ['cerere-anulare'],
            array_column($rezultat['rezumatLunar']['detalii']['anulariSolicitate'], 'id')
        );
        self::assertSame('2020-01', $rezultat['lunaMinimaDisponibila']);

        try {
            $serviciu->listaManageriala('2019-12');
            self::fail('O lună anterioară primei angajări trebuia respinsă de backend.');
        } catch (BadRequest $exceptie) {
            self::assertSame('concediiInainteDePrimulAngajat', $exceptie->getMessage());
        }
    }

    public function testStatisticileLunareIgnoraAngajatiiInactiviDarPastreazaIstoricul(): void
    {
        $cerere = $this->creeazaCerere('cerere-fost-angajat', 'Aprobata');
        $selectieCereri = $this->createStub(RDBSelectBuilder::class);
        $selectieCereri->method('find')->willReturn(new EntityCollection([$cerere]));
        $depozitCereri = $this->createStub(RDBRepository::class);
        $depozitCereri->method('order')->willReturn($selectieCereri);

        $selectieFaraAngajatiActivi = $this->createStub(RDBSelectBuilder::class);
        $selectieFaraAngajatiActivi->method('find')->willReturn(new EntityCollection([]));
        $depozitAngajati = $this->createStub(RDBRepository::class);
        $depozitAngajati->method('where')->willReturn($selectieFaraAngajatiActivi);

        $selectieDocumente = $this->createStub(RDBSelectBuilder::class);
        $selectieDocumente->method('order')->willReturnSelf();
        $selectieDocumente->method('find')->willReturn(new EntityCollection([]));
        $depozitDocumente = $this->createStub(RDBRepository::class);
        $depozitDocumente->method('where')->willReturn($selectieDocumente);

        $managerEntitati = $this->createStub(EntityManager::class);
        $managerEntitati->method('getRDBRepository')->willReturn($depozitCereri);
        $managerEntitati->method('getRDBRepositoryByClass')->willReturnCallback(
            static fn (string $clasa): RDBRepository =>
                $clasa === User::class ? $depozitAngajati : $depozitDocumente
        );
        $utilizator = $this->createStub(User::class);
        $utilizator->method('isAdmin')->willReturn(true);

        $serviciu = new CereriConcediu(
            $managerEntitati,
            $utilizator,
            $this->createStub(Acl::class),
            new DateTime(timeZone: 'UTC'),
            $this->createStub(CalculatorZileConcediu::class),
            $this->createStub(ProfiluriConcediu::class),
            $this->createStub(SolduriConcediu::class),
            $this->createStub(SetariConcedii::class),
            $this->createStub(InstantaneuDocumenteConcediu::class),
            $this->createStub(DocumenteConcediu::class),
            $this->createStub(NotificatorCereriConcediu::class),
            $this->createStub(Language::class),
            $this->createStub(ConfiguratiiCondicaAngajati::class),
            new EligibilitateAngajat(),
        );

        $rezultat = $serviciu->listaManageriala('2027-01');

        self::assertSame(1, $rezultat['total']);
        self::assertSame(
            ['cerere-fost-angajat'],
            array_column($rezultat['sectiuni']['aprobate'], 'id')
        );
        self::assertSame(0, $rezultat['rezumatLunar']['angajatiInConcediu']);
        self::assertSame(0, $rezultat['rezumatLunar']['concediiAprobate']);
        self::assertSame([], $rezultat['rezumatLunar']['detalii']['angajatiInConcediu']);
        self::assertSame([], $rezultat['rezumatLunar']['detalii']['concediiAprobate']);
    }

    public function testSpatiulPersonalSiCreareaSuntBlocateCatTimpAngajatulEsteInCic(): void
    {
        $managerEntitati = $this->createMock(EntityManager::class);
        $managerEntitati->expects(self::never())->method('getRDBRepository');
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn('angajat-1');
        $utilizator->method('isAdmin')->willReturn(false);
        $utilizator->method('isRegular')->willReturn(true);
        $utilizator->method('isActive')->willReturn(true);
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $configuratii = $this->createStub(ConfiguratiiCondicaAngajati::class);
        $configuratii->method('obtineSituatieCic')->willReturn([
            'activa' => true,
            'dataInceput' => '2026-10-24',
            'dataSfarsit' => '2028-10-23',
        ]);
        $serviciu = new CereriConcediu(
            $managerEntitati,
            $utilizator,
            $acl,
            new DateTime(timeZone: 'UTC'),
            $this->createStub(CalculatorZileConcediu::class),
            $this->createStub(ProfiluriConcediu::class),
            $this->createStub(SolduriConcediu::class),
            $this->createStub(SetariConcedii::class),
            $this->createStub(InstantaneuDocumenteConcediu::class),
            $this->createStub(DocumenteConcediu::class),
            $this->createStub(NotificatorCereriConcediu::class),
            $this->createStub(Language::class),
            $configuratii,
            new EligibilitateAngajat(),
        );

        self::assertSame([
            'disponibil' => false,
            'motivIndisponibil' => 'angajatInCic',
            'situatieCic' => [
                'activa' => true,
                'dataInceput' => '2026-10-24',
                'dataSfarsit' => '2028-10-23',
            ],
            'sold' => null,
            'cereri' => [],
        ], $serviciu->spatiuPersonal());

        $this->expectException(Conflict::class);
        $this->expectExceptionMessage('concediuIndisponibilInCic');
        $serviciu->previzualizeazaPerioada([
            'dataInceput' => '2028-11-01',
            'dataSfarsit' => '2028-11-02',
        ]);
    }

    public function testManagerulNuPoateDecideDinNouDupaCeAConfirmatCererea(): void
    {
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => array_fill_keys([
            'id',
            'stare',
            'assignedUserId',
            'aprobatoriNecesari',
            'aprobatoriConfirmati',
        ], [])]);
        $cerere->set([
            'id' => 'cerere-1',
            'stare' => 'InAsteptare',
            'assignedUserId' => 'angajat-1',
            'aprobatoriNecesari' => ['manager-1', 'manager-2'],
            'aprobatoriConfirmati' => ['manager-1'],
        ]);
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('forUpdate')->willReturnSelf();
        $selectie->method('findOne')->willReturn($cerere);
        $depozit = $this->createStub(RDBRepository::class);
        $depozit->method('where')->willReturn($selectie);
        $tranzactii = $this->createStub(TransactionManager::class);
        $tranzactii->method('run')->willReturnCallback(
            static fn (\Closure $operatie): mixed => $operatie()
        );
        $managerEntitati = $this->createStub(EntityManager::class);
        $managerEntitati->method('getRDBRepository')->willReturn($depozit);
        $managerEntitati->method('getTransactionManager')->willReturn($tranzactii);
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn('manager-1');
        $utilizator->method('isAdmin')->willReturn(false);
        $utilizator->method('isRegular')->willReturn(true);
        $utilizator->method('isActive')->willReturn(true);
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $acl->method('checkEntityEdit')->willReturn(true);
        $acl->method('getLevel')->willReturn(Table::LEVEL_ALL);

        $serviciu = new CereriConcediu(
            $managerEntitati,
            $utilizator,
            $acl,
            new DateTime(timeZone: 'UTC'),
            $this->createStub(CalculatorZileConcediu::class),
            $this->createStub(ProfiluriConcediu::class),
            $this->createStub(SolduriConcediu::class),
            $this->createStub(SetariConcedii::class),
            $this->createStub(InstantaneuDocumenteConcediu::class),
            $this->createStub(DocumenteConcediu::class),
            $this->createStub(NotificatorCereriConcediu::class),
            $this->createStub(Language::class),
            $this->createStub(ConfiguratiiCondicaAngajati::class),
            new EligibilitateAngajat(),
        );

        $this->expectException(Conflict::class);
        $this->expectExceptionMessage('decizieManagerDejaInregistrata');
        $serviciu->decide('cerere-1', 'Aproba', null);
    }

    private function creeazaCerere(string $id, string $stare): BaseEntity
    {
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => array_fill_keys([
            'id',
            'name',
            'assignedUserId',
            'assignedUserName',
            'dataInceput',
            'dataSfarsit',
            'numarZile',
            'stare',
            'descriere',
            'motivAnulare',
            'aprobariNecesare',
            'aprobatoriConfirmati',
            'aprobatoriNecesari',
            'aprobatoriAnulareConfirmati',
            'instantaneuDocumente',
        ], [])]);
        $cerere->set([
            'id' => $id,
            'name' => 'Angajat Test',
            'assignedUserId' => 'angajat-1',
            'assignedUserName' => $id === 'cerere-anulare' ? '' : 'Angajat Test',
            'dataInceput' => '2027-01-11',
            'dataSfarsit' => '2027-01-12',
            'numarZile' => 2,
            'stare' => $stare,
            'descriere' => '',
            'motivAnulare' => $stare === 'Anulata' ? 'Plan schimbat' : null,
            'aprobariNecesare' => 1,
            'aprobatoriConfirmati' => [],
            'aprobatoriNecesari' => [],
            'aprobatoriAnulareConfirmati' => [],
            'instantaneuDocumente' => null,
        ]);

        return $cerere;
    }
}
