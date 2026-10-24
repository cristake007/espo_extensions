<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Metadate;

use PHPUnit\Framework\TestCase;

class MetadateConcediiTest extends TestCase
{
    private const string CALE = __DIR__ . '/../../../../../../src/files/custom/Espo/Modules/ManagementAngajati';

    public function testCerereaFolosesteAclNativSiPaginaManagerialaMostenesteGestionarea(): void
    {
        $scopeCerere = $this->json('Resources/metadata/scopes/CerereConcediu.json');
        $scopeManager = $this->json('Resources/metadata/scopes/AdministrareConcedii.json');
        $acl = $this->json('Resources/metadata/app/acl.json');

        self::assertTrue($scopeCerere['entity']);
        self::assertTrue($scopeCerere['acl']);
        self::assertTrue($scopeCerere['tab']);
        self::assertTrue($scopeCerere['calendar']);
        self::assertSame('Event', $scopeCerere['type']);
        self::assertFalse($scopeCerere['customizable']);
        self::assertFalse($scopeManager['entity']);
        self::assertSame('boolean', $scopeManager['acl']);
        self::assertSame(
            'boolean:GestionareAngajati',
            $acl['mandatory']['scopeLevel']['AdministrareConcedii']
        );
        self::assertArrayNotHasKey('adminMandatory', $acl);

        $caleClient = dirname(self::CALE, 4) . '/client/custom/modules/management-angajati';
        $controlorCereri = file_get_contents($caleClient . '/src/controllers/concedii.js');
        $controlorAdministrare = file_get_contents(
            $caleClient . '/src/controllers/administrare-concedii.js'
        );
        self::assertNotFalse($controlorCereri);
        self::assertNotFalse($controlorAdministrare);
        self::assertStringContainsString('this.getUser().isAdmin()', $controlorCereri);
        self::assertStringContainsString('this.getUser().isAdmin()', $controlorAdministrare);
    }

    public function testCerereaAprobataEsteIntegrataDirectInCalendar(): void
    {
        $cerere = $this->json('Resources/metadata/entityDefs/CerereConcediu.json');
        self::assertSame('date', $cerere['fields']['dateStartDate']['type']);
        self::assertTrue($cerere['fields']['dateStartDate']['notStorable']);
        self::assertSame(
            'dataInceput',
            $cerere['fields']['dateStartDate']['select']['select']
        );
        self::assertSame('date', $cerere['fields']['dateEndDate']['type']);
        self::assertTrue($cerere['fields']['dateEndDate']['notStorable']);
        self::assertSame(
            'dataSfarsit',
            $cerere['fields']['dateEndDate']['select']['select']
        );

        $client = $this->json('Resources/metadata/clientDefs/CerereConcediu.json');
        self::assertSame('assignedUserName', $client['nameAttribute']);

        $serviciu = file_get_contents(self::CALE . '/Services/CerereConcediu.php');
        $controlor = file_get_contents(self::CALE . '/Controllers/CerereConcediu.php');
        $instalare = file_get_contents(dirname(self::CALE, 5) . '/scripts/AfterInstall.php');
        self::assertNotFalse($serviciu);
        self::assertNotFalse($controlor);
        self::assertNotFalse($instalare);
        self::assertStringContainsString('getCalenderQuery', $serviciu);
        self::assertStringContainsString(
            'getRepositoryByClass(Preferences::class)',
            $serviciu
        );
        self::assertStringNotContainsString(
            'getRDBRepositoryByClass(Preferences::class)',
            $serviciu
        );
        self::assertStringContainsString("'assignedUserId' => \$userId", $serviciu);
        self::assertStringContainsString("'stare' => 'Aprobata'", $serviciu);
        self::assertStringContainsString("['assignedUserName', 'name']", $serviciu);
        self::assertStringNotContainsString('AbsentaConcediu', $serviciu);
        self::assertStringContainsString('CereriConcediu::class)->creeaza', $controlor);
        self::assertStringContainsString("'dateStartDate' => null", $controlor);
        self::assertStringContainsString("'dateEndDate' => null", $controlor);
        self::assertStringContainsString('...$listaEntitatiCalendar', $instalare);
        self::assertStringContainsString('self::SCOPE_CONCEDII,', $instalare);
    }

    public function testSelectiaNativaDinCalendarPrevizualizeazaZileleLucratoare(): void
    {
        $cerere = $this->json('Resources/metadata/entityDefs/CerereConcediu.json');
        $layout = $this->json('Resources/layouts/CerereConcediu/edit.json');
        $layoutModal = $this->json('Resources/layouts/CerereConcediu/detailSmall.json');
        $rute = $this->json('Resources/routes.json');
        $indexRute = [];

        foreach ($rute as $ruta) {
            $indexRute[$ruta['method'] . ' ' . $ruta['route']] = $ruta['actionClassName'];
        }

        self::assertSame(
            'management-angajati:views/cerere-concediu/fields/numar-zile',
            $cerere['fields']['numarZile']['view']
        );
        self::assertSame('dateStartDate', $layout[0]['rows'][0][0]['name']);
        self::assertSame('dateEndDate', $layout[0]['rows'][0][1]['name']);
        self::assertSame('numarZile', $layout[0]['rows'][1][0]['name']);
        self::assertSame('dateStartDate', $layoutModal[0]['rows'][0][0]['name']);
        self::assertSame('dateEndDate', $layoutModal[0]['rows'][0][1]['name']);
        self::assertSame('numarZile', $layoutModal[0]['rows'][1][0]['name']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\PrevizualizeazaPerioadaConcediu',
            $indexRute['post /Concedii/previzualizare-perioada']
        );

        $caleClient = dirname(self::CALE, 4) . '/client/custom/modules/management-angajati';
        $camp = file_get_contents(
            $caleClient . '/src/views/cerere-concediu/fields/numar-zile.js'
        );
        $serviciu = file_get_contents(
            self::CALE . '/Instrumente/Concedii/CereriConcediu.php'
        );
        self::assertNotFalse($camp);
        self::assertNotFalse($serviciu);
        self::assertStringContainsString("define(['views/fields/int']", $camp);
        self::assertStringContainsString('change:dateStartDate change:dateEndDate', $camp);
        self::assertStringContainsString('if (!this.model.isNew()) return;', $camp);
        self::assertStringContainsString('Concedii/previzualizare-perioada', $camp);
        self::assertStringContainsString(
            'public function previzualizeazaPerioada(array $date): array',
            $serviciu
        );
        self::assertStringContainsString(
            '$this->calculator->calculeaza($angajat, $inceput, $sfarsit)',
            $serviciu
        );
        self::assertStringContainsString("'name' => \$angajat->getName()", $serviciu);
        self::assertStringNotContainsString("'%s · %s – %s'", $serviciu);

        $pozitiePrevizualizare = strpos(
            $serviciu,
            'public function previzualizeazaPerioada(array $date): array'
        );
        $pozitieLista = strpos($serviciu, 'public function listaManageriala(): array');
        self::assertIsInt($pozitiePrevizualizare);
        self::assertIsInt($pozitieLista);
        $metodaPrevizualizare = substr(
            $serviciu,
            $pozitiePrevizualizare,
            $pozitieLista - $pozitiePrevizualizare
        );
        self::assertStringNotContainsString('concediuInTrecut', $metodaPrevizualizare);
    }

    public function testSoldurileSuntSeparatePeTranseSiProtejatePrinIndecsi(): void
    {
        $drept = $this->json('Resources/metadata/entityDefs/DreptConcediu.json');
        $alocare = $this->json('Resources/metadata/entityDefs/AlocareConcediu.json');
        $registru = $this->json('Resources/metadata/entityDefs/RegistruConcediu.json');

        self::assertSame(
            ['Anual', 'ReportInitial', 'Corectie'],
            $drept['fields']['tip']['options']
        );
        self::assertSame(
            ['profilId', 'anOrigine', 'tip', 'deleteId'],
            $drept['indexes']['profilAnTip']['columns']
        );
        self::assertSame(['Rezervata', 'Consumata', 'Eliberata'], $alocare['fields']['stare']['options']);
        self::assertContains('Restaurare', $registru['fields']['tip']['options']);
        self::assertContains('CorectieProgramAcordare', $registru['fields']['tip']['options']);
        self::assertTrue($drept['fields']['dataAcordare']['audited']);
        self::assertSame(
            ['cerereId', 'dreptId', 'anConsum', 'deleteId'],
            $alocare['indexes']['cerereDrept']['columns']
        );
        self::assertTrue($registru['noDeletedAttribute']);
        self::assertSame('unique', $registru['indexes']['cheieOperatieUnica']['type']);
    }

    public function testCerereaArePerioadaImutabilaSiAuditata(): void
    {
        $cerere = $this->json('Resources/metadata/entityDefs/CerereConcediu.json');

        foreach (['dataInceput', 'dataSfarsit'] as $camp) {
            self::assertSame('date', $cerere['fields'][$camp]['type']);
            self::assertTrue($cerere['fields'][$camp]['required']);
            self::assertTrue($cerere['fields'][$camp]['readOnlyAfterCreate']);
            self::assertTrue($cerere['fields'][$camp]['audited']);
        }

        self::assertTrue($cerere['fields']['stare']['readOnly']);
        self::assertTrue($cerere['fields']['stare']['audited']);
        self::assertTrue($cerere['fields']['assignedUser']['readOnlyAfterCreate']);
        self::assertContains('AnulareSolicitata', $cerere['fields']['stare']['options']);

        foreach (['anulareSolicitataLa', 'motivAnulare', 'motivDecizieAnulare', 'anulataLa', 'anulataDe'] as $camp) {
            self::assertTrue($cerere['fields'][$camp]['readOnly']);
            self::assertTrue($cerere['fields'][$camp]['audited']);
        }

        $inregistrare = $this->json('Resources/metadata/recordDefs/CerereConcediu.json');
        self::assertCount(1, $inregistrare['beforeCreateHookClassNameList']);
        self::assertCount(1, $inregistrare['beforeUpdateHookClassNameList']);
        self::assertCount(1, $inregistrare['beforeDeleteHookClassNameList']);
        self::assertFalse($inregistrare['massActions']['update']['allowed']);
        self::assertFalse($inregistrare['massActions']['delete']['allowed']);
    }

    public function testTraducerileNoilorPaginiAuAceeasiStructura(): void
    {
        foreach ([
            'CerereConcediu',
            'ProfilConcediu',
            'DreptConcediu',
            'RegistruConcediu',
            'AdministrareConcedii',
            'ScheduledJob',
        ] as $scope) {
            $ro = $this->json("Resources/i18n/ro_RO/$scope.json");
            $en = $this->json("Resources/i18n/en_US/$scope.json");
            self::assertSame($this->chei($ro), $this->chei($en), $scope);
            self::assertNotSame($ro, $en, $scope);
        }
    }

    public function testCorectiaSiResetareaAuRuteManagerialeExplicite(): void
    {
        $rute = $this->json('Resources/routes.json');
        $index = [];

        foreach ($rute as $ruta) {
            $index[$ruta['method'] . ' ' . $ruta['route']] = $ruta['actionClassName'];
        }

        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\CorecteazaSoldConcediu',
            $index['post /ManagementAngajati/profiluri-concediu/:idAngajat/corectie']
        );
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\CorecteazaProgramAcordareConcediu',
            $index['post /ManagementAngajati/profiluri-concediu/:idAngajat/corectie-program-acordare']
        );
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\PrevizualizeazaCorectieProgramAcordareConcediu',
            $index['post /ManagementAngajati/profiluri-concediu/:idAngajat/previzualizare-corectie-program-acordare']
        );
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\ObtinePrevizualizareResetConcedii',
            $index['get /ManagementAngajati/reset-date-concedii']
        );
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\ReseteazaDateTestConcedii',
            $index['post /ManagementAngajati/reset-date-concedii']
        );
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\SolicitaAnulareCerereConcediu',
            $index['post /Concedii/cereri/:id/anulare']
        );
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\DecideAnulareCerereConcediu',
            $index['post /AdministrareConcedii/cereri/:id/decizie-anulare']
        );

        $caleClient = dirname(self::CALE, 4) . '/client/custom/modules/management-angajati';
        $rand = file_get_contents(
            $caleClient . '/src/views/gestionare-angajati/rand-profil-concediu.js'
        );
        $pagina = file_get_contents(
            $caleClient . '/src/views/gestionare-angajati/concedii.js'
        );
        self::assertNotFalse($rand);
        self::assertNotFalse($pagina);
        self::assertStringContainsString('/corectie`', $rand);
        self::assertStringContainsString('/corectie-program-acordare`', $rand);
        self::assertStringContainsString('/previzualizare-corectie-program-acordare', $rand);
        self::assertStringContainsString('corecteazaProgramAcordare', $rand);
        self::assertStringContainsString('#AdministrareConcedii/view/{{id}}', $rand);
        self::assertStringNotContainsString('#CerereConcediu/view/{{id}}', $rand);
        self::assertStringContainsString(
            "decizieLuata = true;\n                            rezolva(true);\n                            dialog.close();",
            $rand
        );
        self::assertStringContainsString(
            "if (!decizieLuata) rezolva(false);",
            $rand
        );
        self::assertStringContainsString('RESET DATE CONCEDII', $pagina);
        self::assertStringContainsString("'views/fields/checklist'", $pagina);
        self::assertStringContainsString("'views/fields/bool'", $pagina);
        self::assertStringNotContainsString('type="checkbox"', file_get_contents(
            $caleClient . '/res/templates/gestionare-angajati/concedii.tpl'
        ));

        $controlorAdministrare = file_get_contents(
            $caleClient . '/src/controllers/administrare-concedii.js'
        );
        $vizualizareAdministrare = file_get_contents(
            $caleClient . '/src/views/administrare-concedii/index.js'
        );
        self::assertNotFalse($controlorAdministrare);
        self::assertNotFalse($vizualizareAdministrare);
        self::assertStringContainsString('actionView(optiuni)', $controlorAdministrare);
        self::assertStringContainsString('idCerereSelectata', $vizualizareAdministrare);
    }

    public function testErorileCereriiExplicaPerioadaSiSoldul(): void
    {
        $traduceri = $this->json('Resources/i18n/ro_RO/CerereConcediu.json');
        $mesaje = $traduceri['messages'];

        self::assertStringContainsString('{dataMinima}', $mesaje['concediuInTrecut']);
        self::assertStringContainsString('{an}', $mesaje['soldConcediuInsuficientDetaliat']);
        self::assertStringContainsString('{necesare}', $mesaje['soldConcediuInsuficientDetaliat']);
        self::assertStringContainsString('{disponibile}', $mesaje['soldConcediuInsuficientDetaliat']);

        $cereri = file_get_contents(
            self::CALE . '/Instrumente/Concedii/CereriConcediu.php'
        );
        $solduri = file_get_contents(
            self::CALE . '/Instrumente/Concedii/SolduriConcediu.php'
        );
        self::assertNotFalse($cereri);
        self::assertNotFalse($solduri);
        self::assertStringContainsString("\$this->eroare('concediuInTrecut'", $cereri);
        self::assertStringContainsString("'dataMinima' =>", $cereri);
        self::assertStringContainsString('Body::create()->withMessage($this->traduceMesaj(', $cereri);
        self::assertStringContainsString("'soldConcediuInsuficientDetaliat'", $solduri);
        self::assertStringContainsString("'necesare' =>", $solduri);
        self::assertStringContainsString("'disponibile' =>", $solduri);
        self::assertStringContainsString('Body::create()->withMessage($this->traduceMesaj(', $solduri);
    }

    public function testSpatiulPersonalExplicaLipsaProfiluluiFaraEroareTehnica(): void
    {
        $caleClient = dirname(self::CALE, 4) . '/client/custom/modules/management-angajati';
        $vizualizare = file_get_contents(
            $caleClient . '/src/views/concedii/spatiu-personal.js'
        );
        $sablon = file_get_contents(
            $caleClient . '/res/templates/concedii/spatiu-personal.tpl'
        );
        $serviciu = file_get_contents(
            self::CALE . '/Instrumente/Concedii/CereriConcediu.php'
        );

        self::assertNotFalse($vizualizare);
        self::assertNotFalse($sablon);
        self::assertNotFalse($serviciu);
        self::assertStringContainsString("'disponibil' => false", $serviciu);
        self::assertStringContainsString("'motivIndisponibil' => 'profilConcediuNeinitializat'", $serviciu);
        self::assertStringContainsString(
            "'motivIndisponibil' => 'administratorFaraConcediuPersonal'",
            $serviciu
        );
        self::assertStringContainsString('{{#unless disponibil}}', $sablon);
        self::assertStringContainsString('profilConcediuNeinitializatExplicatie', $vizualizare);
        self::assertStringContainsString('administratorFaraConcediuPersonal', $vizualizare);
        self::assertStringContainsString("'#GestionareAngajati'", $vizualizare);
    }

    public function testInitializareaPastreazaConfiguratiaSoldulRealSiReportulSeparat(): void
    {
        $caleClient = dirname(self::CALE, 4) .
            '/client/custom/modules/management-angajati';
        $rand = file_get_contents(
            $caleClient . '/src/views/gestionare-angajati/rand-profil-concediu.js'
        );
        $sablonRand = file_get_contents(
            $caleClient . '/res/templates/gestionare-angajati/rand-profil-concediu.tpl'
        );

        self::assertNotFalse($rand);
        self::assertNotFalse($sablonRand);
        $profil = $this->json('Resources/metadata/entityDefs/ProfilConcediu.json');
        foreach ([
            'zileDisponibileAnCurent',
            'zileReportateAnAnterior',
            'diferentaCorectie',
        ] as $campInterfata) {
            self::assertSame('int', $profil['fields'][$campInterfata]['type']);
            self::assertTrue($profil['fields'][$campInterfata]['notStorable']);
        }
        self::assertStringContainsString(
            'corp.zileDisponibileAnCurent = Number',
            $rand
        );
        self::assertStringContainsString(
            'data-name="zileDisponibileAnCurent">{{{zileDisponibileAnCurent}}}',
            $sablonRand
        );
        self::assertStringContainsString(
            'data-name="zileReportateAnAnterior">{{{zileReportateAnAnterior}}}',
            $sablonRand
        );
        self::assertStringContainsString(
            'data-name="diferentaCorectie">{{{diferentaCorectie}}}',
            $sablonRand
        );
        self::assertStringContainsString("'views/fields/int'", $rand);
        self::assertStringContainsString(
            "this.getView('zileDisponibileAnCurent').fetchToModel()",
            $rand
        );
        self::assertStringNotContainsString('type="number"', $sablonRand);

        $profiluri = file_get_contents(
            self::CALE . '/Instrumente/Concedii/ProfiluriConcediu.php'
        );
        self::assertNotFalse($profiluri);
        self::assertStringContainsString(
            "\$zileDisponibileAnCurent = \$this->numar(\$date['zileDisponibileAnCurent'] ?? null)",
            $profiluri
        );
        self::assertStringContainsString("'Anual',", $profiluri);
        self::assertStringNotContainsString(
            '$zileAnuale - $zileDisponibileAnCurent',
            $profiluri
        );
        self::assertStringContainsString('$zileDisponibileAnCurent,', $profiluri);
        self::assertStringContainsString(
            "'Inițializare sold disponibil din anul curent'",
            $profiluri
        );
        self::assertStringContainsString(
            "\$dataAcordare <= \$this->dataOra->getToday()->toString()",
            $profiluri
        );
        self::assertStringContainsString(
            "\$this->eroare('dataUrmatoareiAcordariTrebuieViitoare')",
            $profiluri
        );
        self::assertStringNotContainsString('SoldInitial', $profiluri);

        $solduri = file_get_contents(
            self::CALE . '/Instrumente/Concedii/SolduriConcediu.php'
        );
        self::assertNotFalse($solduri);
        self::assertStringContainsString("'anConsum' => \$an", $solduri);
        self::assertStringContainsString("'stare' => 'Consumata'", $solduri);
        self::assertStringContainsString("(int) \$drept->get('zileConsumateInitial')", $solduri);
        self::assertLessThan(
            strpos($rand, "Espo.Ui.success(this.translate('profilSalvat'"),
            strpos($rand, 'Espo.Ui.notify(false);')
        );

        foreach ([
            '/src/views/gestionare-angajati/concedii.js' => 'setariSalvate',
            '/src/views/concedii/spatiu-personal.js' => 'cerereTrimisa',
            '/src/views/administrare-concedii/index.js' => 'decizieSalvata',
        ] as $fisier => $mesaj) {
            $continut = file_get_contents($caleClient . $fisier);
            self::assertNotFalse($continut);
            self::assertLessThan(
                strpos($continut, "Espo.Ui.success(this.translate('$mesaj'"),
                strpos($continut, 'Espo.Ui.notify(false);')
            );
        }
    }

    public function testAcordareaAnualaFolosesteJobulNativEspo(): void
    {
        $joburi = $this->json('Resources/metadata/app/scheduledJobs.json');
        $definitie = $joburi['AcordaZileConcediuAnuale'];

        self::assertSame('15 0 * * *', $definitie['scheduling']);
        self::assertTrue($definitie['isDefault']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Jobs\\AcordaZileConcediuAnuale',
            $definitie['jobClassName']
        );

        $continut = file_get_contents(
            self::CALE . '/Instrumente/Concedii/AcordariAnualeConcediu.php'
        );
        self::assertNotFalse($continut);
        self::assertStringContainsString('pregatestePentruAni', $continut);
        self::assertStringContainsString(
            'esteAcordareaAnualaAutomataActiva()',
            $continut
        );
        self::assertStringContainsString("'tip' => 'Anual'", $continut);
        self::assertStringContainsString("'zileConsumateInitial' => 0", $continut);

        $setari = file_get_contents(
            self::CALE . '/Instrumente/Concedii/SetariConcedii.php'
        );
        self::assertNotFalse($setari);
        self::assertStringContainsString(
            'managementAngajatiConcediiAcordareAnualaAutomata',
            $setari
        );
        self::assertStringContainsString(
            "self::CHEIE_ACORDARE_ANUALA_AUTOMATA,\n            true",
            $setari
        );
    }

    public function testConcediulAprobatDevineSursaAutomataInCondica(): void
    {
        $calendar = file_get_contents(
            self::CALE . '/Instrumente/Prezenta/CalendarPrezentaPersonala.php'
        );
        $cereri = file_get_contents(
            self::CALE . '/Instrumente/Concedii/CereriConcediu.php'
        );
        $validator = file_get_contents(
            self::CALE . '/Instrumente/Prezenta/ValidatorPrezenta.php'
        );
        self::assertNotFalse($calendar);
        self::assertNotFalse($cereri);
        self::assertNotFalse($validator);
        self::assertStringContainsString("\$esteConcediu ? 'CO'", $calendar);
        self::assertStringContainsString(
            "verificaPrezenteLaCreare(\$angajat->getId(), \$calcul['date'])",
            $cereri
        );
        self::assertStringContainsString('verificaPrezenteExistente($cerere)', $cereri);
        self::assertStringContainsString('valideazaAbsentaConcediu', $validator);
    }

    public function testPerioadaCereriiFolosestePrimaSiUltimaZiLucratoareCalculata(): void
    {
        $cereri = file_get_contents(
            self::CALE . '/Instrumente/Concedii/CereriConcediu.php'
        );
        self::assertNotFalse($cereri);
        self::assertStringContainsString("\$inceputEfectiv = \$calcul['date'][0]", $cereri);
        self::assertStringContainsString(
            "\$sfarsitEfectiv = \$calcul['date'][count(\$calcul['date']) - 1]",
            $cereri
        );
        self::assertStringContainsString("'dataInceput' => \$inceputEfectiv", $cereri);
        self::assertStringContainsString("'dataSfarsit' => \$sfarsitEfectiv", $cereri);
    }

    public function testNotificarileFolosescRuteleScopesNative(): void
    {
        $notificator = file_get_contents(
            self::CALE . '/Instrumente/Concedii/NotificatorCereriConcediu.php'
        );
        self::assertNotFalse($notificator);
        self::assertStringContainsString("'#CerereConcediu'", $notificator);
        self::assertStringContainsString("'#AdministrareConcedii'", $notificator);
        self::assertStringNotContainsString("'#Concedii'", $notificator);
    }

    public function testDocumenteleFolosescInstantaneuAttachmentSiRutaExplicita(): void
    {
        $cerere = $this->json('Resources/metadata/entityDefs/CerereConcediu.json');
        self::assertSame('jsonObject', $cerere['fields']['instantaneuDocumente']['type']);
        self::assertSame('attachmentMultiple', $cerere['fields']['documenteConcediu']['type']);

        $rute = $this->json('Resources/routes.json');
        $index = [];
        foreach ($rute as $ruta) {
            $index[$ruta['method'] . ' ' . $ruta['route']] = $ruta['actionClassName'];
        }
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\GenereazaDocumenteConcediu',
            $index['post /Concedii/cereri/:id/documente']
        );

        $generator = file_get_contents(
            self::CALE . '/Instrumente/Concedii/GeneratorDocumentConcediu.php'
        );
        self::assertNotFalse($generator);
        self::assertStringContainsString('new TemplateProcessor($caleSablon)', $generator);
        self::assertStringContainsString('$document->saveAs($cale)', $generator);
        self::assertStringContainsString("['dom', 'gd', 'xml', 'zip']", $generator);

        $documente = file_get_contents(
            self::CALE . '/Instrumente/Concedii/DocumenteConcediu.php'
        );
        self::assertNotFalse($documente);
        self::assertStringContainsString('->forUpdate()', $documente);
        self::assertStringContainsString("'contents' => \$continut", $documente);
        self::assertStringContainsString('stergePentruCerere', $documente);
    }

    /** @return array<string, mixed> */
    private function json(string $cale): array
    {
        $continut = file_get_contents(self::CALE . '/' . $cale);
        self::assertNotFalse($continut);
        $date = json_decode($continut, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($date);
        return $date;
    }

    /** @param array<string, mixed> $date @return list<string> */
    private function chei(array $date, string $prefix = ''): array
    {
        $rezultat = [];
        foreach ($date as $cheie => $valoare) {
            $cale = $prefix === '' ? $cheie : "$prefix.$cheie";
            $rezultat[] = $cale;
            if (is_array($valoare)) {
                array_push($rezultat, ...$this->chei($valoare, $cale));
            }
        }
        return $rezultat;
    }
}
