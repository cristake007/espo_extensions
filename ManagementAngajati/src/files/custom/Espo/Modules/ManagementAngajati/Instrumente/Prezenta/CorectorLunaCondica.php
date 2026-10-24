<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\FieldValidation\Type;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Aplica o corectie motivata unei luni inchise si actualizeaza instantaneul fara regenerare. */
class CorectorLunaCondica
{
    private const string TIP_VALIDARE_VALID = 'valid';

    public function __construct(
        private EntityManager $managerEntitati,
        private CicluLunarCondica $cicluLunar,
        private Acl $acl,
        private FieldValidationManager $managerValidareCampuri,
        private DateTime $dataOra,
        private User $utilizatorCurent,
        private NormalizatorInstantaneuCondica $normalizatorInstantaneu,
    ) {}

    /** @return array<string, mixed> */
    public function corecteaza(
        string $luna,
        string $idAngajat,
        string $data,
        string $cod,
        string $motiv,
    ): array {
        $motiv = trim($motiv);

        if (
            $motiv === '' ||
            mb_strlen($motiv) > 1000 ||
            substr($data, 0, 7) !== $luna
        ) {
            throw BadRequest::createWithBody(
                'corectieCondicaInvalida',
                Body::create()->withMessageTranslation('corectieCondicaInvalida', 'CondicaPrezenta')
            );
        }

        return $this->managerEntitati->getTransactionManager()->run(
            function () use ($luna, $idAngajat, $data, $cod, $motiv): array {
                $stare = $this->cicluLunar->obtine($luna, true);

                if (!$stare || $stare->get('stare') !== CicluLunarCondica::STARE_INCHISA) {
                    throw Conflict::createWithBody(
                        'corectieNecesitaLunaInchisa',
                        Body::create()->withMessageTranslation(
                            'corectieNecesitaLunaInchisa',
                            'CondicaPrezenta'
                        )
                    );
                }

                $instantaneu = $this->normalizatorInstantaneu->normalizeaza(
                    $stare->get('instantaneu')
                );
                [$indiceZi, $indiceCelula] = $this->gasesteCelula(
                    $instantaneu,
                    $idAngajat,
                    $data
                );
                /** @var array<string, mixed> $celula */
                $celula = $instantaneu['zile'][$indiceZi]['celule'][$indiceCelula];

                if (
                    ($celula['esteZiLucratoare'] ?? false) !== true ||
                    ($celula['areDataAngajare'] ?? true) !== true ||
                    ($celula['esteInainteDeAngajare'] ?? false) === true ||
                    ($celula['esteViitoare'] ?? false) === true ||
                    ($celula['esteCic'] ?? false) === true
                ) {
                    throw Conflict::createWithBody(
                        'ziCondicaNecorectabila',
                        Body::create()->withMessageTranslation(
                            'ziCondicaNecorectabila',
                            'CondicaPrezenta'
                        )
                    );
                }

                $prezenta = $this->obtineSauCreeazaPrezenta($idAngajat, $data, $celula);
                $prezenta->set([
                    'codPrezenta' => $cod,
                    'esteCorectieManageriala' => true,
                    'motivCorectie' => $motiv,
                    'corectataLa' => $this->dataOra->getNow()->toString(),
                    'corectataDeId' => $this->utilizatorCurent->getId(),
                ]);
                $this->valideazaCampurile($prezenta);
                $this->managerEntitati->saveEntity($prezenta);

                $stare->set('revizie', (int) $stare->get('revizie') + 1);
                $this->managerEntitati->saveEntity($stare);

                return [
                    'luna' => $luna,
                    'idPrezenta' => $prezenta->getId(),
                    'cicluLunar' => $this->cicluLunar->descrie($luna),
                ];
            }
        );
    }

    /** @param array<string, mixed> $celula */
    private function obtineSauCreeazaPrezenta(
        string $idAngajat,
        string $data,
        array $celula,
    ): Entity {
        $prezenta = $this->managerEntitati
            ->getRDBRepository('Prezenta')
            ->where(['assignedUserId' => $idAngajat, 'dataPrezenta' => $data])
            ->forUpdate()
            ->findOne();

        if ($prezenta) {
            if (!$this->acl->checkEntityEdit($prezenta)) {
                throw new Forbidden();
            }

            return $prezenta;
        }

        if (!$this->acl->checkScope('Prezenta', Table::ACTION_CREATE)) {
            throw new Forbidden();
        }

        $prezenta = $this->managerEntitati->getNewEntity('Prezenta');
        $angajat = $this->managerEntitati->getEntityById(User::ENTITY_TYPE, $idAngajat);
        $prezenta->set([
            'assignedUserId' => $idAngajat,
            'assignedUserName' => $angajat instanceof User ? $angajat->getName() : null,
            'dataPrezenta' => $data,
            'codPrezenta' => $celula['codPrezenta'] ?? 'P',
        ]);

        return $prezenta;
    }

    /** @throws BadRequest */
    private function valideazaCampurile(Entity $prezenta): void
    {
        if (
            $this->managerValidareCampuri->check(
                $prezenta,
                'dataPrezenta',
                Type::REQUIRED
            ) &&
            $this->managerValidareCampuri->check(
                $prezenta,
                'dataPrezenta',
                self::TIP_VALIDARE_VALID
            ) &&
            $this->managerValidareCampuri->check(
                $prezenta,
                'codPrezenta',
                Type::REQUIRED
            ) &&
            $this->managerValidareCampuri->check(
                $prezenta,
                'codPrezenta',
                self::TIP_VALIDARE_VALID
            )
        ) {
            return;
        }

        throw BadRequest::createWithBody(
            'corectieCondicaInvalida',
            Body::create()->withMessageTranslation(
                'corectieCondicaInvalida',
                'CondicaPrezenta'
            )
        );
    }

    /**
     * @param array<string, mixed> $instantaneu
     * @return array{int, int}
     */
    private function gasesteCelula(array $instantaneu, string $idAngajat, string $data): array
    {
        foreach (($instantaneu['zile'] ?? []) as $indiceZi => $zi) {
            if (!is_array($zi) || ($zi['data'] ?? null) !== $data) {
                continue;
            }

            foreach (($zi['celule'] ?? []) as $indiceCelula => $celula) {
                if (is_array($celula) && ($celula['idAngajat'] ?? null) === $idAngajat) {
                    return [(int) $indiceZi, (int) $indiceCelula];
                }
            }
        }

        throw BadRequest::createWithBody(
            'corectieCondicaInvalida',
            Body::create()->withMessageTranslation('corectieCondicaInvalida', 'CondicaPrezenta')
        );
    }

}
