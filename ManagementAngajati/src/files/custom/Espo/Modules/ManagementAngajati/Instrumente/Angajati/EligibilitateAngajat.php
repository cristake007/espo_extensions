<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Angajati;

use DateTimeImmutable;
use Espo\Entities\User;

class EligibilitateAngajat
{
    public function obtineDataAngajare(User $angajat): ?string
    {
        $valoare = $angajat->get('dataAngajare');

        return is_string($valoare) && $valoare !== '' ? $valoare : null;
    }

    public function esteInainteDeAngajare(User $angajat, string $data): bool
    {
        $dataAngajare = $this->obtineDataAngajare($angajat);

        return $dataAngajare !== null && $data < $dataAngajare;
    }

    public function participaLaCondicaLunii(User $angajat, string $luna): bool
    {
        $dataAngajare = $this->obtineDataAngajare($angajat);

        if ($dataAngajare === null) {
            return true;
        }

        $ultimaZi = (new DateTimeImmutable($luna . '-01'))
            ->modify('last day of this month')
            ->format('Y-m-d');

        return $dataAngajare <= $ultimaZi;
    }

    /** @param iterable<int, User> $angajati */
    public function obtineLunaMinimaDisponibila(
        iterable $angajati,
        string $lunaImplicita,
    ): ?string {
        $lunaMinima = null;
        $existaAngajati = false;

        foreach ($angajati as $angajat) {
            $existaAngajati = true;
            $dataAngajare = $this->obtineDataAngajare($angajat);

            // Fara data angajarii nu putem afirma corect cat de vechi este istoricul.
            if ($dataAngajare === null) {
                return null;
            }

            $lunaAngajare = substr($dataAngajare, 0, 7);

            if ($lunaMinima === null || $lunaAngajare < $lunaMinima) {
                $lunaMinima = $lunaAngajare;
            }
        }

        return $existaAngajati ? $lunaMinima : $lunaImplicita;
    }
}
