<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/** Creeaza o singura data instantaneul canonic al unei luni incheiate. */
class InchizatorLunaCondica
{
    public function __construct(
        private EntityManager $managerEntitati,
        private CondicaPrezentaLunara $condica,
        private CicluLunarCondica $cicluLunar,
        private DateTime $dataOra,
        private User $utilizatorCurent,
    ) {}

    /** @return array<string, mixed> */
    public function inchide(string $luna): array
    {
        $this->cicluLunar->valideazaLuna($luna);
        $lunaCurenta = substr($this->dataOra->getToday()->toString(), 0, 7);

        if ($luna >= $lunaCurenta) {
            throw BadRequest::createWithBody(
                'lunaCondicaNeincheiata',
                Body::create()->withMessageTranslation('lunaCondicaNeincheiata', 'CondicaPrezenta')
            );
        }

        return $this->managerEntitati->getTransactionManager()->run(
            function () use ($luna): array {
                $existenta = $this->cicluLunar->obtine($luna, true);

                if ($existenta?->get('stare') === CicluLunarCondica::STARE_INCHISA) {
                    throw Conflict::createWithBody(
                        'lunaCondicaDejaInchisa',
                        Body::create()->withMessageTranslation(
                            'lunaCondicaDejaInchisa',
                            'CondicaPrezenta'
                        )
                    );
                }

                $instantaneu = $this->condica->obtineDinDateCurente($luna);

                if (($instantaneu['statistici']['poateFiInchisa'] ?? false) !== true) {
                    throw Conflict::createWithBody(
                        'lunaCondicaIncompleta',
                        Body::create()->withMessageTranslation(
                            'lunaCondicaIncompleta',
                            'CondicaPrezenta'
                        )
                    );
                }

                $instantaneu = $this->pregatestePentruInghetare($instantaneu);
                $stare = $existenta ?? $this->managerEntitati->getNewEntity(
                    CicluLunarCondica::TIP_ENTITATE
                );
                $stare->set([
                    'name' => $luna,
                    'luna' => $luna,
                    'stare' => CicluLunarCondica::STARE_INCHISA,
                    'instantaneu' => $instantaneu,
                    'inchisaLa' => $this->dataOra->getNow()->toString(),
                    'inchisaDeId' => $this->utilizatorCurent->getId(),
                    'revizie' => 1,
                ]);
                $this->managerEntitati->saveEntity($stare);

                return [
                    ...$instantaneu,
                    'cicluLunar' => $this->cicluLunar->descrie($luna),
                ];
            }
        );
    }

    /**
     * @param array<string, mixed> $instantaneu
     * @return array<string, mixed>
     */
    private function pregatestePentruInghetare(array $instantaneu): array
    {
        foreach ($instantaneu['zile'] as &$zi) {
            foreach ($zi['celule'] as &$celula) {
                $poateFiCorectata =
                    ($celula['esteZiLucratoare'] ?? false) === true &&
                    ($celula['areDataAngajare'] ?? true) === true &&
                    ($celula['esteInainteDeAngajare'] ?? false) !== true &&
                    ($celula['esteViitoare'] ?? false) !== true &&
                    ($celula['esteCic'] ?? false) !== true &&
                    ($celula['esteConcediu'] ?? false) !== true;
                $celula['poateFiCompletata'] = false;
                $celula['poateFiCorectata'] = $poateFiCorectata;
            }
            unset($celula);
        }
        unset($zi);

        return $instantaneu;
    }
}
