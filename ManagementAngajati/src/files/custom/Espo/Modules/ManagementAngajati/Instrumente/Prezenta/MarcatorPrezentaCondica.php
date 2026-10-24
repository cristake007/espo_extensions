<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\FieldValidation\Type;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Salveaza o celula din condica pentru angajatul identificat explicit. */
class MarcatorPrezentaCondica
{
    private const string TIP_VALIDARE_VALID = 'valid';

    public function __construct(
        private EntityManager $managerEntitati,
        private Acl $acl,
        private AccesAngajatiCondica $accesAngajati,
        private FieldValidationManager $managerValidareCampuri,
        private ValidatorPrezenta $validatorPrezenta,
    ) {}

    /** @return array{idPrezenta: string, aFostCreata: bool} */
    public function marcheaza(string $idAngajat, string $data, string $cod): array
    {
        if (!$this->acl->checkField('Prezenta', 'codPrezenta', Table::ACTION_EDIT)) {
            throw new Forbidden();
        }

        return $this->managerEntitati->getTransactionManager()->run(
            function () use ($idAngajat, $data, $cod): array {
                $angajat = $this->obtineAngajat($idAngajat);

                if (!$this->accesAngajati->poateEdita($angajat)) {
                    throw new Forbidden();
                }

                $prezenta = $this->managerEntitati
                    ->getRDBRepository('Prezenta')
                    ->where([
                        'assignedUserId' => $idAngajat,
                        'dataPrezenta' => $data,
                    ])
                    ->forUpdate()
                    ->findOne();
                $aFostCreata = !$prezenta;

                if ($prezenta) {
                    if (!$this->acl->checkEntityEdit($prezenta)) {
                        throw new Forbidden();
                    }

                    $prezenta->set('codPrezenta', $cod);
                    $this->valideazaCampurile($prezenta);
                    $this->validatorPrezenta->valideazaLaActualizareManageriala($prezenta);
                } else {
                    if (!$this->acl->checkScope('Prezenta', Table::ACTION_CREATE)) {
                        throw new Forbidden();
                    }

                    $prezenta = $this->creeazaPrezenta($angajat, $data, $cod);
                    $this->valideazaCampurile($prezenta);
                    $this->validatorPrezenta->valideazaLaCreareManageriala(
                        $prezenta,
                        $angajat,
                        false
                    );
                }

                $this->managerEntitati->saveEntity($prezenta);

                return [
                    'idPrezenta' => (string) $prezenta->getId(),
                    'aFostCreata' => $aFostCreata,
                ];
            }
        );
    }

    private function obtineAngajat(string $idAngajat): User
    {
        $angajat = $this->managerEntitati
            ->getRDBRepositoryByClass(User::class)
            ->where(['id' => $idAngajat])
            ->forUpdate()
            ->findOne();

        if (!$angajat instanceof User || !$angajat->isActive() || !$angajat->isRegular()) {
            throw BadRequest::createWithBody(
                'angajatInvalid',
                Body::create()->withMessageTranslation('angajatInvalid', 'Prezenta')
            );
        }

        return $angajat;
    }

    private function creeazaPrezenta(User $angajat, string $data, string $cod): Entity
    {
        $prezenta = $this->managerEntitati->getNewEntity('Prezenta');

        $prezenta->set([
            'assignedUserId' => $angajat->getId(),
            'assignedUserName' => $angajat->getName(),
            'dataPrezenta' => $data,
            'codPrezenta' => $cod,
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
            'marcarePrezentaInvalida',
            Body::create()->withMessageTranslation(
                'marcarePrezentaInvalida',
                'CondicaPrezenta'
            )
        );
    }
}
