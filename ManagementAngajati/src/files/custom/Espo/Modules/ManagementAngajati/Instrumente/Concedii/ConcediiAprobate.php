<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use DateTimeImmutable;
use Espo\ORM\EntityManager;

/** Proiecteaza cererile aprobate ca absente automate, fara a duplica date in Prezenta. */
class ConcediiAprobate
{
    public function __construct(private EntityManager $managerEntitati) {}

    /** @return array<string, string> Cheia este data, valoarea este ID-ul cererii. */
    public function obtinePentruAngajatIntre(
        string $idAngajat,
        string $dataInceput,
        string $dataSfarsit,
    ): array {
        $cereri = $this->managerEntitati->getRDBRepository('CerereConcediu')
            ->where([
                'assignedUserId' => $idAngajat,
                'stare' => ['Aprobata', 'AnulareSolicitata'],
                'dataInceput<=' => $dataSfarsit,
                'dataSfarsit>=' => $dataInceput,
            ])
            ->find();
        $rezultat = [];

        foreach ($cereri as $cerere) {
            $inceput = max($dataInceput, (string) $cerere->get('dataInceput'));
            $sfarsit = min($dataSfarsit, (string) $cerere->get('dataSfarsit'));
            $data = DateTimeImmutable::createFromFormat('!Y-m-d', $inceput);
            $ultima = DateTimeImmutable::createFromFormat('!Y-m-d', $sfarsit);

            if (!$data || !$ultima) {
                continue;
            }

            for (; $data <= $ultima; $data = $data->modify('+1 day')) {
                $rezultat[$data->format('Y-m-d')] = (string) $cerere->getId();
            }
        }

        return $rezultat;
    }

    public function esteInConcediu(string $idAngajat, string $data): bool
    {
        return $this->managerEntitati->getRDBRepository('CerereConcediu')
            ->where([
                'assignedUserId' => $idAngajat,
                'stare' => ['Aprobata', 'AnulareSolicitata'],
                'dataInceput<=' => $data,
                'dataSfarsit>=' => $data,
            ])
            ->findOne() !== null;
    }
}
