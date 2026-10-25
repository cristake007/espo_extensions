<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Field\DateTime as CampDataOra;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ConcediiAprobate;
use Espo\Tools\WorkingTime\CalendarFactory;
use Espo\Tools\WorkingTime\CalendarUtilityFactory;
use Espo\Tools\WorkingTime\Extractor;

/**
 * Furnizeaza zilele unei luni folosind calendarul de lucru nativ al utilizatorului.
 *
 * @phpstan-type ZiCalendar array{
 *     data: string,
 *     zi: int,
 *     ziSaptamana: int,
 *     esteViitoare: bool,
 *     esteZiLucratoare: bool,
 *     esteSarbatoareLegala: bool,
 *     areDataAngajare: bool,
 *     esteInainteDeAngajare: bool,
 *     esteCic: bool,
 *     esteConcediu: bool,
 *     esteRemote: bool,
 *     orePlanificate: float,
 *     oraIntrare: ?string,
 *     oraIesire: ?string,
 *     codPrezentaAutomat: ?string,
 *     poateFiCompletata: bool
 * }
 * @phpstan-type RezultatCalendar array{
 *     luna: string,
 *     lunaMinimaDisponibila: ?string,
 *     dataCurenta: string,
 *     situatieCic: array{activa: bool, dataInceput: ?string, dataSfarsit: ?string},
 *     cicluLunar: array{
 *         stare: string,
 *         esteInchisa: bool,
 *         inchisaLa: ?string,
 *         inchisaDeId: ?string,
 *         revizie: int,
 *         lunaMinimaEditabila: string,
 *         numarLuniAnterioareEditabile: int
 *     },
 *     esteLunaEditabila: bool,
 *     zile: list<ZiCalendar>
 * }
 */
class CalendarPrezentaPersonala
{
    private const string SCOPE_TRADUCERI = 'Prezenta';

    public function __construct(
        private CalendarFactory $fabricaCalendare,
        private CalendarUtilityFactory $fabricaUtilitareCalendar,
        private Extractor $extractorIntervale,
        private DateTime $dataOra,
        private ConfiguratiiCondicaAngajati $configuratiiCondica,
        private CicluLunarCondica $cicluLunar,
        private SarbatoriLegale $sarbatoriLegale,
        private NormalizatorInstantaneuCondica $normalizatorInstantaneu,
        private EligibilitateAngajat $eligibilitateAngajat,
        private ConcediiAprobate $concediiAprobate,
    ) {}

    /**
     * @return RezultatCalendar
     * @throws BadRequest
     */
    public function obtine(User $utilizator, string $luna): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $luna)) {
            throw BadRequest::createWithBody(
                'lunaPrezentaInvalida',
                Body::create()->withMessageTranslation('lunaPrezentaInvalida', self::SCOPE_TRADUCERI)
            );
        }

        $dataAngajare = $this->eligibilitateAngajat->obtineDataAngajare($utilizator);
        $lunaMinimaDisponibila = $dataAngajare !== null ?
            substr($dataAngajare, 0, 7) :
            null;
        $dataCurenta = $this->dataOra->getToday()->toString();
        $configuratieAngajat = $this->configuratiiCondica->obtinePentruAngajat(
            (string) $utilizator->getId()
        );
        $situatieCic = $this->configuratiiCondica->descrieSituatieCic(
            $configuratieAngajat,
            $dataCurenta
        );

        if ($lunaMinimaDisponibila !== null && $luna < $lunaMinimaDisponibila) {
            throw BadRequest::createWithBody(
                'lunaInainteDeAngajare',
                Body::create()->withMessageTranslation(
                    'lunaInainteDeAngajare',
                    self::SCOPE_TRADUCERI,
                    ['lunaMinima' => $lunaMinimaDisponibila]
                )
            );
        }

        $stareLuna = $this->cicluLunar->obtine($luna);

        if ($stareLuna?->get('stare') === CicluLunarCondica::STARE_INCHISA) {
            return $this->obtineDinInstantaneu(
                $utilizator,
                $luna,
                $stareLuna->get('instantaneu'),
                $lunaMinimaDisponibila,
                $situatieCic
            );
        }

        $calendar = $this->fabricaCalendare->createForUser($utilizator);

        if (!$calendar->isAvailable()) {
            throw BadRequest::createWithBody(
                'calendarLucruIndisponibil',
                Body::create()->withMessageTranslation('calendarLucruIndisponibil', self::SCOPE_TRADUCERI)
            );
        }

        $utilitarCalendar = $this->fabricaUtilitareCalendar->create($calendar);
        $primaZi = new DateTimeImmutable($luna . '-01 12:00:00', $calendar->getTimezone());
        $ultimaZi = $primaZi->modify('last day of this month');
        $sarbatori = $this->sarbatoriLegale->obtineIntre(
            $primaZi->format('Y-m-d'),
            $ultimaZi->format('Y-m-d')
        );
        $zile = [];
        $cicluLunar = $this->cicluLunar->descrie($luna);
        $esteLunaInchisa = $cicluLunar['esteInchisa'];
        $esteLunaEditabila = $this->cicluLunar->poateFiModificataObisnuit($luna);
        $areDataAngajare = $dataAngajare !== null;
        $concedii = $this->concediiAprobate->obtinePentruAngajatIntre(
            (string) $utilizator->getId(),
            $primaZi->format('Y-m-d'),
            $ultimaZi->format('Y-m-d')
        );

        for ($data = $primaZi; $data <= $ultimaZi; $data = $data->modify('+1 day')) {
            $dataText = $data->format('Y-m-d');
            $ziSaptamana = (int) $data->format('N');
            $esteViitoare = $dataText > $dataCurenta;
            $esteSarbatoareLegala = isset($sarbatori[$dataText]);

            if ($ziSaptamana >= 6 || $esteSarbatoareLegala) {
                continue;
            }

            $esteZiLucratoare = $utilitarCalendar->isWorkingDay(
                CampDataOra::fromDateTime($data)
            );
            $esteInainteDeAngajare = $areDataAngajare && $dataText < $dataAngajare;
            $esteCic = $areDataAngajare &&
                !$esteInainteDeAngajare &&
                $esteZiLucratoare &&
                $this->configuratiiCondica->esteDataCic(
                $configuratieAngajat,
                $dataText
            );
            $esteRemote = $areDataAngajare &&
                !$esteInainteDeAngajare &&
                $esteZiLucratoare &&
                $this->configuratiiCondica->esteDataRemote(
                $configuratieAngajat,
                $dataText
            );
            $esteConcediu = $areDataAngajare &&
                !$esteInainteDeAngajare &&
                $esteZiLucratoare &&
                isset($concedii[$dataText]);
            $codPrezentaAutomat = $esteCic ? 'CIC' : ($esteConcediu ? 'CO' : null);
            $orePlanificate = $esteZiLucratoare ? $utilitarCalendar->getSummedWorkingHours(
                CampDataOra::fromDateTime($data->setTime(0, 0)),
                CampDataOra::fromDateTime($data->modify('+1 day')->setTime(0, 0))
            ) : 0.0;
            [$oraIntrare, $oraIesire] = $esteZiLucratoare ?
                $this->obtineLimiteleProgramului($calendar, $data) :
                [null, null];

            $zile[] = [
                'data' => $dataText,
                'zi' => (int) $data->format('j'),
                'ziSaptamana' => $ziSaptamana,
                'esteViitoare' => $esteViitoare,
                'esteZiLucratoare' => $esteZiLucratoare,
                'esteSarbatoareLegala' => $esteSarbatoareLegala,
                'areDataAngajare' => $areDataAngajare,
                'esteInainteDeAngajare' => $esteInainteDeAngajare,
                'esteCic' => $esteCic,
                'esteConcediu' => $esteConcediu,
                'esteRemote' => $esteRemote,
                'orePlanificate' => $orePlanificate,
                'oraIntrare' => $oraIntrare,
                'oraIesire' => $oraIesire,
                'codPrezentaAutomat' => $codPrezentaAutomat,
                'poateFiCompletata' =>
                    !$esteLunaInchisa &&
                    $esteLunaEditabila &&
                    $areDataAngajare &&
                    !$esteInainteDeAngajare &&
                    !$esteViitoare &&
                    $esteZiLucratoare &&
                    $codPrezentaAutomat === null,
            ];
        }

        return [
            'luna' => $luna,
            'lunaMinimaDisponibila' => $lunaMinimaDisponibila,
            'dataCurenta' => $dataCurenta,
            'situatieCic' => $situatieCic,
            'cicluLunar' => $cicluLunar,
            'esteLunaEditabila' => $esteLunaEditabila,
            'zile' => $zile,
        ];
    }

    /**
     * @param array{activa: bool, dataInceput: ?string, dataSfarsit: ?string} $situatieCic
     * @return RezultatCalendar
     */
    private function obtineDinInstantaneu(
        User $utilizator,
        string $luna,
        mixed $valoareInstantaneu,
        ?string $lunaMinimaDisponibila,
        array $situatieCic,
    ): array {
        $instantaneu = $this->normalizatorInstantaneu->normalizeaza($valoareInstantaneu);
        $idAngajat = $utilizator->getId();
        $zile = [];

        foreach ($instantaneu['zile'] ?? [] as $zi) {
            if (!is_array($zi) || !is_array($zi['celule'] ?? null)) {
                continue;
            }

            foreach ($zi['celule'] as $celula) {
                if (!is_array($celula) || ($celula['idAngajat'] ?? null) !== $idAngajat) {
                    continue;
                }

                $data = (string) $zi['data'];
                $zile[] = [
                    'data' => $data,
                    'zi' => (int) substr($data, 8, 2),
                    'ziSaptamana' => (int) ($zi['ziSaptamana'] ?? 0),
                    'esteViitoare' => (bool) ($celula['esteViitoare'] ?? false),
                    'esteZiLucratoare' => (bool) ($celula['esteZiLucratoare'] ?? false),
                    'esteSarbatoareLegala' => false,
                    'areDataAngajare' => (bool) ($celula['areDataAngajare'] ?? true),
                    'esteInainteDeAngajare' =>
                        (bool) ($celula['esteInainteDeAngajare'] ?? false),
                    'esteCic' => (bool) ($celula['esteCic'] ?? false),
                    'esteConcediu' => (bool) ($celula['esteConcediu'] ?? false),
                    'esteRemote' => (bool) ($celula['esteRemote'] ?? false),
                    'orePlanificate' => (float) ($celula['orePlanificate'] ?? 0.0),
                    'oraIntrare' => $this->sirSauNull($celula['oraIntrare'] ?? null),
                    'oraIesire' => $this->sirSauNull($celula['oraIesire'] ?? null),
                    'codPrezentaAutomat' => ($celula['esteCic'] ?? false) === true ?
                        'CIC' : ((($celula['esteConcediu'] ?? false) === true) ? 'CO' : null),
                    'poateFiCompletata' => false,
                ];
                break;
            }
        }

        return [
            'luna' => $luna,
            'lunaMinimaDisponibila' => $lunaMinimaDisponibila,
            'dataCurenta' => $this->dataOra->getToday()->toString(),
            'situatieCic' => $situatieCic,
            'cicluLunar' => $this->cicluLunar->descrie($luna),
            'esteLunaEditabila' => false,
            'zile' => $zile,
        ];
    }

    /** @return array{?string, ?string} */
    private function obtineLimiteleProgramului(
        \Espo\Tools\WorkingTime\Calendar $calendar,
        DateTimeImmutable $data,
    ): array {
        $intervale = $this->extractorIntervale->extract(
            $calendar,
            CampDataOra::fromDateTime($data->setTime(0, 0)),
            CampDataOra::fromDateTime($data->modify('+1 day')->setTime(0, 0))
        );

        if ($intervale === []) {
            return [null, null];
        }

        $inceput = $intervale[0][0];
        $sfarsit = $intervale[count($intervale) - 1][1];

        return [
            sprintf('%02d:%02d', $inceput->getHour(), $inceput->getMinute()),
            sprintf('%02d:%02d', $sfarsit->getHour(), $sfarsit->getMinute()),
        ];
    }

    private function sirSauNull(mixed $valoare): ?string
    {
        return is_string($valoare) && $valoare !== '' ? $valoare : null;
    }

}
