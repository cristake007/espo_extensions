<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Field\DateTime as CampDataOra;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\SarbatoriLegale;
use Espo\Tools\WorkingTime\CalendarFactory;
use Espo\Tools\WorkingTime\CalendarUtilityFactory;

/** Calculeaza exclusiv zile intregi, folosind WTC si sarbatorile legale native proiectului. */
class CalculatorZileConcediu
{
    public function __construct(
        private CalendarFactory $fabricaCalendare,
        private CalendarUtilityFactory $fabricaUtilitareCalendar,
        private SarbatoriLegale $sarbatoriLegale,
        private ConfiguratiiCondicaAngajati $configuratiiCondica,
        private EligibilitateAngajat $eligibilitateAngajat,
    ) {}

    /** @return array{total: int, peAni: array<int, int>, date: list<string>} */
    public function calculeaza(User $angajat, string $dataInceput, string $dataSfarsit): array
    {
        $inceput = $this->dataValida($dataInceput);
        $sfarsit = $this->dataValida($dataSfarsit);

        if ($sfarsit < $inceput) {
            $this->eroare('perioadaConcediuInvalida');
        }

        $dataAngajare = $this->eligibilitateAngajat->obtineDataAngajare($angajat);

        if ($dataAngajare === null || $dataInceput < $dataAngajare) {
            $this->eroare('concediuInainteDeAngajare');
        }

        $calendar = $this->fabricaCalendare->createForUser($angajat);

        if (!$calendar->isAvailable()) {
            $this->eroare('calendarLucruIndisponibil');
        }

        $utilitar = $this->fabricaUtilitareCalendar->create($calendar);
        $sarbatori = $this->sarbatoriLegale->obtineIntre($dataInceput, $dataSfarsit);
        $configuratie = $this->configuratiiCondica->obtinePentruAngajat($angajat->getId());
        $peAni = [];
        $date = [];

        for ($data = $inceput; $data <= $sfarsit; $data = $data->modify('+1 day')) {
            $text = $data->format('Y-m-d');

            if ($this->configuratiiCondica->esteDataCic($configuratie, $text)) {
                $this->eroare('concediuSuprapusCic');
            }

            if ((int) $data->format('N') >= 6 || isset($sarbatori[$text])) {
                continue;
            }

            if (!$utilitar->isWorkingDay(CampDataOra::fromDateTime($data))) {
                continue;
            }

            $an = (int) $data->format('Y');
            $peAni[$an] = ($peAni[$an] ?? 0) + 1;
            $date[] = $text;
        }

        if ($date === []) {
            $this->eroare('concediuFaraZileLucratoare');
        }

        return ['total' => count($date), 'peAni' => $peAni, 'date' => $date];
    }

    private function dataValida(string $valoare): DateTimeImmutable
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valoare);

        if (!$data || $data->format('Y-m-d') !== $valoare) {
            $this->eroare('perioadaConcediuInvalida');
        }

        return $data;
    }

    private function eroare(string $mesaj): never
    {
        throw BadRequest::createWithBody(
            $mesaj,
            Body::create()->withMessageTranslation($mesaj, 'CerereConcediu')
        );
    }
}
