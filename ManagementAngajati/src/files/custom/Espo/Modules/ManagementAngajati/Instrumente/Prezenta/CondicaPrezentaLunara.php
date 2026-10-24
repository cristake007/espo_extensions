<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use DateTimeImmutable;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\OrdonatorAngajati;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\WorkingTime\CalendarFactory;

/** Construieste proiectia lunara a condicii din datele native EspoCRM. */
class CondicaPrezentaLunara
{
    private const string TIP_ENTITATE = 'Prezenta';

    public function __construct(
        private EntityManager $managerEntitati,
        private SelectBuilderFactory $fabricaSelectii,
        private CalendarFactory $fabricaCalendare,
        private CalendarPrezentaPersonala $calendarPrezentaPersonala,
        private Acl $acl,
        private AccesAngajatiCondica $accesAngajati,
        private DateTime $dataOra,
        private CicluLunarCondica $cicluLunar,
        private NormalizatorInstantaneuCondica $normalizatorInstantaneu,
        private EligibilitateAngajat $eligibilitateAngajat,
        private CalculatorCompletitudineCondica $calculatorCompletitudine,
        private OrdonatorAngajati $ordonatorAngajati,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function obtine(string $luna): array
    {
        $this->cicluLunar->valideazaLuna($luna);
        $stare = $this->cicluLunar->obtine($luna);

        if ($stare?->get('stare') === CicluLunarCondica::STARE_INCHISA) {
            $instantaneu = $this->normalizatorInstantaneu->normalizeaza(
                $stare->get('instantaneu')
            );
            $instantaneu = $this->aplicaPrezenteCurentePeStructuraInghetata($instantaneu);

            return [
                ...$instantaneu,
                'cicluLunar' => $this->cicluLunar->descrie($luna),
            ];
        }

        return $this->obtineDinDateCurente($luna);
    }

    /** @return array<string, mixed> */
    public function obtineDinDateCurente(string $luna): array
    {
        $this->cicluLunar->valideazaLuna($luna);

        $angajati = $this->obtineAngajatiPermisi($luna);
        $idAngajati = array_map(
            static fn (User $angajat): string => $angajat->getId(),
            $angajati
        );
        $prezente = $this->obtinePrezente($idAngajati, $luna);
        $prezenteDupaAngajatSiData = $this->indexeazaPrezente($prezente);
        $dataCurenta = $this->dataOra->getToday()->toString();
        $randuriAngajati = [];
        $calendareAngajati = [];
        $editareAngajati = [];
        $dateCondica = [];

        foreach ($angajati as $angajat) {
            $idAngajat = $angajat->getId();
            $editareAngajati[$idAngajat] = $this->accesAngajati->poateEdita($angajat);
            $calendar = $this->fabricaCalendare->createForUser($angajat);
            $nume = trim($angajat->getName() ?? '');

            if (!$calendar->isAvailable()) {
                $randuriAngajati[$idAngajat] = [
                    'idAngajat' => $idAngajat,
                    'numeAngajat' => $nume !== '' ? $nume : (string) $angajat->get('userName'),
                    'esteRemote' => false,
                    'areCalendarLucru' => false,
                    'areDataAngajare' =>
                        is_string($angajat->get('dataAngajare')) &&
                        $angajat->get('dataAngajare') !== '',
                    'zileScadente' => 0,
                    'zileCompletate' => 0,
                    'zileLipsa' => 0,
                    'orePlanificateLuna' => 0.0,
                ];
                $calendareAngajati[$idAngajat] = [];

                continue;
            }

            $calendarLunar = $this->calendarPrezentaPersonala->obtine($angajat, $luna);
            $zileDupaData = [];
            $orePlanificate = 0.0;
            $esteRemote = false;

            foreach ($calendarLunar['zile'] as $zi) {
                $zileDupaData[$zi['data']] = $zi;
                $esteRemote = $esteRemote || ($zi['esteRemote'] ?? false) === true;

                if ($zi['esteZiLucratoare']) {
                    $dateCondica[$zi['data']] = true;
                    if (
                        $zi['areDataAngajare'] &&
                        !$zi['esteInainteDeAngajare']
                    ) {
                        $orePlanificate += $zi['orePlanificate'];
                    }
                }
            }

            $randuriAngajati[$idAngajat] = [
                'idAngajat' => $idAngajat,
                'numeAngajat' => $nume !== '' ? $nume : (string) $angajat->get('userName'),
                'esteRemote' => $esteRemote,
                'areCalendarLucru' => true,
                'areDataAngajare' =>
                    is_string($angajat->get('dataAngajare')) &&
                    $angajat->get('dataAngajare') !== '',
                'zileScadente' => 0,
                'zileCompletate' => 0,
                'zileLipsa' => 0,
                'orePlanificateLuna' => $orePlanificate,
            ];
            $calendareAngajati[$idAngajat] = $zileDupaData;
        }

        $date = array_keys($dateCondica);
        sort($date);
        $zile = [];

        foreach ($date as $data) {
            $celule = [];

            foreach ($randuriAngajati as $idAngajat => $randAngajat) {
                $zi = $calendareAngajati[$idAngajat][$data] ?? null;
                $prezenta = $prezenteDupaAngajatSiData[$idAngajat][$data] ?? null;
                $esteZiLucratoare = (bool) ($zi['esteZiLucratoare'] ?? false);
                $areDataAngajare = $randAngajat['areDataAngajare'];
                $esteInainteDeAngajare =
                    (bool) ($zi['esteInainteDeAngajare'] ?? false);
                $esteViitoare = $data > $dataCurenta;
                $codAutomat = $esteZiLucratoare ? ($zi['codPrezentaAutomat'] ?? null) : null;
                $codManual = $esteZiLucratoare ? $prezenta?->get('codPrezenta') : null;
                $codPrezenta = $codAutomat ?? $codManual;
                $esteLipsa = $esteZiLucratoare &&
                    $areDataAngajare &&
                    !$esteInainteDeAngajare &&
                    !$esteViitoare &&
                    $codPrezenta === null;

                $celule[] = [
                    'idAngajat' => $idAngajat,
                    'idPrezenta' => $prezenta?->getId(),
                    'codPrezenta' => $codPrezenta,
                    'sursa' => $codAutomat === 'CIC' ?
                        'CIC' : ($codAutomat === 'CO' ? 'Concediu' : ($codManual !== null ? 'manual' : null)),
                    'esteZiLucratoare' => $esteZiLucratoare,
                    'areDataAngajare' => $areDataAngajare,
                    'esteInainteDeAngajare' => $esteInainteDeAngajare,
                    'esteViitoare' => $esteViitoare,
                    'esteLipsa' => $esteLipsa,
                    'esteCic' => $codAutomat === 'CIC',
                    'esteConcediu' => $codAutomat === 'CO',
                    'esteRemote' => (bool) ($zi['esteRemote'] ?? false),
                    'orePlanificate' => (float) ($zi['orePlanificate'] ?? 0.0),
                    'oraIntrare' => $zi['oraIntrare'] ?? null,
                    'oraIesire' => $zi['oraIesire'] ?? null,
                    'poateFiCompletata' => $this->poateFiCompletata(
                        $prezenta,
                        $data,
                        $esteZiLucratoare,
                        $esteViitoare,
                        $codAutomat !== null,
                        $areDataAngajare,
                        $esteInainteDeAngajare,
                        $editareAngajati[$idAngajat]
                    ),
                    'poateFiCorectata' => false,
                ];
            }

            $zile[] = [
                'data' => $data,
                'ziSaptamana' => (int) (new DateTimeImmutable($data))->format('N'),
                'esteViitoare' => $data > $dataCurenta,
                'celule' => $celule,
            ];
        }

        $numarCalendare = count(array_filter(
            $randuriAngajati,
            static fn (array $rand): bool => $rand['areCalendarLucru']
        ));
        $statistici = $this->calculatorCompletitudine->calculeaza(
            $randuriAngajati,
            $zile,
            $numarCalendare,
            $luna < substr($dataCurenta, 0, 7),
        );

        return [
            'luna' => $luna,
            'dataCurenta' => $dataCurenta,
            'cicluLunar' => $this->cicluLunar->descrie($luna),
            'angajati' => array_values($randuriAngajati),
            'zile' => $zile,
            'statistici' => $statistici,
        ];
    }

    /**
     * Pastreaza structura inghetata, dar citeste valorile auditate ale entitatii Prezenta.
     *
     * @param array<string, mixed> $instantaneu
     * @return array<string, mixed>
     */
    private function aplicaPrezenteCurentePeStructuraInghetata(array $instantaneu): array
    {
        $idAngajati = array_values(array_filter(array_map(
            static fn (mixed $angajat): ?string =>
                is_array($angajat) && is_string($angajat['idAngajat'] ?? null) ?
                    $angajat['idAngajat'] :
                    null,
            $instantaneu['angajati'] ?? []
        )));
        $prezente = $this->obtinePrezente(
            $idAngajati,
            (string) ($instantaneu['luna'] ?? '')
        );
        $index = $this->indexeazaPrezente($prezente);
        foreach ($instantaneu['zile'] as &$zi) {
            foreach ($zi['celule'] as &$celula) {
                $idAngajat = (string) $celula['idAngajat'];
                $data = (string) $zi['data'];
                $esteZiLucratoare = ($celula['esteZiLucratoare'] ?? false) === true;
                $esteViitoare = ($celula['esteViitoare'] ?? false) === true;
                $esteCic = ($celula['esteCic'] ?? false) === true;
                $esteConcediu = ($celula['esteConcediu'] ?? false) === true;
                $prezenta = $index[$idAngajat][$data] ?? null;

                if (!$esteCic && !$esteConcediu) {
                    $celula['idPrezenta'] = $prezenta?->getId();
                    $celula['codPrezenta'] = $prezenta?->get('codPrezenta');
                    $celula['sursa'] = $prezenta ?
                        ((bool) $prezenta->get('esteCorectieManageriala') ?
                            'corectieManageriala' :
                            'manual') :
                        null;
                }

                $celula['esteLipsa'] =
                    $esteZiLucratoare &&
                    ($celula['areDataAngajare'] ?? true) === true &&
                    ($celula['esteInainteDeAngajare'] ?? false) !== true &&
                    !$esteViitoare &&
                    $celula['codPrezenta'] === null;
                $celula['poateFiCompletata'] = false;
                $celula['poateFiCorectata'] =
                    $esteZiLucratoare &&
                    ($celula['areDataAngajare'] ?? true) === true &&
                    ($celula['esteInainteDeAngajare'] ?? false) !== true &&
                    !$esteViitoare &&
                    !$esteCic &&
                    !$esteConcediu;
            }
            unset($celula);
        }
        unset($zi);

        $calendareConfigurate = (int) $instantaneu['statistici']['calendareConfigurate'];
        $instantaneu['statistici'] = [
            ...$instantaneu['statistici'],
            ...$this->calculatorCompletitudine->calculeaza(
                $instantaneu['angajati'],
                $instantaneu['zile'],
                $calendareConfigurate,
                (string) $instantaneu['luna'] < substr(
                    $this->dataOra->getToday()->toString(),
                    0,
                    7
                ),
            ),
        ];

        return $instantaneu;
    }

    /** @return list<User> */
    private function obtineAngajatiPermisi(string $luna): array
    {
        $angajati = $this->ordonatorAngajati->ordoneaza(
            $this->managerEntitati
                ->getRDBRepositoryByClass(User::class)
                ->where([
                    'type' => User::TYPE_REGULAR,
                    'isActive' => true,
                ])
                ->find()
        );
        $rezultat = [];

        foreach ($angajati as $angajat) {
            if (
                $this->eligibilitateAngajat->participaLaCondicaLunii($angajat, $luna) &&
                $this->accesAngajati->poateCiti($angajat)
            ) {
                $rezultat[] = $angajat;
            }
        }

        return $rezultat;
    }

    /**
     * @param list<string> $idAngajati
     * @return iterable<int, Entity>
     */
    private function obtinePrezente(array $idAngajati, string $luna): iterable
    {
        if ($idAngajati === []) {
            return [];
        }

        $primaZi = $luna . '-01';
        $ultimaZi = (new DateTimeImmutable($primaZi))
            ->modify('last day of this month')
            ->format('Y-m-d');
        $interogare = $this->fabricaSelectii
            ->create()
            ->from(self::TIP_ENTITATE)
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select([
                'id',
                'assignedUserId',
                'dataPrezenta',
                'codPrezenta',
                'esteCorectieManageriala',
            ])
            ->where([
                'assignedUserId' => $idAngajati,
                'dataPrezenta>=' => $primaZi,
                'dataPrezenta<=' => $ultimaZi,
            ])
            ->build();

        return $this->managerEntitati
            ->getRDBRepository(self::TIP_ENTITATE)
            ->clone($interogare)
            ->find();
    }

    /**
     * @param iterable<int, Entity> $prezente
     * @return array<string, array<string, Entity>>
     */
    private function indexeazaPrezente(iterable $prezente): array
    {
        $rezultat = [];

        foreach ($prezente as $prezenta) {
            $idAngajat = (string) $prezenta->get('assignedUserId');
            $data = (string) $prezenta->get('dataPrezenta');

            if ($idAngajat !== '' && $data !== '') {
                $rezultat[$idAngajat][$data] = $prezenta;
            }
        }

        return $rezultat;
    }

    private function poateFiCompletata(
        ?Entity $prezenta,
        string $data,
        bool $esteZiLucratoare,
        bool $esteViitoare,
        bool $esteAutomata,
        bool $areDataAngajare,
        bool $esteInainteDeAngajare,
        bool $poateEditaAngajatul,
    ): bool {
        if (
            !$esteZiLucratoare ||
            $esteViitoare ||
            $esteAutomata ||
            !$areDataAngajare ||
            $esteInainteDeAngajare ||
            !$this->cicluLunar->poateFiModificataManagerial(substr($data, 0, 7))
        ) {
            return false;
        }

        if ($prezenta) {
            return $this->acl->checkEntityEdit($prezenta);
        }

        return $this->acl->checkScope(self::TIP_ENTITATE, Table::ACTION_CREATE) &&
            $poateEditaAngajatul;
    }
}
