<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\ORM\Repository\Option\RemoveOption;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;

/** Elimina exclusiv datele de test ale concediilor, pastrand utilizatorii si configurarea aprobatorilor. */
class ResetatorDateTestConcedii
{
    public const string CONFIRMARE = 'RESET DATE CONCEDII';

    private const array OPTIUNI_STERGERE = [RemoveOption::SILENT => true];

    /** @var list<string> */
    private const array ORDINE_STERGERE = [
        'RegistruConcediu',
        'AlocareConcediu',
        'CerereConcediu',
        'DreptConcediu',
        'ProfilConcediu',
    ];

    public function __construct(private EntityManager $managerEntitati) {}

    /** @return array<string, int> */
    public function previzualizeaza(): array
    {
        return [
            'profiluri' => $this->managerEntitati->getRDBRepository('ProfilConcediu')->count(),
            'drepturi' => $this->managerEntitati->getRDBRepository('DreptConcediu')->count(),
            'cereri' => $this->managerEntitati->getRDBRepository('CerereConcediu')->count(),
            'alocari' => $this->managerEntitati->getRDBRepository('AlocareConcediu')->count(),
            'registru' => $this->managerEntitati->getRDBRepository('RegistruConcediu')->count(),
        ];
    }

    /** @return array{sterse: array<string, int>, dupaResetare: array<string, int>} */
    public function reseteaza(): array
    {
        $inainte = $this->previzualizeaza();

        $this->managerEntitati->getTransactionManager()->run(function (): void {
            $this->stergeDocumenteGenerate();
            foreach (self::ORDINE_STERGERE as $tipEntitate) {
                $this->stergeDefinitiv($tipEntitate);
            }
        });

        return ['sterse' => $inainte, 'dupaResetare' => $this->previzualizeaza()];
    }

    private function stergeDefinitiv(string $tipEntitate): void
    {
        $entitati = $this->managerEntitati->getRDBRepository($tipEntitate)
            ->order('id')
            ->forUpdate()
            ->find();

        foreach ($entitati as $entitate) {
            $id = $entitate->getId();
            $this->managerEntitati->removeEntity($entitate, self::OPTIUNI_STERGERE);
            $this->managerEntitati->getRDBRepository($tipEntitate)->deleteFromDb($id, true);
        }
    }

    /** Stergerea nativa a Attachment declanseaza si eliminarea continutului din storage. */
    private function stergeDocumenteGenerate(): void
    {
        $depozit = $this->managerEntitati->getRDBRepositoryByClass(Attachment::class);
        $atasamente = $depozit->where([
            'parentType' => 'CerereConcediu',
            'field' => 'documenteConcediu',
        ])->order('id')->forUpdate()->find();

        foreach ($atasamente as $atasament) {
            if (!$atasament instanceof Attachment) {
                continue;
            }
            $id = $atasament->getId();
            $this->managerEntitati->removeEntity($atasament, self::OPTIUNI_STERGERE);
            $depozit->deleteFromDb($id, true);
        }
    }
}
