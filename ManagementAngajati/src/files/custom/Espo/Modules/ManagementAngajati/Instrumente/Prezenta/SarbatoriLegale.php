<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Modules\ManagementAngajati\Instrumente\Dependente\VerificatorZileSarbatoare;
use Espo\ORM\EntityManager;

/** Acces unic la sarbatorile legale furnizate de extensia ZileSarbatoare. */
class SarbatoriLegale
{
    private const string COD_TARA = 'RO';

    public function __construct(
        private EntityManager $managerEntitati,
        private VerificatorZileSarbatoare $verificator,
    ) {}

    public function esteSarbatoare(string $data): bool
    {
        return isset($this->obtineIntre($data, $data)[$data]);
    }

    /** @return array<string, true> */
    public function obtineIntre(string $dataInceput, string $dataSfarsit): array
    {
        $this->verificator->verificaDisponibilitateSauArunca();
        $rezultat = [];
        $sarbatori = $this->managerEntitati
            ->getRDBRepository(VerificatorZileSarbatoare::TIP_ENTITATE)
            ->where([
                'dateStart>=' => $dataInceput,
                'dateStart<=' => $dataSfarsit,
                'countryCode' => self::COD_TARA,
            ])
            ->find();

        foreach ($sarbatori as $sarbatoare) {
            $data = (string) $sarbatoare->get('dateStart');

            if ($data !== '') {
                $rezultat[$data] = true;
            }
        }

        return $rezultat;
    }
}
