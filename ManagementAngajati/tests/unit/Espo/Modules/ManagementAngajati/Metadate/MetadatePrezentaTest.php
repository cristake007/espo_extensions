<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Metadate;

use Espo\Core\Controllers\Record;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Table;
use Espo\Core\Acl\Table\ScopeDataResolver;
use Espo\Modules\ManagementAngajati\Controllers\Prezenta;
use PHPUnit\Framework\TestCase;

class MetadatePrezentaTest extends TestCase
{
    private const string CALE_MODUL = __DIR__ . '/../../../../../../src/files/custom/Espo/Modules/ManagementAngajati';
    private const string CALE_CLIENT = __DIR__ . '/../../../../../../src/files/client/custom/modules/management-angajati';

    public function testUnicitateaInBazaDeDateEsteSiguraLaConcurentaSiPermiteRecreareaDupaStergere(): void
    {
        $metadate = $this->citesteJson('Resources/metadata/entityDefs/Prezenta.json');

        self::assertTrue($metadate['deleteId']);
        self::assertSame('unique', $metadate['indexes']['angajatDataPrezenta']['type']);
        self::assertSame(
            ['assignedUserId', 'dataPrezenta', 'deleteId'],
            $metadate['indexes']['angajatDataPrezenta']['columns']
        );
    }

    public function testCheiaInregistrariiNuPoateFiSchimbataDupaCreare(): void
    {
        $metadate = $this->citesteJson('Resources/metadata/entityDefs/Prezenta.json');

        self::assertTrue($metadate['fields']['dataPrezenta']['readOnlyAfterCreate']);
        self::assertTrue($metadate['fields']['assignedUser']['readOnlyAfterCreate']);
    }

    public function testTipurileSiValorilePrezenteiSuntValidateNativDinMetadata(): void
    {
        $metadate = $this->citesteJson('Resources/metadata/entityDefs/Prezenta.json');

        self::assertSame('date', $metadate['fields']['dataPrezenta']['type']);
        self::assertTrue($metadate['fields']['dataPrezenta']['required']);
        self::assertSame('enum', $metadate['fields']['codPrezenta']['type']);
        self::assertTrue($metadate['fields']['codPrezenta']['required']);
        self::assertSame(['P', 'D', 'CM'], $metadate['fields']['codPrezenta']['options']);
    }

    public function testAclNativAcceptaNivelurileRolurilorFaraRestrictiePersonalizataPentruAdministrator(): void
    {
        $definitieScope = $this->citesteJson('Resources/metadata/scopes/Prezenta.json');

        self::assertTrue($definitieScope['acl']);
        self::assertFalse($definitieScope['aclPortal']);
        self::assertFalse($definitieScope['customizable']);
        self::assertArrayNotHasKey('aclActionList', $definitieScope);
        self::assertArrayNotHasKey('aclActionLevelListMap', $definitieScope);
        self::assertArrayNotHasKey('accessCheckerClassName', $definitieScope);
        self::assertFileDoesNotExist(self::CALE_MODUL . '/Resources/metadata/aclDefs/Prezenta.json');
    }

    public function testTraducerileRomaneSiEnglezeAuAceeasiStructuraDarTexteLocalizate(): void
    {
        $traduceriRomana = $this->citesteJson('Resources/i18n/ro_RO/Prezenta.json');
        $traduceriEngleza = $this->citesteJson('Resources/i18n/en_US/Prezenta.json');
        $globalRomana = $this->citesteJson('Resources/i18n/ro_RO/Global.json');
        $globalEngleza = $this->citesteJson('Resources/i18n/en_US/Global.json');

        foreach (
            [
                'Prezenta',
                'CondicaPrezenta',
                'ConfiguratieCondicaAngajat',
                'StareLunaCondica',
                'GestionareAngajati',
                'User',
                'Global',
            ]
            as $scope
        ) {
            $romana = $this->citesteJson("Resources/i18n/ro_RO/$scope.json");
            $engleza = $this->citesteJson("Resources/i18n/en_US/$scope.json");

            self::assertSame(
                $this->obtineCheiRecursive($romana),
                $this->obtineCheiRecursive($engleza),
                $scope
            );
            self::assertNotSame($romana, $engleza, $scope);
        }

        self::assertSame('Prezență', $globalRomana['navbarTabs']['prezenta']);
        self::assertSame('Attendance', $globalEngleza['navbarTabs']['prezenta']);
        self::assertSame('Management', $globalRomana['navbarTabs']['management']);
        self::assertSame('Management', $globalEngleza['navbarTabs']['management']);
        self::assertSame('Administrare', $globalRomana['scopeNamesPlural']['GestionareAngajati']);
        self::assertSame('Administration', $globalEngleza['scopeNamesPlural']['GestionareAngajati']);
        self::assertSame('Concediu medical', $traduceriRomana['options']['codPrezenta']['CM']);
        self::assertSame('Medical leave', $traduceriEngleza['options']['codPrezenta']['CM']);
        self::assertSame(
            [
                'name',
                'dataPrezenta',
                'codPrezenta',
                'esteCorectieManageriala',
                'motivCorectie',
                'corectataLa',
                'corectataDe',
                'createdAt',
                'modifiedAt',
                'createdBy',
                'modifiedBy',
                'assignedUser',
                'teams',
            ],
            array_keys($traduceriRomana['fields'])
        );
        self::assertSame(
            ['P', 'D', 'CM', 'CO', 'CIC'],
            array_keys($traduceriRomana['options']['codPrezenta'])
        );
        self::assertSame(
            [
                'dataPrezentaViitoare',
                'ziSarbatoareLegala',
                'ziNelucratoare',
                'prezentaDuplicata',
                'ziAcoperitaDeCic',
                'ziAcoperitaDeConcediu',
                'calendarLucruIndisponibil',
                'administratorFaraPrezentaPersonala',
                'angajatInvalid',
                'extensieZileSarbatoareIndisponibila',
                'dependentaZileSarbatoareIndisponibila',
                'lunaPrezentaInvalida',
                'lunaInainteDeAngajare',
                'prezentaDoarPentruAngajati',
                'prezentaCreata',
                'prezentaModificata',
                'lunaCondicaInchisa',
                'lunaPrezentaNeeditabila',
                'dataAnterioaraAngajarii',
                'descrierePrezentaPersonala',
                'descriereLuna',
                'faraZileExplicatie',
                'descrierePrezentaAstazi',
                'ajutorP',
                'ajutorD',
                'ajutorCM',
                'prezentaSuspendataCic',
                'cicContactManager',
            ],
            array_keys($traduceriRomana['messages'])
        );
        self::assertSame(
            ['Create Prezenta', 'prezentaPersonala', 'prezentaAngajatului', 'condicaPrezenta',
                'prezentaIndisponibilaAdministrator',
                'lunaCurenta', 'lunaAnterioara', 'lunaUrmatoare', 'ziua', 'stareZi',
                'ziViitoare', 'sarbatoareLegala', 'ziNelucratoare', 'cicAutomat',
                'concediuAprobatAutomat',
                'faraPermisiuneCreare',
                'faraPermisiuneModificare', 'prezentaInregistrata',
                'disponibilaPentruPrezenta', 'lunaInchisa', 'lunaDeschisa', 'lunaNeeditabila',
                'inainteDeAngajare', 'dataAngajareLipsa',
                'systemInformation', 'corectieManageriala', 'detaliiLuna',
                'zileInregistrate', 'zileAfisate', 'faraZile',
                'prezentaAstazi', 'stareCurenta', 'alegePrezenta', 'registruLunar',
                'zileCompletate', 'zileNecompletate', 'zileViitoare', 'nemarcat',
                'nicioActiuneNecesara', 'prezentaIndisponibilaCic', 'cicActiv',
                'perioadaCic', 'cicFaraPontare'],
            array_keys($traduceriRomana['labels'])
        );
        self::assertCount(12, $traduceriRomana['luni']);
        self::assertCount(7, $traduceriRomana['zileSaptamana']);
    }

    public function testPrezentaPersonalaFolosesteCrudNativIarCondicaAreComandaManagerialaDedicata(): void
    {
        $definitiiInregistrare = $this->citesteJson('Resources/metadata/recordDefs/Prezenta.json');
        $rute = $this->citesteJson('Resources/routes.json');
        $rute = $this->indexeazaRute($rute);

        self::assertSame(
            ['Espo\\Modules\\ManagementAngajati\\Classes\\RecordHooks\\Prezenta\\InainteDeCreare'],
            $definitiiInregistrare['beforeCreateHookClassNameList']
        );
        self::assertSame(
            ['Espo\\Modules\\ManagementAngajati\\Classes\\RecordHooks\\Prezenta\\InainteDeActualizare'],
            $definitiiInregistrare['beforeUpdateHookClassNameList']
        );
        self::assertSame(
            ['Espo\\Modules\\ManagementAngajati\\Classes\\RecordHooks\\Prezenta\\StabilesteNumele'],
            $definitiiInregistrare['earlyBeforeCreateHookClassNameList']
        );
        self::assertSame('get', $rute['get /Prezenta/prezenta-personala']['method']);
        self::assertSame('/Prezenta/prezenta-personala', $rute['get /Prezenta/prezenta-personala']['route']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\ObtineCalendarPrezentaPersonala',
            $rute['get /Prezenta/prezenta-personala']['actionClassName']
        );
        self::assertSame('/CondicaPrezenta/condica-lunara', $rute['get /CondicaPrezenta/condica-lunara']['route']);
        self::assertSame('get', $rute['get /CondicaPrezenta/condica-lunara']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\ObtineCondicaPrezentaLunara',
            $rute['get /CondicaPrezenta/condica-lunara']['actionClassName']
        );
        self::assertSame('/ManagementAngajati/setari-condica', $rute['get /ManagementAngajati/setari-condica']['route']);
        self::assertSame('get', $rute['get /ManagementAngajati/setari-condica']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\ObtineSetariCondica',
            $rute['get /ManagementAngajati/setari-condica']['actionClassName']
        );
        self::assertSame('/ManagementAngajati/setari-condica', $rute['put /ManagementAngajati/setari-condica']['route']);
        self::assertSame('put', $rute['put /ManagementAngajati/setari-condica']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\SalveazaSetariCondica',
            $rute['put /ManagementAngajati/setari-condica']['actionClassName']
        );
        self::assertSame('/CondicaPrezenta/inchide-luna', $rute['post /CondicaPrezenta/inchide-luna']['route']);
        self::assertSame('post', $rute['post /CondicaPrezenta/inchide-luna']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\InchideLunaCondica',
            $rute['post /CondicaPrezenta/inchide-luna']['actionClassName']
        );
        self::assertSame('/CondicaPrezenta/corecteaza-luna', $rute['post /CondicaPrezenta/corecteaza-luna']['route']);
        self::assertSame('post', $rute['post /CondicaPrezenta/corecteaza-luna']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\CorecteazaLunaCondica',
            $rute['post /CondicaPrezenta/corecteaza-luna']['actionClassName']
        );
        self::assertSame('/CondicaPrezenta/marcheaza-prezenta', $rute['post /CondicaPrezenta/marcheaza-prezenta']['route']);
        self::assertSame('post', $rute['post /CondicaPrezenta/marcheaza-prezenta']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\MarcheazaPrezentaCondica',
            $rute['post /CondicaPrezenta/marcheaza-prezenta']['actionClassName']
        );
        self::assertSame('/CondicaPrezenta/trimite-remindere', $rute['post /CondicaPrezenta/trimite-remindere']['route']);
        self::assertSame('post', $rute['post /CondicaPrezenta/trimite-remindere']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\TrimiteReminderePrezenta',
            $rute['post /CondicaPrezenta/trimite-remindere']['actionClassName']
        );
        self::assertSame('/CondicaPrezenta/export/:format', $rute['get /CondicaPrezenta/export/:format']['route']);
        self::assertSame('get', $rute['get /CondicaPrezenta/export/:format']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\DescarcaExportCondica',
            $rute['get /CondicaPrezenta/export/:format']['actionClassName']
        );
        self::assertSame('/ManagementAngajati/reset-date-condica', $rute['get /ManagementAngajati/reset-date-condica']['route']);
        self::assertSame('get', $rute['get /ManagementAngajati/reset-date-condica']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\ObtinePrevizualizareResetCondica',
            $rute['get /ManagementAngajati/reset-date-condica']['actionClassName']
        );
        self::assertSame('/ManagementAngajati/reset-date-condica', $rute['post /ManagementAngajati/reset-date-condica']['route']);
        self::assertSame('post', $rute['post /ManagementAngajati/reset-date-condica']['method']);
        self::assertSame(
            'Espo\\Modules\\ManagementAngajati\\Api\\ReseteazaDateTestCondica',
            $rute['post /ManagementAngajati/reset-date-condica']['actionClassName']
        );
        self::assertTrue(is_subclass_of(Prezenta::class, Record::class));
    }

    public function testInterfataLunaraRamanePeScopeulEntitatiiSiCrudulNativ(): void
    {
        $definitieScope = $this->citesteJson('Resources/metadata/scopes/Prezenta.json');
        $definitieClient = $this->citesteJson('Resources/metadata/clientDefs/Prezenta.json');

        self::assertTrue($definitieScope['tab']);
        self::assertSame(
            'management-angajati:controllers/prezenta',
            $definitieClient['controller']
        );
        self::assertSame(
            'management-angajati:views/prezenta/lista-lunara',
            $definitieClient['views']['list']
        );
        self::assertGreaterThanOrEqual(13, count($this->citesteJson('Resources/routes.json')));

        $caleView = self::CALE_CLIENT . '/src/views/prezenta/lista-lunara.js';
        $continutView = file_get_contents($caleView);

        self::assertNotFalse($continutView);
        self::assertStringContainsString("getCollectionFactory().create('Prezenta')", $continutView);
        self::assertStringContainsString("getModelFactory().create('Prezenta')", $continutView);
        self::assertStringContainsString('modelExistent.save(', $continutView);
        self::assertStringContainsString('zi.codPrezentaAutomat', $continutView);
        self::assertStringContainsString("translate('cicAutomat'", $continutView);
        self::assertStringContainsString(
            "'Prezenta/prezenta-personala'",
            $continutView
        );
        $continutControlor = file_get_contents(
            self::CALE_CLIENT . '/src/controllers/prezenta.js'
        );

        self::assertNotFalse($continutControlor);
        self::assertStringContainsString("define(['controllers/record']", $continutControlor);
        self::assertStringContainsString('this.getUser().isAdmin()', $continutControlor);
        self::assertStringContainsString(
            "return 'management-angajati:views/prezenta/prezenta-indisponibila'",
            $continutControlor
        );
        self::assertStringNotContainsString("return 'views/list'", $continutControlor);
        self::assertStringNotContainsString('deschideAdministrare', $continutView);
        self::assertFileDoesNotExist(
            self::CALE_CLIENT . '/src/views/prezenta/lista-administrare.js'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/res/templates/prezenta/lista-lunara.tpl'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/src/views/prezenta/prezenta-indisponibila.js'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/res/templates/prezenta/prezenta-indisponibila.tpl'
        );

        $sablonLista = file_get_contents(
            self::CALE_CLIENT . '/res/templates/prezenta/lista-lunara.tpl'
        );
        self::assertNotFalse($sablonLista);
        self::assertStringContainsString('{{#if poateFiCompletata}}', $sablonLista);
        self::assertStringContainsString('{{#if esteCicActiv}}', $sablonLista);
        self::assertStringContainsString('stare-cic-panou', $sablonLista);
        self::assertStringContainsString('calendar.situatieCic', $continutView);
        self::assertStringContainsString(
            'class="btn prezenta-actiune-card {{butonClasa}}',
            $sablonLista
        );
        self::assertStringNotContainsString('poateMergeLaLunaAnterioara', $continutView);
        self::assertStringNotContainsString('poateMergeLaLunaAnterioara', $sablonLista);
    }

    public function testCondicaEsteTabNativCuPermisiuneIndependenta(): void
    {
        $scopeCondica = $this->citesteJson('Resources/metadata/scopes/CondicaPrezenta.json');
        $scopeGestionare = $this->citesteJson('Resources/metadata/scopes/GestionareAngajati.json');
        $definitieCondica = $this->citesteJson('Resources/metadata/clientDefs/CondicaPrezenta.json');
        $definitieGestionare = $this->citesteJson(
            'Resources/metadata/clientDefs/GestionareAngajati.json'
        );
        $definitieAcl = $this->citesteJson('Resources/metadata/app/acl.json');
        self::assertFalse($scopeCondica['entity']);
        self::assertSame('boolean', $scopeCondica['acl']);
        self::assertTrue($scopeCondica['tab']);
        self::assertFalse($scopeCondica['layouts']);
        self::assertFalse($scopeCondica['customizable']);

        self::assertFalse($scopeGestionare['entity']);
        self::assertSame('boolean', $scopeGestionare['acl']);
        self::assertTrue($scopeGestionare['tab']);
        self::assertFalse($scopeGestionare['customizable']);
        self::assertArrayNotHasKey('CondicaPrezenta', $definitieAcl['mandatory']['scopeLevel']);
        self::assertArrayNotHasKey('adminMandatory', $definitieAcl);
        self::assertSame(
            'management-angajati:controllers/condica-prezenta',
            $definitieCondica['controller']
        );
        self::assertSame(
            'management-angajati:views/condica-prezenta/lunara',
            $definitieCondica['views']['list']
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/src/views/condica-prezenta/lunara.js'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/res/templates/condica-prezenta/lunara.tpl'
        );
        $continutCondica = file_get_contents(
            self::CALE_CLIENT . '/src/views/condica-prezenta/lunara.js'
        );
        $sablonCondica = file_get_contents(
            self::CALE_CLIENT . '/res/templates/condica-prezenta/lunara.tpl'
        );
        $stiluri = file_get_contents(
            self::CALE_CLIENT . '/css/management-angajati.css'
        );
        self::assertNotFalse($continutCondica);
        self::assertNotFalse($sablonCondica);
        self::assertNotFalse($stiluri);
        self::assertStringNotContainsString(
            'poateMergeLaLunaAnterioara',
            $continutCondica
        );
        self::assertStringNotContainsString(
            'poateMergeLaLunaAnterioara',
            $sablonCondica
        );
        self::assertStringContainsString('lunaMinimaDisponibila', $continutCondica);
        self::assertStringContainsString('poateMergeAnterior', $continutCondica);
        self::assertStringContainsString(
            '{{#unless poateMergeAnterior}}disabled{{/unless}}',
            $sablonCondica
        );
        self::assertStringContainsString(
            'angajatiDupaId[celula.idAngajat]',
            $continutCondica
        );
        self::assertStringContainsString(
            'this.actiuniPrezenta[cheieActiune]',
            $continutCondica
        );
        self::assertStringContainsString(
            "'CondicaPrezenta/marcheaza-prezenta'",
            $continutCondica
        );
        self::assertStringNotContainsString(
            "getModelFactory().create('Prezenta')",
            $continutCondica
        );
        self::assertStringNotContainsString('assignedUserId:', $continutCondica);
        self::assertStringContainsString(
            'data-cheie-actiune="{{cheieActiune}}"',
            $sablonCondica
        );
        self::assertStringNotContainsString('data-id-angajat', $sablonCondica);
        self::assertStringNotContainsString('data-nume-angajat', $sablonCondica);
        self::assertStringNotContainsString('data-id-prezenta', $sablonCondica);
        self::assertStringContainsString('condica-antet', $sablonCondica);
        self::assertStringContainsString('condica-indicatori', $sablonCondica);
        self::assertStringContainsString('<details class="condica-indicator-detalii">', $sablonCondica);
        self::assertStringContainsString('data-detaliu-lipsa="{{idAngajat}}"', $sablonCondica);
        self::assertStringContainsString('condica-mini-tabel', $sablonCondica);
        self::assertStringContainsString('condica-legenda', $sablonCondica);
        self::assertStringContainsString('{{clasaVizuala}}', $sablonCondica);
        self::assertStringContainsString('obtineClasaVizualaCelula', $continutCondica);
        self::assertStringContainsString('position: sticky', $stiluri);
        self::assertStringContainsString('max-height: 68vh', $stiluri);
        self::assertStringContainsString('max-height: 170px', $stiluri);
        self::assertStringContainsString('summary::-webkit-details-marker', $stiluri);
        self::assertSame(
            'management-angajati:controllers/gestionare-angajati',
            $definitieGestionare['controller']
        );
        self::assertFileDoesNotExist(
            self::CALE_MODUL . '/Resources/metadata/app/clientRoutes.json'
        );

        $continutControlor = file_get_contents(
            self::CALE_CLIENT . '/src/controllers/condica-prezenta.js'
        );

        self::assertNotFalse($continutControlor);
        self::assertStringContainsString("entityType = 'Prezenta'", $continutControlor);
        self::assertStringContainsString("checkScope('CondicaPrezenta')", $continutControlor);
        self::assertStringContainsString(
            'this.getAcl().check(this.entityType, actiune)',
            $continutControlor
        );
        self::assertStringNotContainsString('actionSetari', $continutControlor);

        $continutGestionare = file_get_contents(
            self::CALE_CLIENT . '/src/controllers/gestionare-angajati.js'
        );
        self::assertNotFalse($continutGestionare);
        self::assertStringContainsString("checkScope('GestionareAngajati')", $continutGestionare);
        self::assertStringContainsString('actionSetariCondica()', $continutGestionare);
        self::assertStringContainsString('actionProfiluriAngajati()', $continutGestionare);
        self::assertFileExists(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/index.js'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/setari-condica.js'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/res/templates/gestionare-angajati/setari-condica.tpl'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/rand-setari-condica.js'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/res/templates/gestionare-angajati/rand-setari-condica.tpl'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/profiluri-angajati.js'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/rand-profil-angajat.js'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/res/templates/gestionare-angajati/profiluri-angajati.tpl'
        );
        self::assertFileExists(
            self::CALE_CLIENT . '/res/templates/gestionare-angajati/rand-profil-angajat.tpl'
        );

        $continutSetariCondica = file_get_contents(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/setari-condica.js'
        );
        self::assertNotFalse($continutSetariCondica);
        self::assertStringContainsString(
            'this.createView(',
            $continutSetariCondica
        );
        self::assertStringContainsString(
            'afterRender()',
            $continutSetariCondica
        );
        self::assertStringContainsString(
            "\$('<article>')",
            $continutSetariCondica
        );
        self::assertStringContainsString(
            ".addClass('setari-condica-rand-angajat')",
            $continutSetariCondica
        );
        self::assertStringContainsString(
            'configuratieAngajat,',
            $continutSetariCondica
        );
        self::assertStringContainsString('?.obtineConfiguratie(mod)', $continutSetariCondica);
        self::assertStringContainsString('angajati: angajati', $continutSetariCondica);
        self::assertStringNotContainsString(
            'dataset.idAngajat',
            $continutSetariCondica
        );

        $sablonSetariCondica = file_get_contents(
            self::CALE_CLIENT . '/res/templates/gestionare-angajati/setari-condica.tpl'
        );
        self::assertNotFalse($sablonSetariCondica);
        self::assertStringNotContainsString('idAngajat', $sablonSetariCondica);
        self::assertStringNotContainsString('idConfiguratie', $sablonSetariCondica);
        self::assertSame(1, substr_count($sablonSetariCondica, 'data-action="salveaza"'));
        self::assertStringContainsString(
            'data-role="randuri-angajati"',
            $sablonSetariCondica
        );

        $continutRandSetari = file_get_contents(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/rand-setari-condica.js'
        );
        self::assertNotFalse($continutRandSetari);
        self::assertStringContainsString(
            'this.configuratieAngajat = this.options.configuratieAngajat;',
            $continutRandSetari
        );
        self::assertStringContainsString(
            'angajatId: this.configuratieAngajat.idAngajat',
            $continutRandSetari
        );
        self::assertStringContainsString('obtineConfiguratie(modAplicareRemote)', $continutRandSetari);
        self::assertStringContainsString('`views/fields/${tip}`', $continutRandSetari);
        self::assertStringContainsString("remoteActiv: 'bool'", $continutRandSetari);
        self::assertStringContainsString("dataInceputCic: 'date'", $continutRandSetari);
        self::assertStringContainsString("'[data-role=\"comutator-optiune\"]'", $continutRandSetari);
        self::assertStringContainsString('comutaOptiunea(eveniment, element)', $continutRandSetari);
        self::assertStringContainsString("bifa.trigger('click')", $continutRandSetari);
        self::assertStringContainsString(
            'this.getDateTime().toDisplayDate(dataAngajare)',
            $continutRandSetari
        );
        self::assertStringContainsString("translate('dataAngajareLipsa'", $continutRandSetari);
        self::assertStringContainsString('.fetchToModel()', $continutRandSetari);
        self::assertStringContainsString('.validate()', $continutRandSetari);
        self::assertStringNotContainsString('afiseazaConflictCic', $continutRandSetari);
        self::assertStringNotContainsString('Espo.Ui.success', $continutRandSetari);
        self::assertTrue(
            strpos($continutSetariCondica, 'Espo.Ui.notify(false);') <
            strpos($continutSetariCondica, "Espo.Ui.success(this.translate('setariSalvate'")
        );

        $sablonRandSetari = file_get_contents(
            self::CALE_CLIENT . '/res/templates/gestionare-angajati/rand-setari-condica.tpl'
        );
        self::assertNotFalse($sablonRandSetari);
        self::assertStringNotContainsString('idAngajat', $sablonRandSetari);
        self::assertStringNotContainsString('idConfiguratie', $sablonRandSetari);
        self::assertStringNotContainsString('data-action="salveaza"', $sablonRandSetari);
        self::assertStringNotContainsString('type="date"', $sablonRandSetari);
        self::assertStringContainsString('{{dataAngajareEticheta}}', $sablonRandSetari);
        self::assertStringContainsString('{{dataAngajareAfisata}}', $sablonRandSetari);
        self::assertSame(4, substr_count($sablonRandSetari, 'class="field" data-name="data'));
        self::assertStringContainsString(
            'data-name="remoteActiv"',
            $sablonRandSetari
        );
        self::assertStringContainsString(
            'data-name="cicActiv"',
            $sablonRandSetari
        );
        self::assertStringContainsString('setari-condica-optiune', $sablonRandSetari);
        self::assertSame(2, substr_count($sablonRandSetari, 'data-role="comutator-optiune"'));
        self::assertSame(2, substr_count($sablonRandSetari, 'data-camp-bool='));
        self::assertStringContainsString('{{{dataInceputRemote}}}', $sablonRandSetari);
        self::assertStringContainsString('{{{dataSfarsitRemote}}}', $sablonRandSetari);
        self::assertStringContainsString('{{{dataInceputCic}}}', $sablonRandSetari);
        self::assertStringContainsString('{{{dataSfarsitCic}}}', $sablonRandSetari);

        $sablonSetariConcedii = file_get_contents(
            self::CALE_CLIENT . '/res/templates/gestionare-angajati/concedii.tpl'
        );
        self::assertNotFalse($sablonSetariConcedii);
        self::assertStringContainsString('setari-concedii-page', $sablonSetariConcedii);
        self::assertStringContainsString('data-role="profiluri"', $sablonSetariConcedii);

        $sablonRandProfilConcediu = file_get_contents(
            self::CALE_CLIENT . '/res/templates/gestionare-angajati/rand-profil-concediu.tpl'
        );
        self::assertNotFalse($sablonRandProfilConcediu);
        self::assertStringContainsString('profil-concediu-identitate', $sablonRandProfilConcediu);
        self::assertStringContainsString('{{dataAngajareAfisata}}', $sablonRandProfilConcediu);
        self::assertStringNotContainsString('istoricEticheta', $sablonRandProfilConcediu);
        self::assertStringNotContainsString('istoric.length', $sablonRandProfilConcediu);

        $continutRandProfilConcediu = file_get_contents(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/rand-profil-concediu.js'
        );
        self::assertNotFalse($continutRandProfilConcediu);
        self::assertStringNotContainsString('istoricEticheta', $continutRandProfilConcediu);
        self::assertStringContainsString(
            'this.getDateTime().toDisplayDate(this.profil.dataAngajare)',
            $continutRandProfilConcediu
        );

        $continutInstalare = file_get_contents(
            dirname(self::CALE_MODUL, 5) . '/scripts/AfterInstall.php'
        );

        self::assertNotFalse($continutInstalare);
        self::assertStringContainsString(
            '$listaNoua[] = self::SCOPE_PREZENTA;',
            $continutInstalare
        );
        self::assertStringContainsString(
            'NotificatorDependentaZileSarbatoare::class',
            $continutInstalare
        );
        self::assertStringContainsString(
            '->notificaDacaEsteNecesara()',
            $continutInstalare
        );
        self::assertStringContainsString("'type' => 'group'", $continutInstalare);
        self::assertSame(1, substr_count($continutInstalare, "'type' => 'group'"));
        self::assertStringContainsString("'text' => '\$management'", $continutInstalare);
        self::assertStringContainsString(
            '$listaNoua[] = self::SCOPE_GESTIONARE;',
            $continutInstalare
        );
        self::assertStringContainsString(
            "'itemList' => [",
            $continutInstalare
        );
        self::assertStringContainsString('self::SCOPE_CONDICA', $continutInstalare);
        self::assertStringContainsString('self::SCOPE_GESTIONARE', $continutInstalare);
        self::assertStringNotContainsString('creeazaTab', $continutInstalare);
        self::assertStringNotContainsString("'aclScope' =>", $continutInstalare);
    }

    public function testProfilulAngajatuluiExtindeUserFaraScopeSauEntitateParalela(): void
    {
        $definitieUser = $this->citesteJson('Resources/metadata/entityDefs/User.json');
        $rute = $this->indexeazaRute($this->citesteJson('Resources/routes.json'));

        self::assertSame('date', $definitieUser['fields']['dataAngajare']['type']);
        self::assertTrue($definitieUser['fields']['dataAngajare']['audited']);
        self::assertTrue($definitieUser['fields']['dataAngajare']['customizationDisabled']);
        self::assertFileDoesNotExist(
            self::CALE_MODUL . '/Resources/metadata/scopes/ProfilAngajat.json'
        );
        self::assertFileDoesNotExist(
            self::CALE_MODUL . '/Resources/metadata/entityDefs/ProfilAngajat.json'
        );
        self::assertSame('/ManagementAngajati/profiluri-angajati', $rute['get /ManagementAngajati/profiluri-angajati']['route']);
        self::assertSame('get', $rute['get /ManagementAngajati/profiluri-angajati']['method']);
        self::assertSame('/ManagementAngajati/profiluri-angajati', $rute['put /ManagementAngajati/profiluri-angajati']['route']);
        self::assertSame('put', $rute['put /ManagementAngajati/profiluri-angajati']['method']);

        $continutRand = file_get_contents(
            self::CALE_CLIENT . '/src/views/gestionare-angajati/rand-profil-angajat.js'
        );

        self::assertNotFalse($continutRand);
        self::assertStringContainsString("create('User')", $continutRand);
        self::assertStringContainsString("'views/fields/date'", $continutRand);
        self::assertStringContainsString("'views/fields/enum'", $continutRand);
    }

    public function testConfiguratiaCondiciiEsteEntitateInternaCuAclManagerialMostenit(): void
    {
        $scope = $this->citesteJson(
            'Resources/metadata/scopes/ConfiguratieCondicaAngajat.json'
        );
        $definitie = $this->citesteJson(
            'Resources/metadata/entityDefs/ConfiguratieCondicaAngajat.json'
        );
        $record = $this->citesteJson(
            'Resources/metadata/recordDefs/ConfiguratieCondicaAngajat.json'
        );
        $acl = $this->citesteJson('Resources/metadata/app/acl.json');

        self::assertTrue($scope['entity']);
        self::assertFalse($scope['acl']);
        self::assertFalse($scope['tab']);
        self::assertFalse($scope['layouts']);
        self::assertFalse($scope['customizable']);
        self::assertFalse($scope['importable']);
        self::assertTrue($scope['preserveAuditLog']);
        self::assertSame(
            'boolean:GestionareAngajati',
            $acl['mandatory']['scopeLevel']['ConfiguratieCondicaAngajat']
        );
        self::assertArrayNotHasKey('adminMandatory', $acl);
        self::assertTrue($definitie['transactionalSave']);
        self::assertTrue($definitie['fields']['remoteActiv']['audited']);
        self::assertTrue($definitie['fields']['cicActiv']['audited']);
        self::assertTrue($definitie['deleteId']);
        self::assertSame(
            ['angajatId', 'deleteId'],
            $definitie['indexes']['angajatUnic']['columns']
        );
        self::assertTrue($definitie['fields']['angajat']['required']);
        self::assertTrue($definitie['fields']['angajat']['readOnlyAfterCreate']);
        self::assertArrayNotHasKey('beforeCreateHookClassNameList', $record);
        self::assertArrayNotHasKey('beforeUpdateHookClassNameList', $record);
        self::assertFileExists(
            self::CALE_MODUL . '/Hooks/ConfiguratieCondicaAngajat/Valideaza.php'
        );
        self::assertTrue(
            $record['relationships']['angajat']['linkForeignAccessCheckDisabled']
        );
        self::assertFalse($record['massActions']['update']['allowed']);
        self::assertFalse($record['massActions']['delete']['allowed']);
    }

    public function testStareaLuniiEsteInternaUnicaInghetataSiAuditata(): void
    {
        $scope = $this->citesteJson('Resources/metadata/scopes/StareLunaCondica.json');
        $definitie = $this->citesteJson(
            'Resources/metadata/entityDefs/StareLunaCondica.json'
        );
        $acl = $this->citesteJson('Resources/metadata/app/acl.json');
        $prezenta = $this->citesteJson('Resources/metadata/entityDefs/Prezenta.json');

        self::assertTrue($scope['entity']);
        self::assertFalse($scope['acl']);
        self::assertFalse($scope['tab']);
        self::assertFalse($scope['customizable']);
        self::assertTrue($scope['preserveAuditLog']);
        self::assertSame(
            'boolean:GestionareAngajati',
            $acl['mandatory']['scopeLevel']['StareLunaCondica']
        );
        self::assertSame('unique', $definitie['indexes']['lunaUnica']['type']);
        self::assertSame(['luna'], $definitie['indexes']['lunaUnica']['columns']);
        self::assertSame('jsonObject', $definitie['fields']['instantaneu']['type']);
        self::assertTrue($definitie['fields']['instantaneu']['readOnly']);
        self::assertTrue($definitie['fields']['stare']['audited']);
        self::assertTrue($definitie['fields']['inchisaLa']['audited']);
        self::assertTrue($definitie['fields']['inchisaDe']['audited']);
        self::assertTrue($definitie['fields']['revizie']['audited']);

        foreach (
            ['esteCorectieManageriala', 'motivCorectie', 'corectataLa', 'corectataDe']
            as $camp
        ) {
            self::assertTrue($prezenta['fields'][$camp]['audited'], $camp);
            self::assertTrue($prezenta['fields'][$camp]['readOnly'], $camp);
        }
    }

    public function testMostenireaBooleanaEsteRezolvataPentruAngajatManagerSiAdministrator(): void
    {
        $cazuri = [
            'angajat' => false,
            'manager' => true,
            'administrator' => true,
        ];

        foreach ($cazuri as $profil => $accesGestionare) {
            $tabelAcl = $this->createStub(Table::class);
            $tabelAcl
                ->method('getScopeData')
                ->willReturnCallback(
                    static fn (string $scope): ScopeData =>
                        $scope === 'GestionareAngajati' ?
                            ScopeData::fromRaw($accesGestionare) :
                            ScopeData::fromRaw(false)
                );

            $rezultat = (new ScopeDataResolver($tabelAcl))
                ->resolve('boolean:GestionareAngajati');

            self::assertTrue($rezultat->isBoolean(), $profil);
            self::assertSame($accesGestionare, $rezultat->isTrue(), $profil);
        }
    }

    public function testLayouturileNativeExpunCampurileNecesareAdministrariiPrezentei(): void
    {
        $lista = $this->citesteJson('Resources/layouts/Prezenta/list.json');
        $detaliu = $this->citesteJson('Resources/layouts/Prezenta/detail.json');
        $detaliuMic = $this->citesteJson('Resources/layouts/Prezenta/detailSmall.json');
        $filtre = $this->citesteJson('Resources/layouts/Prezenta/filters.json');

        self::assertSame(
            ['dataPrezenta', 'assignedUser', 'codPrezenta', 'modifiedAt', 'modifiedBy'],
            array_column($lista, 'name')
        );
        self::assertNotEmpty($detaliu);
        self::assertNotEmpty($detaliuMic);
        self::assertSame(
            ['dataPrezenta', 'codPrezenta', 'assignedUser', 'teams', 'createdAt', 'modifiedAt'],
            $filtre
        );
    }

    public function testSurseleNuPastreazaDenumirileRetrase(): void
    {
        $directorSursa = dirname(self::CALE_MODUL, 5);
        $termenRetras = 'pon' . 'taj';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directorSursa,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $fisier) {
            if (!$fisier instanceof \SplFileInfo || !$fisier->isFile()) {
                continue;
            }

            $cale = $fisier->getPathname();
            $continut = file_get_contents($cale);

            self::assertStringNotContainsString($termenRetras, strtolower($cale));
            self::assertNotFalse($continut);
            self::assertStringNotContainsString($termenRetras, strtolower($continut), $cale);
        }
    }

    public function testInterfataFolosesteCampurileDataNativeEspoCrm(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                self::CALE_CLIENT,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $fisier) {
            if (!$fisier instanceof \SplFileInfo || !$fisier->isFile()) {
                continue;
            }

            $continut = file_get_contents($fisier->getPathname());

            self::assertNotFalse($continut);
            self::assertSame(
                0,
                preg_match('/<input\b[^>]*\btype\s*=\s*["\']date["\']/i', $continut),
                $fisier->getPathname()
            );
        }
    }

    /** @return array<string, mixed> */
    private function citesteJson(string $caleRelativa): array
    {
        $continut = file_get_contents(self::CALE_MODUL . '/' . $caleRelativa);

        self::assertNotFalse($continut);

        $dateDecodificate = json_decode($continut, true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($dateDecodificate);

        return $dateDecodificate;
    }

    /**
     * @param list<array<string, mixed>> $rute
     * @return array<string, array<string, mixed>>
     */
    private function indexeazaRute(array $rute): array
    {
        $rezultat = [];

        foreach ($rute as $ruta) {
            $rezultat[$ruta['method'] . ' ' . $ruta['route']] = $ruta;
        }

        return $rezultat;
    }

    /**
     * @param array<string, mixed> $date
     * @return list<string>
     */
    private function obtineCheiRecursive(array $date, string $prefix = ''): array
    {
        $chei = [];

        foreach ($date as $cheie => $valoare) {
            $cale = $prefix === '' ? $cheie : $prefix . '.' . $cheie;
            $chei[] = $cale;

            if (is_array($valoare)) {
                array_push($chei, ...$this->obtineCheiRecursive($valoare, $cale));
            }
        }

        return $chei;
    }
}
