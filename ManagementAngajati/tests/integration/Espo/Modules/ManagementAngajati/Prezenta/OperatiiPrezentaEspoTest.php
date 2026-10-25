<?php

namespace tests\integration\Espo\Modules\ManagementAngajati\Prezenta;

use DateTimeImmutable;
use Espo\Core\Application;
use Espo\Core\Application\ApplicationParams;
use Espo\Core\Acl;
use Espo\Core\AclManager;
use Espo\Core\Binding\BindingContainerBuilder;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\ServiceFactory;
use Espo\Core\Record\UpdateParams;
use Espo\Core\Select\SearchParams;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Entities\Notification;
use Espo\Entities\Role;
use Espo\Entities\User;
use Espo\Entities\WorkingTimeCalendar;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CalendarPrezentaPersonala;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CicluLunarCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CondicaPrezentaLunara;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\MarcatorPrezentaCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\SalvatorSetariCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\SetariCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\TrimitatorReminderePrezenta;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

/** Verificare functionala pe serviciile Record, ORM si ACL reale ale instantei EspoCRM. */
class OperatiiPrezentaEspoTest extends TestCase
{
    private Application $aplicatie;
    private EntityManager $managerEntitati;
    private ServiceFactory $fabricaServicii;
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
            $container->getByClass(Config::class)->set('baselineRoleId', null);
            $this->managerEntitati = $container->getByClass(EntityManager::class);
            $this->fabricaInjectabila = $container->getByClass(InjectableFactory::class);
            $this->fabricaServicii = $this->fabricaInjectabila->create(ServiceFactory::class);
            $this->managerEntitati->getTransactionManager()->start();
        } catch (\Throwable $exceptie) {
            chdir($this->directorInitial);

            throw $exceptie;
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->managerEntitati)) {
            $managerTranzactii = $this->managerEntitati->getTransactionManager();

            if ($managerTranzactii->isStarted()) {
                $managerTranzactii->rollback();
            }
        }

        chdir($this->directorInitial);
    }

    public function testCreareModificareDuplicatSiAutorizarePrinServiciulRecordNativ(): void
    {
        $calendar = $this->creeazaCalendarDeTest();
        $angajat = $this->creeazaUtilizator(
            'test-prezenta-angajat',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no']
        );
        $altAngajat = $this->creeazaUtilizator(
            'test-prezenta-alt-angajat',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no']
        );
        $faraAcces = $this->creeazaUtilizator(
            'test-prezenta-fara-acces',
            $calendar->getId(),
            ['create' => 'no', 'read' => 'no', 'edit' => 'no', 'delete' => 'no']
        );
        $dataPrezenta = $this->obtineDataValida();
        $dataSarbatoareTest = $this->creeazaSarbatoareDeTest(
            substr($dataPrezenta, 0, 7),
            $dataPrezenta
        );
        $calendarPrezentaPersonala = $this->fabricaInjectabila->create(CalendarPrezentaPersonala::class);
        $dateCalendar = $calendarPrezentaPersonala->obtine($angajat, substr($dataPrezenta, 0, 7));
        $dateAfisate = array_column($dateCalendar['zile'], 'data');
        $ziCalendar = array_values(array_filter(
            $dateCalendar['zile'],
            fn (array $zi): bool => $zi['data'] === $dataPrezenta
        ));

        self::assertNotContains($dataSarbatoareTest, $dateAfisate);
        self::assertNotEmpty($dateCalendar['zile']);

        foreach ($dateCalendar['zile'] as $zi) {
            self::assertLessThanOrEqual(5, $zi['ziSaptamana']);
            self::assertFalse($zi['esteSarbatoareLegala']);
        }

        self::assertCount(1, $ziCalendar);
        self::assertTrue($ziCalendar[0]['poateFiCompletata']);

        $serviciuAngajat = $this->fabricaServicii->createForUser('Prezenta', $angajat);

        $rezultatCreare = $serviciuAngajat->create((object) [
            'dataPrezenta' => $dataPrezenta,
            'codPrezenta' => 'P',
            'assignedUserId' => $angajat->getId(),
            'assignedUserName' => $angajat->getName(),
        ], CreateParams::create());
        $idPrezenta = $rezultatCreare->getId();

        self::assertNotSame('', $idPrezenta);
        self::assertSame('P', $rezultatCreare->getEntity()->get('codPrezenta'));
        self::assertSame($angajat->getId(), $rezultatCreare->getEntity()->get('assignedUserId'));

        $rezultatModificare = $serviciuAngajat->update(
            $idPrezenta,
            (object) [
                'codPrezenta' => 'D',
                'dataPrezenta' => '2000-01-01',
                'assignedUserId' => $altAngajat->getId(),
            ],
            UpdateParams::create()
        );

        self::assertSame('D', $rezultatModificare->getEntity()->get('codPrezenta'));
        self::assertSame($dataPrezenta, $rezultatModificare->getEntity()->get('dataPrezenta'));
        self::assertSame($angajat->getId(), $rezultatModificare->getEntity()->get('assignedUserId'));

        $this->asteaptaInterzis(function () use ($serviciuAngajat, $dataPrezenta, $angajat): void {
            $serviciuAngajat->create((object) [
                'dataPrezenta' => $dataPrezenta,
                'codPrezenta' => 'CM',
                'assignedUserId' => $angajat->getId(),
            ]);
        }, false);

        $serviciuAltAngajat = $this->fabricaServicii->createForUser('Prezenta', $altAngajat);
        try {
            $serviciuAltAngajat->read($idPrezenta);
            self::fail('Prezentaul altui angajat nu trebuia să fie vizibil.');
        } catch (Forbidden) {
            self::assertTrue(true);
        }
        $this->asteaptaInterzis(
            fn () => $serviciuAltAngajat->update($idPrezenta, (object) ['codPrezenta' => 'CM'])
        );

        $serviciuFaraAcces = $this->fabricaServicii->createForUser('Prezenta', $faraAcces);
        $this->asteaptaInterzis(function () use ($serviciuFaraAcces, $dataPrezenta, $faraAcces): void {
            $serviciuFaraAcces->create((object) [
                'dataPrezenta' => $dataPrezenta,
                'codPrezenta' => 'P',
                'assignedUserId' => $faraAcces->getId(),
            ]);
        });

        $administrator = $this->managerEntitati
            ->getRDBRepository(User::ENTITY_TYPE)
            ->where(['type' => User::TYPE_ADMIN, 'isActive' => true])
            ->findOne();

        self::assertInstanceOf(User::class, $administrator);

        $serviciuAdministrator = $this->fabricaServicii
            ->createForUser('Prezenta', $administrator);
        $rezultatAdministrator = $serviciuAdministrator->update(
            $idPrezenta,
            (object) ['codPrezenta' => 'CM']
        );

        self::assertSame('CM', $rezultatAdministrator->getEntity()->get('codPrezenta'));

        $listaAdministrator = $serviciuAdministrator->find(
            SearchParams::create()
                ->withSelect(['name'])
                ->withOrderBy('dataPrezenta')
                ->withOrder(SearchParams::ORDER_DESC)
                ->withMaxSize(20)
        );

        self::assertGreaterThanOrEqual(1, $listaAdministrator->getTotal());

        try {
            $serviciuAdministrator->create((object) [
                'dataPrezenta' => $dataPrezenta,
                'codPrezenta' => 'P',
                'assignedUserId' => $administrator->getId(),
                'assignedUserName' => $administrator->getName(),
            ]);
            self::fail('Administratorul nu trebuie să poată fi subiectul unui prezenta.');
        } catch (BadRequest $exceptie) {
            $corp = $exceptie->getBody();

            self::assertNotNull($corp);
            $dateEroare = json_decode($corp, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('angajatInvalid', $dateEroare['messageTranslation']['label']);
        }
    }

    public function testConfiguratieCicProiectieBlocareConflicteSiAclManagerial(): void
    {
        $calendar = $this->creeazaCalendarDeTest();
        $angajat = $this->creeazaUtilizator(
            'test-cic-angajat',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no']
        );
        $angajatCuPrezenta = $this->creeazaUtilizator(
            'test-cic-conflict',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no']
        );
        $manager = $this->creeazaUtilizator(
            'test-cic-manager',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'no'],
            true,
            'no'
        );
        $dataInceputCic = (new DateTimeImmutable('today'))->format('Y-m-d');
        $dataCic = $this->obtineDataCicValida();
        $serviciuConfiguratiiManager = $this->fabricaServicii
            ->createForUser('ConfiguratieCondicaAngajat', $manager);

        $configuratie = $serviciuConfiguratiiManager->create((object) [
            'angajatId' => $angajat->getId(),
            'cicActiv' => true,
            'dataInceputCic' => $dataInceputCic,
            'dataSfarsitCic' => $dataCic,
            'remoteActiv' => false,
            'assignedUserId' => $manager->getId(),
        ]);

        self::assertTrue((bool) $configuratie->getEntity()->get('cicActiv'));
        self::assertSame($dataInceputCic, $configuratie->getEntity()->get('dataInceputCic'));
        self::assertSame($angajat->getId(), $configuratie->getEntity()->get('angajatId'));
        self::assertSame($manager->getId(), $configuratie->getEntity()->get('assignedUserId'));

        try {
            $serviciuConfiguratiiManager->create((object) [
                'angajatId' => $angajat->getId(),
                'cicActiv' => false,
                'remoteActiv' => true,
                'assignedUserId' => $manager->getId(),
            ]);
            self::fail('A doua configurație a aceluiași angajat trebuia respinsă.');
        } catch (Conflict $exceptie) {
            $corp = json_decode(
                (string) $exceptie->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame(
                'configuratiePrezentaDuplicata',
                $corp['messageTranslation']['label']
            );
        }

        $calendarPrezentaPersonala = $this->fabricaInjectabila->create(CalendarPrezentaPersonala::class);
        $dateCalendar = $calendarPrezentaPersonala->obtine($angajat, substr($dataCic, 0, 7));
        $ziCic = array_values(array_filter(
            $dateCalendar['zile'],
            fn (array $zi): bool => $zi['data'] === $dataCic
        ));

        self::assertCount(1, $ziCic);
        self::assertTrue($dateCalendar['situatieCic']['activa']);
        self::assertSame($dataInceputCic, $dateCalendar['situatieCic']['dataInceput']);
        self::assertSame($dataCic, $dateCalendar['situatieCic']['dataSfarsit']);
        self::assertTrue($ziCic[0]['esteCic']);
        self::assertSame('CIC', $ziCic[0]['codPrezentaAutomat']);
        self::assertFalse($ziCic[0]['poateFiCompletata']);

        $serviciuPrezentaAngajat = $this->fabricaServicii
            ->createForUser('Prezenta', $angajat);

        try {
            $serviciuPrezentaAngajat->create((object) [
                'dataPrezenta' => $dataInceputCic,
                'codPrezenta' => 'CM',
                'assignedUserId' => $angajat->getId(),
            ]);
            self::fail('Nici CM nu trebuie permis într-o perioadă CIC.');
        } catch (Conflict $exceptie) {
            $corp = json_decode(
                (string) $exceptie->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('ziAcoperitaDeCic', $corp['messageTranslation']['label']);
        }

        $prezentaConflictuala = $this->managerEntitati->getNewEntity('Prezenta');
        $prezentaConflictuala->set([
            'name' => $angajatCuPrezenta->getName() . ' · ' . $dataCic,
            'dataPrezenta' => $dataCic,
            'codPrezenta' => 'P',
            'assignedUserId' => $angajatCuPrezenta->getId(),
        ]);
        $this->managerEntitati->saveEntity($prezentaConflictuala);

        $salvatorSetari = $this->fabricaInjectabila->create(SalvatorSetariCondica::class);

        try {
            $salvatorSetari->salveaza(
                ConfiguratiiCondicaAngajati::MOD_REMOTE_CURENT,
                ConfiguratiiCondicaAngajati::LUNI_ANTERIOARE_EDITABILE_IMPLICIT,
                false,
                [(object) [
                    'angajatId' => $angajatCuPrezenta->getId(),
                    'remoteActiv' => false,
                    'cicActiv' => true,
                    'dataInceputCic' => $dataInceputCic,
                    'dataSfarsitCic' => $dataCic,
                ]]
            );
            self::fail('Salvarea managerială trebuia să respingă prezențele conflictuale.');
        } catch (Conflict $exceptie) {
            $corp = json_decode(
                (string) $exceptie->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('conflictePerioadaCic', $corp['messageTranslation']['label']);
        }

        $angajatInainteDeAngajare = $this->creeazaUtilizator(
            'test-cic-inainte-angajare',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no']
        );
        $dataInceputCicIstoric = (new DateTimeImmutable('today'))
            ->modify('-7 days')
            ->format('Y-m-d');
        $angajatInainteDeAngajare->set(
            'dataAngajare',
            (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d')
        );
        $this->managerEntitati->saveEntity($angajatInainteDeAngajare);

        try {
            $salvatorSetari->salveaza(
                ConfiguratiiCondicaAngajati::MOD_REMOTE_CURENT,
                ConfiguratiiCondicaAngajati::LUNI_ANTERIOARE_EDITABILE_IMPLICIT,
                false,
                [(object) [
                    'angajatId' => $angajatInainteDeAngajare->getId(),
                    'remoteActiv' => false,
                    'cicActiv' => true,
                    'dataInceputCic' => $dataInceputCicIstoric,
                    'dataSfarsitCic' => $dataCic,
                ]]
            );
            self::fail('Salvarea managerială trebuia să respingă începutul CIC anterior angajării.');
        } catch (BadRequest $exceptie) {
            $corp = json_decode(
                (string) $exceptie->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame(
                'perioadaCicInainteDeAngajare',
                $corp['messageTranslation']['label']
            );
        }

        $angajatInainteDeAngajare->set('dataAngajare', '2000-01-01');
        $this->managerEntitati->saveEntity($angajatInainteDeAngajare);
        $salvatorSetari->salveaza(
            ConfiguratiiCondicaAngajati::MOD_REMOTE_CURENT,
            ConfiguratiiCondicaAngajati::LUNI_ANTERIOARE_EDITABILE_IMPLICIT,
            false,
            [(object) [
                'angajatId' => $angajatInainteDeAngajare->getId(),
                'remoteActiv' => false,
                'cicActiv' => true,
                'dataInceputCic' => $dataInceputCicIstoric,
                'dataSfarsitCic' => $dataCic,
            ]]
        );

        $configuratieIstorica = $this->managerEntitati
            ->getRDBRepository(ConfiguratiiCondicaAngajati::TIP_ENTITATE)
            ->where(['angajatId' => $angajatInainteDeAngajare->getId()])
            ->findOne();
        self::assertNotNull($configuratieIstorica);
        self::assertSame(
            $dataInceputCicIstoric,
            $configuratieIstorica->get('dataInceputCic')
        );

        try {
            $serviciuConfiguratiiManager->create((object) [
                'angajatId' => $angajatCuPrezenta->getId(),
                'cicActiv' => true,
                'dataInceputCic' => $dataInceputCic,
                'dataSfarsitCic' => $dataCic,
                'assignedUserId' => $manager->getId(),
            ]);
            self::fail('Configurația CIC trebuia oprită de prezentaul existent.');
        } catch (Conflict $exceptie) {
            $corp = json_decode(
                (string) $exceptie->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('conflictePerioadaCic', $corp['messageTranslation']['label']);
            self::assertSame([], $corp['messageTranslation']['data']);
        }

        $condica = $this->fabricaInjectabila->create(CondicaPrezentaLunara::class)->obtine(
            substr($dataCic, 0, 7)
        );
        $angajatiCondica = array_column($condica['angajati'], null, 'idAngajat');
        $ziCondica = array_values(array_filter(
            $condica['zile'],
            fn (array $zi): bool => $zi['data'] === $dataCic
        ));

        self::assertArrayHasKey($angajat->getId(), $angajatiCondica);
        self::assertArrayHasKey($angajatCuPrezenta->getId(), $angajatiCondica);
        self::assertCount(1, $ziCondica);
        $celuleCondica = array_column($ziCondica[0]['celule'], null, 'idAngajat');
        self::assertSame('CIC', $celuleCondica[$angajat->getId()]['codPrezenta']);
        self::assertSame('CIC', $celuleCondica[$angajat->getId()]['sursa']);
        self::assertFalse($celuleCondica[$angajat->getId()]['poateFiCompletata']);
        self::assertFalse($celuleCondica[$angajat->getId()]['poateFiCorectata']);
        self::assertSame('P', $celuleCondica[$angajatCuPrezenta->getId()]['codPrezenta']);
        self::assertSame('manual', $celuleCondica[$angajatCuPrezenta->getId()]['sursa']);
        self::assertGreaterThan(0.0, $celuleCondica[$angajat->getId()]['orePlanificate']);

        $serviciuConfiguratiiAngajat = $this->fabricaServicii
            ->createForUser('ConfiguratieCondicaAngajat', $angajat);
        $this->asteaptaInterzis(function () use ($serviciuConfiguratiiAngajat, $angajat): void {
            $serviciuConfiguratiiAngajat->create((object) [
                'angajatId' => $angajat->getId(),
                'remoteActiv' => true,
                'cicActiv' => false,
            ]);
        });

        $administrator = $this->managerEntitati
            ->getRDBRepository(User::ENTITY_TYPE)
            ->where(['type' => User::TYPE_ADMIN, 'isActive' => true])
            ->findOne();
        self::assertInstanceOf(User::class, $administrator);
        $serviciuConfiguratiiAdministrator = $this->fabricaServicii
            ->createForUser('ConfiguratieCondicaAngajat', $administrator);
        $actualizata = $serviciuConfiguratiiAdministrator->update(
            $configuratie->getId(),
            (object) ['remoteActiv' => true]
        );
        self::assertTrue((bool) $actualizata->getEntity()->get('remoteActiv'));

        $setariCondica = $this->fabricaInjectabila->create(SetariCondica::class)->obtine();
        $configuratiiAngajat = array_values(array_filter(
            $setariCondica['angajati'],
            fn (array $dateAngajat): bool => $dateAngajat['idAngajat'] === $angajat->getId()
        ));

        self::assertCount(1, $configuratiiAngajat);
        self::assertSame($configuratie->getId(), $configuratiiAngajat[0]['idConfiguratie']);
        self::assertTrue($configuratiiAngajat[0]['remoteActiv']);
        self::assertTrue($configuratiiAngajat[0]['cicActiv']);
        self::assertTrue($configuratiiAngajat[0]['areCalendarLucru']);

        $this->fabricaInjectabila->create(SalvatorSetariCondica::class)->salveaza(
            $setariCondica['modAplicareRemote'],
            $setariCondica['numarLuniAnterioareEditabile'],
            $setariCondica['permiteExportCondicaIncompleta'],
            [(object) [
                'angajatId' => $angajat->getId(),
                'remoteActiv' => false,
                'dataInceputRemote' => null,
                'dataSfarsitRemote' => null,
                'cicActiv' => false,
                'dataInceputCic' => null,
                'dataSfarsitCic' => null,
            ]]
        );
        $configuratieDebifata = $this->managerEntitati->getEntityById(
            'ConfiguratieCondicaAngajat',
            $configuratie->getId()
        );

        self::assertNotNull($configuratieDebifata);
        self::assertFalse((bool) $configuratieDebifata->get('remoteActiv'));
        self::assertFalse((bool) $configuratieDebifata->get('cicActiv'));
        self::assertNull($configuratieDebifata->get('dataInceputCic'));
        self::assertNull($configuratieDebifata->get('dataSfarsitCic'));
    }

    public function testMarcareaManagerialaPastreazaAngajatulCandAsignareaGlobalaEsteNo(): void
    {
        $calendar = $this->creeazaCalendarDeTest();
        $angajat = $this->creeazaUtilizator(
            'test-condica-angajat-explicit',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no']
        );
        $manager = $this->creeazaUtilizator(
            'test-condica-manager-explicit',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'no'],
            true,
            'no'
        );
        $aclManager = $this->aplicatie->getContainer()->getByClass(AclManager::class);
        $aclManagerial = $aclManager->createUserAcl($manager);
        $marcator = $this->fabricaInjectabila->createWithBinding(
            MarcatorPrezentaCondica::class,
            BindingContainerBuilder::create()
                ->bindInstance(User::class, $manager)
                ->bindInstance(Acl::class, $aclManagerial)
                ->build()
        );
        $data = $this->obtineDataValida();

        $rezultat = $marcator->marcheaza($angajat->getId(), $data, 'P');
        $prezenta = $this->managerEntitati->getEntityById(
            'Prezenta',
            $rezultat['idPrezenta']
        );

        self::assertNotNull($prezenta);
        self::assertTrue($rezultat['aFostCreata']);
        self::assertSame($angajat->getId(), $prezenta->get('assignedUserId'));
        self::assertNotSame($manager->getId(), $prezenta->get('assignedUserId'));

        $actualizare = $marcator->marcheaza($angajat->getId(), $data, 'D');
        $prezentaActualizata = $this->managerEntitati->getEntityById(
            'Prezenta',
            $actualizare['idPrezenta']
        );

        self::assertNotNull($prezentaActualizata);
        self::assertFalse($actualizare['aFostCreata']);
        self::assertSame($angajat->getId(), $prezentaActualizata->get('assignedUserId'));
        self::assertSame('D', $prezentaActualizata->get('codPrezenta'));
    }

    public function testReminderulFolosesteNotificareaNativaSiNuSeDuplica(): void
    {
        $calendar = $this->creeazaCalendarDeTest();
        $angajat = $this->creeazaUtilizator(
            'test-reminder-prezenta-angajat',
            $calendar->getId(),
            ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no']
        );
        $condica = $this->createStub(CondicaPrezentaLunara::class);
        $condica->method('obtineDinDateCurente')->willReturn([
            'angajati' => [['idAngajat' => $angajat->getId()]],
            'zile' => [[
                'data' => (new DateTimeImmutable('yesterday'))->format('Y-m-d'),
                'celule' => [[
                    'idAngajat' => $angajat->getId(),
                    'esteLipsa' => true,
                ]],
            ]],
        ]);
        $luna = (new DateTimeImmutable('today'))->format('Y-m');
        $ciclu = $this->createStub(CicluLunarCondica::class);
        $ciclu->method('esteInFereastraPersonalaEditabila')->willReturn(true);
        $ciclu->method('obtineLuniDeschiseDinFereastraPersonala')->willReturn([$luna]);
        $container = $this->aplicatie->getContainer();
        $trimitator = new TrimitatorReminderePrezenta(
            $this->managerEntitati,
            $condica,
            $ciclu,
            $container->getByClass(AclManager::class),
            $this->fabricaInjectabila->create(LanguageFactory::class),
            $container->getByClass(Config::class),
        );
        $primaTrimitere = $trimitator->trimite($luna);
        $aDouaTrimitere = $trimitator->trimite($luna);
        $notificari = $this->managerEntitati
            ->getRDBRepositoryByClass(Notification::class)
            ->where([
                Notification::ATTR_USER_ID => $angajat->getId(),
                Notification::FIELD_TYPE => Notification::TYPE_MESSAGE,
            ])
            ->find();

        self::assertSame(1, $primaTrimitere['notificariCreate']);
        self::assertSame(1, $aDouaTrimitere['notificariActualizate']);
        self::assertCount(1, $notificari);
        self::assertInstanceOf(Notification::class, $notificari[0]);
        self::assertFalse($notificari[0]->isRead());
        self::assertNotSame('', $notificari[0]->getActionId());
        self::assertSame(1, $notificari[0]->getData()?->luniLipsa->{$luna});
        self::assertSame(1, $notificari[0]->getData()?->numarZileLipsa);
    }

    private function creeazaCalendarDeTest(): WorkingTimeCalendar
    {
        $calendar = $this->managerEntitati->getNewEntity(WorkingTimeCalendar::ENTITY_TYPE);
        $calendar->setMultiple([
            'name' => 'Calendar test prezenta',
            'timeZone' => 'UTC',
            'timeRanges' => [['09:00', '17:00']],
            'weekday0' => true,
            'weekday1' => true,
            'weekday2' => true,
            'weekday3' => true,
            'weekday4' => true,
            'weekday5' => true,
            'weekday6' => true,
        ]);
        $this->managerEntitati->saveEntity($calendar);

        self::assertInstanceOf(WorkingTimeCalendar::class, $calendar);

        return $calendar;
    }

    /** @param array<string, string> $drepturi */
    private function creeazaUtilizator(
        string $numeUtilizator,
        string $idCalendar,
        array $drepturi,
        bool $poateGestionaAngajati = false,
        string $permisiuneAsignare = 'all',
    ): User
    {
        $dateRol = ['Prezenta' => (object) $drepturi];

        if ($poateGestionaAngajati) {
            $dateRol['GestionareAngajati'] = true;
            $dateRol['CondicaPrezenta'] = true;
        }

        $rol = $this->managerEntitati->getNewEntity(Role::ENTITY_TYPE);
        $rol->setMultiple([
            'name' => 'Rol ' . $numeUtilizator,
            'assignmentPermission' => $permisiuneAsignare,
            'data' => (object) $dateRol,
        ]);
        $this->managerEntitati->saveEntity($rol);

        $utilizator = $this->managerEntitati->getNewEntity(User::ENTITY_TYPE);
        $utilizator->setMultiple([
            'userName' => $numeUtilizator,
            'lastName' => $numeUtilizator,
            'type' => User::TYPE_REGULAR,
            'isActive' => true,
            'workingTimeCalendarId' => $idCalendar,
            'dataAngajare' => '2000-01-01',
        ]);
        $this->managerEntitati->saveEntity($utilizator);
        $this->managerEntitati->getRelation($utilizator, User::LINK_ROLES)->relate($rol);

        $utilizatorSalvat = $this->managerEntitati
            ->getRDBRepository(User::ENTITY_TYPE)
            ->getById($utilizator->getId());

        self::assertInstanceOf(User::class, $utilizatorSalvat);

        return $utilizatorSalvat;
    }

    private function obtineDataValida(): string
    {
        $data = new DateTimeImmutable('yesterday');

        for ($incercare = 0; $incercare < 31; $incercare++) {
            $dataText = $data->format('Y-m-d');
            $esteSarbatoare = $this->managerEntitati
                ->getRDBRepository('ZileLibere')
                ->where(['dateStart' => $dataText, 'countryCode' => 'RO'])
                ->findOne() !== null;

            if (!$esteSarbatoare) {
                return $dataText;
            }

            $data = $data->modify('-1 day');
        }

        self::fail('Nu a fost găsită o dată de test fără sărbătoare legală.');
    }

    private function obtineDataCicValida(): string
    {
        $data = new DateTimeImmutable('today');

        for ($incercare = 0; $incercare < 31; $incercare++) {
            $dataText = $data->format('Y-m-d');
            $esteZiDinSaptamana = (int) $data->format('N') <= 5;
            $esteSarbatoare = $this->managerEntitati
                ->getRDBRepository('ZileLibere')
                ->where(['dateStart' => $dataText, 'countryCode' => 'RO'])
                ->findOne() !== null;

            if ($esteZiDinSaptamana && !$esteSarbatoare) {
                return $dataText;
            }

            $data = $data->modify('+1 day');
        }

        self::fail('Nu a fost găsită o dată CIC lucrătoare în intervalul de test.');
    }

    private function creeazaSarbatoareDeTest(string $luna, string $dataExclusa): string
    {
        $data = new DateTimeImmutable($luna . '-01');

        while ((int) $data->format('N') >= 6 || $data->format('Y-m-d') === $dataExclusa) {
            $data = $data->modify('+1 day');
        }

        $sarbatoare = $this->managerEntitati->getNewEntity('ZileLibere');
        $sarbatoare->setMultiple([
            'name' => 'Sarbatoare test prezenta',
            'dateStart' => $data->format('Y-m-d'),
            'countryCode' => 'RO',
            'source' => 'manual',
            'managed' => false,
            'holidayTypes' => ['Public'],
            'nationalHoliday' => true,
        ]);
        $this->managerEntitati->saveEntity($sarbatoare);

        return $data->format('Y-m-d');
    }

    private function asteaptaInterzis(callable $operatie, bool $doarInterzis = true): void
    {
        try {
            $operatie();
            self::fail('Operația trebuia să fie respinsă.');
        } catch (Forbidden $exceptie) {
            self::assertNotSame('', $exceptie->getMessage());
        } catch (\Espo\Core\Exceptions\Conflict $exceptie) {
            if ($doarInterzis) {
                throw $exceptie;
            }

            self::assertSame('prezentaDuplicata', $exceptie->getMessage());
        }
    }
}
