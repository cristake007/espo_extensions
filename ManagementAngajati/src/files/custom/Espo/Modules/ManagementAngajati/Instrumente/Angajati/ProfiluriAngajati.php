<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Angajati;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

/** Gestioneaza datele comune extensiei pastrate pe utilizatorul nativ EspoCRM. */
class ProfiluriAngajati
{
    public function __construct(
        private EntityManager $managerEntitati,
        private FieldValidationManager $managerValidareCampuri,
        private OrdonatorAngajati $ordonatorAngajati,
    ) {}

    /** @return array<string, mixed> */
    public function obtine(): array
    {
        $calendare = $this->managerEntitati
            ->getRDBRepository('WorkingTimeCalendar')
            ->order('name')
            ->find();
        $numeCalendare = [];
        $listaCalendare = [];

        foreach ($calendare as $calendar) {
            $id = $calendar->getId();
            $nume = trim((string) $calendar->get('name'));
            $numeCalendare[$id] = $nume !== '' ? $nume : $id;
            $listaCalendare[] = [
                'id' => $id,
                'nume' => $numeCalendare[$id],
            ];
        }

        $angajati = $this->ordonatorAngajati->ordoneaza(
            $this->managerEntitati
                ->getRDBRepositoryByClass(User::class)
                ->where([
                    'type' => User::TYPE_REGULAR,
                    'isActive' => true,
                ])
                ->find()
        );
        $listaAngajati = [];

        foreach ($angajati as $angajat) {
            $idCalendar = $this->sirSauNull($angajat->get('workingTimeCalendarId'));
            $nume = trim($angajat->getName() ?? '');

            $listaAngajati[] = [
                'idAngajat' => $angajat->getId(),
                'numeAngajat' => $nume !== '' ? $nume : (string) $angajat->get('userName'),
                'dataAngajare' => $this->sirSauNull($angajat->get('dataAngajare')),
                'calendarLucruId' => $idCalendar,
                'calendarLucruNume' => $idCalendar ? ($numeCalendare[$idCalendar] ?? $idCalendar) : null,
            ];
        }

        return [
            'angajati' => $listaAngajati,
            'calendareLucru' => $listaCalendare,
        ];
    }

    /**
     * @param list<object> $dateAngajati
     * @return array<string, mixed>
     */
    public function salveaza(array $dateAngajati): array
    {
        $this->managerEntitati->getTransactionManager()->run(
            fn () => $this->salveazaInTranzactie($dateAngajati)
        );

        return $this->obtine();
    }

    /** @param list<object> $dateAngajati */
    private function salveazaInTranzactie(array $dateAngajati): void
    {
        $idProcesate = [];

        foreach ($dateAngajati as $dateAngajat) {
            $idAngajat = $this->sirSauNull($dateAngajat->angajatId ?? null);
            $dataAngajare = $this->sirSauNull($dateAngajat->dataAngajare ?? null);
            $idCalendar = $this->sirSauNull($dateAngajat->calendarLucruId ?? null);

            if (
                !$idAngajat ||
                !$dataAngajare ||
                !$idCalendar ||
                isset($idProcesate[$idAngajat])
            ) {
                $this->aruncaDateInvalide();
            }

            $idProcesate[$idAngajat] = true;
            $angajat = $this->managerEntitati
                ->getRDBRepositoryByClass(User::class)
                ->where(['id' => $idAngajat])
                ->forUpdate()
                ->findOne();

            if (!$angajat instanceof User || !$angajat->isActive() || !$angajat->isRegular()) {
                throw BadRequest::createWithBody(
                    'angajatProfilInvalid',
                    Body::create()->withMessageTranslation(
                        'angajatProfilInvalid',
                        'GestionareAngajati'
                    )
                );
            }

            if (!$this->managerEntitati->getEntityById('WorkingTimeCalendar', $idCalendar)) {
                throw BadRequest::createWithBody(
                    'calendarLucruInvalid',
                    Body::create()->withMessageTranslation(
                        'calendarLucruInvalid',
                        'GestionareAngajati'
                    )
                );
            }

            if ($this->existaPrezentaAnterioara($idAngajat, $dataAngajare)) {
                throw Conflict::createWithBody(
                    'dataAngajareDupaPrezenta',
                    Body::create()->withMessageTranslation(
                        'dataAngajareDupaPrezenta',
                        'GestionareAngajati'
                    )
                );
            }

            $date = (object) [
                'dataAngajare' => $dataAngajare,
                'workingTimeCalendarId' => $idCalendar,
            ];
            $angajat->setMultiple($date);
            $this->managerValidareCampuri->process($angajat, $date);

            if (
                $angajat->isAttributeChanged('dataAngajare') ||
                $angajat->isAttributeChanged('workingTimeCalendarId')
            ) {
                $this->managerEntitati->saveEntity($angajat, [SaveOption::API => true]);
            }
        }
    }

    private function existaPrezentaAnterioara(string $idAngajat, string $dataAngajare): bool
    {
        return $this->managerEntitati
            ->getRDBRepository('Prezenta')
            ->where([
                'assignedUserId' => $idAngajat,
                'dataPrezenta<' => $dataAngajare,
            ])
            ->findOne() !== null;
    }

    /** @return never */
    private function aruncaDateInvalide(): void
    {
        throw BadRequest::createWithBody(
            'profiluriAngajatiInvalide',
            Body::create()->withMessageTranslation(
                'profiluriAngajatiInvalide',
                'GestionareAngajati'
            )
        );
    }

    private function sirSauNull(mixed $valoare): ?string
    {
        return is_string($valoare) && trim($valoare) !== '' ? trim($valoare) : null;
    }
}
