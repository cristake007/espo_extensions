<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

/** Captura imuabila folosita ulterior la generarea documentelor. */
class InstantaneuDocumenteConcediu
{
    public function __construct(
        private EntityManager $managerEntitati,
        private CalculatorZileConcediu $calculator,
    ) {}

    /** @return array<string, mixed> */
    public function creeaza(Entity $cerere): array
    {
        $angajat = $this->managerEntitati->getEntityById(
            User::ENTITY_TYPE,
            (string) $cerere->get('assignedUserId')
        );

        if (!$angajat instanceof User) {
            throw new RuntimeException('Angajatul cererii nu mai este disponibil.');
        }

        $calcul = $this->calculator->calculeaza(
            $angajat,
            (string) $cerere->get('dataInceput'),
            (string) $cerere->get('dataSfarsit')
        );
        $alocari = $this->managerEntitati->getRDBRepository('AlocareConcediu')
            ->where(['cerereId' => $cerere->getId(), 'stare' => 'Rezervata'])
            ->find();
        $rezervatCerere = [];

        foreach ($alocari as $alocare) {
            $idDrept = (string) $alocare->get('dreptId');
            $rezervatCerere[$idDrept] = ($rezervatCerere[$idDrept] ?? 0) +
                (int) $alocare->get('numarZile');
        }

        $drepturi = [];
        $consumatePeAni = [];

        foreach ($this->managerEntitati->getRDBRepository('DreptConcediu')
            ->where(['assignedUserId' => $angajat->getId()])
            ->order('anOrigine', 'ASC')
            ->order('dataAcordare', 'ASC')
            ->find() as $drept) {
            $an = (int) $drept->get('anOrigine');
            $drepturi[] = [
                'id' => $drept->getId(),
                'an' => $an,
                'dataAcordare' => (string) $drept->get('dataAcordare'),
                'acordate' => (int) $drept->get('zileAcordate'),
                'ramase' => max(0,
                    (int) $drept->get('zileAcordate') -
                    (int) $drept->get('zileConsumate') -
                    (int) $drept->get('zileRezervate') +
                    ($rezervatCerere[$drept->getId()] ?? 0)
                ),
            ];
            $consumatePeAni[$an] = ($consumatePeAni[$an] ?? 0) +
                (int) $drept->get('zileConsumate');
        }

        $datePeLuni = [];
        foreach ($calcul['date'] as $data) {
            $datePeLuni[substr($data, 0, 7)][] = $data;
        }

        $segmente = [];
        foreach ($datePeLuni as $luna => $date) {
            $an = (int) substr($luna, 0, 4);
            $inceput = $date[0];
            $sfarsit = $date[count($date) - 1];
            $soldInainte = 0;
            $report = 0;
            $zileAnualeAcordate = 0;

            foreach ($drepturi as $drept) {
                if ($drept['an'] <= $an && $drept['dataAcordare'] <= $inceput) {
                    $soldInainte += $drept['ramase'];
                    if ($drept['an'] < $an) {
                        $report += $drept['ramase'];
                    } elseif ($drept['an'] === $an) {
                        $zileAnualeAcordate += $drept['acordate'];
                    }
                }
            }

            $zile = count($date);
            $segmente[] = [
                'luna' => $luna,
                'dataInceput' => $inceput,
                'dataSfarsit' => $sfarsit,
                'an' => $an,
                'numarLuna' => (int) substr($luna, 5, 2),
                'zileSolicitate' => $zile,
                'zileAnuale' => $zileAnualeAcordate,
                'zileConsumate' => $consumatePeAni[$an] ?? 0,
                'zileReportate' => $report,
                'soldInainte' => $soldInainte,
                'soldDupa' => $soldInainte - $zile,
            ];

            $ramase = $zile;
            foreach ($drepturi as &$drept) {
                if ($ramase === 0) {
                    break;
                }
                if ($drept['an'] > $an || $drept['dataAcordare'] > $inceput) {
                    continue;
                }
                $consum = min($ramase, $drept['ramase']);
                $drept['ramase'] -= $consum;
                $ramase -= $consum;
                if ($drept['an'] === $an) {
                    $consumatePeAni[$an] = ($consumatePeAni[$an] ?? 0) + $consum;
                }
            }
            unset($drept);
        }

        $aprobatori = [];
        foreach ((array) $cerere->get('aprobatoriNecesari') as $idAprobator) {
            if (!is_string($idAprobator)) {
                continue;
            }
            $aprobator = $this->managerEntitati->getEntityById(User::ENTITY_TYPE, $idAprobator);
            if ($aprobator instanceof User) {
                $aprobatori[] = $aprobator->getName() ?? (string) $aprobator->get('userName');
            }
        }

        return [
            'versiune' => 3,
            'emisLa' => date('Y-m-d'),
            'codVerificare' => strtoupper(bin2hex(random_bytes(16))),
            'angajat' => [
                'id' => $angajat->getId(),
                'nume' => (string) $angajat->get('lastName'),
                'prenume' => (string) $angajat->get('firstName'),
                'numeComplet' => $angajat->getName(),
            ],
            'aprobatori' => $aprobatori,
            'segmente' => $segmente,
        ];
    }

    /**
     * Corecteaza instantaneele create inainte ca documentele sa distinga dreptul
     * anual configurat de dreptul efectiv acordat la data concediului.
     *
     * @param array<string, mixed> $instantaneu
     * @return array<string, mixed>
     */
    public function pregatesteInstantaneuVechiPentruDocument(array $instantaneu): array
    {
        if ((int) ($instantaneu['versiune'] ?? 0) >= 3) {
            return $instantaneu;
        }

        $segmente = [];
        foreach ((array) ($instantaneu['segmente'] ?? []) as $segmentBrut) {
            $segment = is_object($segmentBrut) ? get_object_vars($segmentBrut) : $segmentBrut;
            if (!is_array($segment)) {
                continue;
            }

            $disponibileDinAn = max(
                0,
                (int) ($segment['soldInainte'] ?? 0) -
                (int) ($segment['zileReportate'] ?? 0)
            );
            $consumateDinAn = (int) ($segment['zileConsumate'] ?? 0);

            if ($disponibileDinAn === 0 && $consumateDinAn === 0) {
                $segment['zileAnuale'] = 0;
            }
            $segmente[] = $segment;
        }

        $instantaneu['segmente'] = $segmente;
        $instantaneu['versiune'] = 3;

        return $instantaneu;
    }
}
