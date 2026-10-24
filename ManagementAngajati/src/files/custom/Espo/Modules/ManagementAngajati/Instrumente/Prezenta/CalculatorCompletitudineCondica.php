<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

/** Calculeaza completitudinea lunii exclusiv din proiectia canonica a condicii. */
class CalculatorCompletitudineCondica
{
    public const string MOTIV_FARA_ANGAJATI = 'faraAngajati';
    public const string MOTIV_CALENDARE_LIPSA = 'calendareLipsa';
    public const string MOTIV_PROFILURI_LIPSA = 'profiluriLipsa';
    public const string MOTIV_FARA_ZILE_SCADENTE = 'faraZileScadente';
    public const string MOTIV_PREZENTE_LIPSA = 'prezenteLipsa';
    public const string MOTIV_ZILE_VIITOARE = 'zileViitoare';
    public const string MOTIV_LUNA_NEINCHEIATA = 'lunaNeincheiata';

    /**
     * @param array<int|string, array<string, mixed>> $angajati
     * @param list<array<string, mixed>> $zile
     * @return array<string, int|bool|list<string>>
     */
    public function calculeaza(
        array &$angajati,
        array $zile,
        int $calendareConfigurate,
        bool $lunaCalendaristicaIncheiata,
    ): array {
        $indexAngajati = [];

        foreach ($angajati as $cheie => &$angajat) {
            $angajat['zileScadente'] = 0;
            $angajat['zileCompletate'] = 0;
            $angajat['zileLipsa'] = 0;
            $indexAngajati[(string) $angajat['idAngajat']] = $cheie;
        }
        unset($angajat);

        $totalScadente = 0;
        $totalCompletate = 0;
        $totalLipsa = 0;
        $totalViitoare = 0;

        foreach ($zile as $zi) {
            foreach ($zi['celule'] as $celula) {
                if (($celula['esteZiLucratoare'] ?? false) !== true) {
                    continue;
                }

                if (
                    ($celula['areDataAngajare'] ?? true) !== true ||
                    ($celula['esteInainteDeAngajare'] ?? false) === true
                ) {
                    continue;
                }

                if (($celula['esteViitoare'] ?? false) === true) {
                    $totalViitoare++;
                    continue;
                }

                $idAngajat = (string) $celula['idAngajat'];

                if (!array_key_exists($idAngajat, $indexAngajati)) {
                    continue;
                }

                $cheie = $indexAngajati[$idAngajat];
                $totalScadente++;
                $angajati[$cheie]['zileScadente']++;

                if (($celula['esteLipsa'] ?? false) === true) {
                    $totalLipsa++;
                    $angajati[$cheie]['zileLipsa']++;
                } else {
                    $totalCompletate++;
                    $angajati[$cheie]['zileCompletate']++;
                }
            }
        }

        $numarAngajati = count($angajati);
        $profiluriConfigurate = count(array_filter(
            $angajati,
            static fn (array $rand): bool => ($rand['areDataAngajare'] ?? true) === true
        ));
        $motiveBlocare = $this->obtineMotiveBlocare(
            $numarAngajati,
            $calendareConfigurate,
            $profiluriConfigurate,
            $totalScadente,
            $totalLipsa,
            $totalViitoare,
            $lunaCalendaristicaIncheiata,
        );
        $esteCompleta = $numarAngajati > 0 &&
            $calendareConfigurate === $numarAngajati &&
            $profiluriConfigurate === $numarAngajati &&
            $totalScadente > 0 &&
            $totalLipsa === 0 &&
            $totalViitoare === 0;

        return [
            'numarAngajati' => $numarAngajati,
            'calendareConfigurate' => $calendareConfigurate,
            'calendareLipsa' => $numarAngajati - $calendareConfigurate,
            'profiluriConfigurate' => $profiluriConfigurate,
            'profiluriLipsa' => $numarAngajati - $profiluriConfigurate,
            'zileScadente' => $totalScadente,
            'zileCompletate' => $totalCompletate,
            'zileLipsa' => $totalLipsa,
            'zileViitoare' => $totalViitoare,
            'procentCompletare' => $totalScadente > 0 ?
                (int) round($totalCompletate * 100 / $totalScadente) :
                0,
            'esteCompleta' => $esteCompleta,
            'poateFiInchisa' => $esteCompleta && $lunaCalendaristicaIncheiata,
            'motiveBlocareInchidere' => $motiveBlocare,
        ];
    }

    /** @return list<string> */
    private function obtineMotiveBlocare(
        int $numarAngajati,
        int $calendareConfigurate,
        int $profiluriConfigurate,
        int $totalScadente,
        int $totalLipsa,
        int $totalViitoare,
        bool $lunaCalendaristicaIncheiata,
    ): array {
        $motive = [];

        if (!$lunaCalendaristicaIncheiata) {
            $motive[] = self::MOTIV_LUNA_NEINCHEIATA;
        }

        if ($numarAngajati === 0) {
            $motive[] = self::MOTIV_FARA_ANGAJATI;
        }

        if ($calendareConfigurate < $numarAngajati) {
            $motive[] = self::MOTIV_CALENDARE_LIPSA;
        }

        if ($profiluriConfigurate < $numarAngajati) {
            $motive[] = self::MOTIV_PROFILURI_LIPSA;
        }

        if ($numarAngajati > 0 && $totalScadente === 0) {
            $motive[] = self::MOTIV_FARA_ZILE_SCADENTE;
        }

        if ($totalLipsa > 0) {
            $motive[] = self::MOTIV_PREZENTE_LIPSA;
        }

        if ($totalViitoare > 0) {
            $motive[] = self::MOTIV_ZILE_VIITOARE;
        }

        return $motive;
    }
}
