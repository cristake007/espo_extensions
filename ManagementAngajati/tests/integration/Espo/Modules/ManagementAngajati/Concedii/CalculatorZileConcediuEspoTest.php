<?php

namespace tests\integration\Espo\Modules\ManagementAngajati\Concedii;

use Espo\Core\Application;
use Espo\Core\Application\ApplicationParams;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Entities\WorkingTimeCalendar;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CalculatorZileConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CorectorProgramAcordareConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\GeneratorDocumentConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\InstantaneuDocumenteConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ProfiluriConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SolduriConcediu;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/** Verifică intervalele de concediu prin WTC și sărbătorile reale ale integrării. */
class CalculatorZileConcediuEspoTest extends TestCase
{
    private Application $aplicatie;
    private EntityManager $managerEntitati;
    private InjectableFactory $fabricaInjectabila;
    private string $directorInitial;

    protected function setUp(): void
    {
        $this->directorInitial = (string) getcwd();
        $radacinaTeste = dirname(__DIR__, 6);
        $directorSite = is_dir($radacinaTeste . '/application/Espo') ?
            $radacinaTeste :
            $radacinaTeste . '/site';
        chdir($directorSite);

        try {
            $this->aplicatie = new Application(new ApplicationParams(noErrorHandler: true));
            $this->aplicatie->setupSystemUser();
            $container = $this->aplicatie->getContainer();
            $this->managerEntitati = $container->getByClass(EntityManager::class);
            $this->fabricaInjectabila = $container->getByClass(InjectableFactory::class);
            $this->managerEntitati->getTransactionManager()->start();
        } catch (\Throwable $exceptie) {
            chdir($this->directorInitial);

            throw $exceptie;
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->managerEntitati)) {
            $tranzactii = $this->managerEntitati->getTransactionManager();

            if ($tranzactii->isStarted()) {
                $tranzactii->rollback();
            }
        }

        chdir($this->directorInitial);
    }

    public function testIntervalulDintreAniRespectaWtcWeekendulSiSarbatorile(): void
    {
        $this->curataSarbatorileDinInterval('2026-12-28', '2027-01-05');
        $this->creeazaSarbatoare('2027-01-01', 'Anul Nou test');
        $angajat = $this->creeazaAngajatCuProgramLuniVineri();
        $calculator = $this->fabricaInjectabila->create(CalculatorZileConcediu::class);

        $panaPeCinciIanuarie = $calculator->calculeaza(
            $angajat,
            '2026-12-28',
            '2027-01-05'
        );

        self::assertSame(6, $panaPeCinciIanuarie['total']);
        self::assertSame([2026 => 4, 2027 => 2], $panaPeCinciIanuarie['peAni']);
        self::assertSame([
            '2026-12-28',
            '2026-12-29',
            '2026-12-30',
            '2026-12-31',
            '2027-01-04',
            '2027-01-05',
        ], $panaPeCinciIanuarie['date']);
        self::assertNotContains('2027-01-01', $panaPeCinciIanuarie['date']);
        self::assertNotContains('2027-01-02', $panaPeCinciIanuarie['date']);
        self::assertNotContains('2027-01-03', $panaPeCinciIanuarie['date']);

        $panaPePatruIanuarie = $calculator->calculeaza(
            $angajat,
            '2026-12-28',
            '2027-01-04'
        );

        self::assertSame(5, $panaPePatruIanuarie['total']);
        self::assertSame([2026 => 4, 2027 => 1], $panaPePatruIanuarie['peAni']);

        $this->creeazaSarbatoare('2026-12-31', 'Zi liberă suplimentară test');
        $cuTreizeciSiUnuDecembrieLiber = $calculator->calculeaza(
            $angajat,
            '2026-12-28',
            '2027-01-05'
        );

        self::assertSame(5, $cuTreizeciSiUnuDecembrieLiber['total']);
        self::assertSame([2026 => 3, 2027 => 2], $cuTreizeciSiUnuDecembrieLiber['peAni']);
        self::assertNotContains('2026-12-31', $cuTreizeciSiUnuDecembrieLiber['date']);
    }

    public function testMutareaAcordariiNuRescrieDocumentulIstoricSiActiveazaDreptulViitor(): void
    {
        $this->curataSarbatorileDinInterval('2026-12-28', '2027-01-05');
        $this->creeazaSarbatoare('2027-01-01', 'Anul Nou test');
        $angajat = $this->creeazaAngajatCuProgramLuniVineri();
        $profiluri = $this->fabricaInjectabila->create(ProfiluriConcediu::class);
        $solduri = $this->fabricaInjectabila->create(SolduriConcediu::class);
        $calculator = $this->fabricaInjectabila->create(CalculatorZileConcediu::class);
        $instantanee = $this->fabricaInjectabila->create(InstantaneuDocumenteConcediu::class);

        $profiluri->initializeaza([
            'idAngajat' => $angajat->getId(),
            'zileAnuale' => 21,
            'zileDisponibileAnCurent' => 5,
            'zileReportateAnAnterior' => 0,
            'dataUrmatoareiAcordari' => '2027-03-01',
        ]);
        $profil = $this->managerEntitati->getRDBRepository('ProfilConcediu')
            ->where(['assignedUserId' => $angajat->getId()])
            ->findOne();
        self::assertNotNull($profil);

        $calcul = $calculator->calculeaza($angajat, '2026-12-28', '2027-01-04');
        self::assertSame(5, $calcul['total']);
        self::assertSame([2026 => 4, 2027 => 1], $calcul['peAni']);

        $cerere = $this->creeazaCerere(
            $angajat,
            $profil->getId(),
            '2026-12-28',
            '2027-01-04',
            5
        );
        $solduri->rezerva($cerere, $profil, $calcul['date']);
        $instantaneuInitial = $instantanee->creeaza($cerere);
        $cerere->set('instantaneuDocumente', $instantaneuInitial);
        $solduri->consuma($cerere, $profil);
        $cerere->set('stare', 'Aprobata');
        $this->managerEntitati->saveEntity($cerere);

        $segmente = $instantaneuInitial['segmente'];
        self::assertCount(2, $segmente);
        self::assertSame('2026-12', $segmente[0]['luna']);
        self::assertSame(4, $segmente[0]['zileSolicitate']);
        self::assertSame(5, $segmente[0]['zileAnuale']);
        self::assertSame('2027-01', $segmente[1]['luna']);
        self::assertSame(1, $segmente[1]['zileSolicitate']);
        self::assertSame(0, $segmente[1]['zileAnuale']);
        self::assertSame(1, $segmente[1]['zileReportate']);

        $xmlInainte = $this->xmlDocument(
            $this->fabricaInjectabila->create(GeneratorDocumentConcediu::class)
                ->genereaza($instantaneuInitial, $segmente[1])
        );
        self::assertStringContainsString('1 - report 2026', $xmlInainte);
        self::assertStringNotContainsString('21 - 2027', $xmlInainte);

        $corector = $this->fabricaInjectabila->create(CorectorProgramAcordareConcediu::class);
        $previzualizare = $corector->previzualizeaza($angajat->getId(), '2027-01-01');
        self::assertTrue($previzualizare['permis']);
        self::assertSame(1, $previzualizare['drepturiAfectate']);
        $corector->corecteaza(
            $angajat->getId(),
            '2027-01-01',
            'Corectare dată acordare pentru test'
        );

        $profilActualizat = $this->managerEntitati->getRDBRepository('ProfilConcediu')
            ->where(['id' => $profil->getId()])
            ->findOne();
        $drept2027 = $this->managerEntitati->getRDBRepository('DreptConcediu')
            ->where([
                'profilId' => $profil->getId(),
                'anOrigine' => 2027,
                'tip' => 'Anual',
            ])
            ->findOne();
        self::assertNotNull($profilActualizat);
        self::assertNotNull($drept2027);
        self::assertSame('2027-01-01', $profilActualizat->get('dataUrmatoareiAcordari'));
        self::assertSame('2027-01-01', $drept2027->get('dataAcordare'));
        self::assertSame(21, $drept2027->get('zileAcordate'));

        $cerereActualizata = $this->managerEntitati->getRDBRepository('CerereConcediu')
            ->where(['id' => $cerere->getId()])
            ->findOne();
        self::assertNotNull($cerereActualizata);
        $instantaneuPastrat = $cerereActualizata->get('instantaneuDocumente');
        $instantaneuPastrat = is_object($instantaneuPastrat) ?
            get_object_vars($instantaneuPastrat) :
            $instantaneuPastrat;
        self::assertIsArray($instantaneuPastrat);
        $jsonInitial = json_encode($instantaneuInitial, JSON_THROW_ON_ERROR);
        $jsonPastrat = json_encode($instantaneuPastrat, JSON_THROW_ON_ERROR);
        self::assertJsonStringEqualsJsonString($jsonInitial, $jsonPastrat);

        $segmentePastrate = (array) $instantaneuPastrat['segmente'];
        $segmentIanuarie = is_object($segmentePastrate[1]) ?
            get_object_vars($segmentePastrate[1]) :
            $segmentePastrate[1];
        $xmlDupa = $this->xmlDocument(
            $this->fabricaInjectabila->create(GeneratorDocumentConcediu::class)
                ->genereaza($instantaneuPastrat, $segmentIanuarie)
        );
        self::assertStringContainsString('1 - report 2026', $xmlDupa);
        self::assertStringNotContainsString('21 - 2027', $xmlDupa);

        $cerereNoua = $this->creeazaCerere(
            $angajat,
            $profil->getId(),
            '2027-01-05',
            '2027-01-05',
            1
        );
        $solduri->rezerva($cerereNoua, $profilActualizat, ['2027-01-05']);
        $drept2027Actualizat = $this->managerEntitati->getRDBRepository('DreptConcediu')
            ->where(['id' => $drept2027->getId()])
            ->findOne();
        self::assertNotNull($drept2027Actualizat);
        self::assertSame(1, $drept2027Actualizat->get('zileRezervate'));
    }

    private function creeazaAngajatCuProgramLuniVineri(): User
    {
        $calendar = $this->managerEntitati->getNewEntity(WorkingTimeCalendar::ENTITY_TYPE);
        $calendar->setMultiple([
            'name' => 'Calendar concediu luni-vineri',
            'timeZone' => 'Europe/Bucharest',
            'timeRanges' => [['09:00', '17:00']],
            'weekday0' => false,
            'weekday1' => true,
            'weekday2' => true,
            'weekday3' => true,
            'weekday4' => true,
            'weekday5' => true,
            'weekday6' => false,
        ]);
        $this->managerEntitati->saveEntity($calendar);

        $angajat = $this->managerEntitati->getNewEntity(User::ENTITY_TYPE);
        $angajat->setMultiple([
            'userName' => 'test-calculator-concediu',
            'lastName' => 'Calculator concediu',
            'type' => User::TYPE_REGULAR,
            'isActive' => true,
            'workingTimeCalendarId' => $calendar->getId(),
            'dataAngajare' => '2020-01-01',
        ]);
        $this->managerEntitati->saveEntity($angajat);

        $angajatSalvat = $this->managerEntitati
            ->getRDBRepository(User::ENTITY_TYPE)
            ->getById($angajat->getId());
        self::assertInstanceOf(User::class, $angajatSalvat);

        return $angajatSalvat;
    }

    private function creeazaSarbatoare(string $data, string $nume): void
    {
        $sarbatoare = $this->managerEntitati->getNewEntity('ZileLibere');
        $sarbatoare->setMultiple([
            'name' => $nume,
            'dateStart' => $data,
            'countryCode' => 'RO',
            'source' => 'manual',
            'managed' => false,
            'holidayTypes' => ['Public'],
            'nationalHoliday' => true,
        ]);
        $this->managerEntitati->saveEntity($sarbatoare);
    }

    private function creeazaCerere(
        User $angajat,
        string $idProfil,
        string $inceput,
        string $sfarsit,
        int $zile,
    ): \Espo\ORM\Entity {
        $cerere = $this->managerEntitati->getNewEntity('CerereConcediu');
        $cerere->set([
            'name' => sprintf('Cerere test · %s – %s', $inceput, $sfarsit),
            'dataInceput' => $inceput,
            'dataSfarsit' => $sfarsit,
            'numarZile' => $zile,
            'stare' => 'InAsteptare',
            'profilId' => $idProfil,
            'assignedUserId' => $angajat->getId(),
            'assignedUserName' => $angajat->getName(),
            'aprobariNecesare' => 1,
            'aprobatoriNecesari' => [],
            'aprobatoriConfirmati' => [],
        ]);
        $this->managerEntitati->saveEntity($cerere);

        return $cerere;
    }

    private function xmlDocument(string $continut): string
    {
        $cale = tempnam(sys_get_temp_dir(), 'test-flux-acordare-');
        self::assertNotFalse($cale);
        file_put_contents($cale, $continut);
        $arhiva = new ZipArchive();

        try {
            self::assertTrue($arhiva->open($cale));
            $xml = $arhiva->getFromName('word/document.xml');
            self::assertIsString($xml);

            return $xml;
        } finally {
            $arhiva->close();
            unlink($cale);
        }
    }

    private function curataSarbatorileDinInterval(string $inceput, string $sfarsit): void
    {
        $sarbatori = $this->managerEntitati->getRDBRepository('ZileLibere')
            ->where([
                'dateStart>=' => $inceput,
                'dateStart<=' => $sfarsit,
                'countryCode' => 'RO',
            ])
            ->find();

        foreach ($sarbatori as $sarbatoare) {
            $this->managerEntitati->removeEntity($sarbatoare);
        }
    }
}
