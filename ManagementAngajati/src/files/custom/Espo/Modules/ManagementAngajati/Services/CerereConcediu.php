<?php

namespace Espo\Modules\ManagementAngajati\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Name\Field;
use Espo\Entities\Preferences;
use Espo\ORM\Query\Select;
use Espo\Services\Record;
use RuntimeException;

/**
 * Adaptor nativ pentru filtrul Cereri de concediu din Calendar.
 *
 * @extends Record<\Espo\ORM\Entity>
 */
class CerereConcediu extends Record
{
    /** Numele metodei este stabilit astfel de extensia Calendar din EspoCRM. */
    public function getCalenderQuery(
        string $userId,
        string $from,
        string $to,
        bool $skipAcl = false,
    ): Select {
        [$dataInceput, $dataSfarsit] = $this->limiteDate($from, $to);
        $constructor = $this->selectBuilderFactory
            ->create()
            ->from($this->entityType)
            ->withStrictAccessControl();
        $selectie = [
            ['"CerereConcediu"', 'scope'],
            'id',
            ['assignedUserName', 'name'],
            ['null', 'dateStart'],
            ['null', 'dateEnd'],
            ['stare', 'status'],
            ['dataInceput', 'dateStartDate'],
            ['dataSfarsit', 'dateEndDate'],
            ['null', 'parentType'],
            ['null', 'parentId'],
            Field::CREATED_AT,
        ];

        foreach ($this->metadata->get(['app', 'calendar', 'additionalAttributeList']) ?? [] as $atribut) {
            $selectie[] = ['null', $atribut];
        }

        try {
            $interogare = $constructor->buildQueryBuilder();
            $poateVedeaAltCalendar = $this->user->isAdmin() ||
                $this->acl->checkScope('GestionareAngajati');
            $campurileSuntVizibile = $this->acl->checkField($this->entityType, 'name') &&
                $this->acl->checkField($this->entityType, 'dataInceput') &&
                $this->acl->checkField($this->entityType, 'dataSfarsit') &&
                $this->acl->checkField($this->entityType, 'stare');

            if ((!$this->user->isRegular() && !$this->user->isAdmin()) ||
                !$this->user->isActive() || !$campurileSuntVizibile ||
                ($userId !== $this->user->getId() && !$poateVedeaAltCalendar) ||
                !$this->acl->checkScope($this->entityType, Table::ACTION_READ)) {
                $interogare->where(['id' => null]);
            }

            return $interogare
                ->select($selectie)
                ->where([
                    'dataInceput<' => $dataSfarsit,
                    'dataSfarsit>=' => $dataInceput,
                    'stare' => 'Aprobata',
                    'assignedUserId' => $userId,
                ])
                ->build();
        } catch (BadRequest | Forbidden $exceptie) {
            throw new RuntimeException($exceptie->getMessage(), 0, $exceptie);
        }
    }

    /** @return array{string, string} */
    private function limiteDate(string $from, string $to): array
    {
        $preferinte = $this->entityManager->getRepositoryByClass(Preferences::class)
            ->getById($this->user->getId());
        $zona = new DateTimeZone(
            $preferinte?->getTimeZone() ?: (string) ($this->config->get('timeZone') ?? 'UTC')
        );
        $utc = new DateTimeZone('UTC');
        $inceput = (new DateTimeImmutable($from, $utc))->setTimezone($zona);
        $sfarsit = (new DateTimeImmutable($to, $utc))->setTimezone($zona);

        if ($sfarsit->format('H:i:s') !== '00:00:00') {
            $sfarsit = $sfarsit->modify('+1 day');
        }

        return [$inceput->format('Y-m-d'), $sfarsit->format('Y-m-d')];
    }
}
