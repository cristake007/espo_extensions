<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Name\Field;
use Espo\ORM\Query\Select;
use Espo\Services\Record;
use RuntimeException;

/**
 * @extends Record<\Espo\Modules\HolidayManagement\Entities\HolidayRequest>
 */
final class HolidayRequest extends Record
{
    /** EspoCRM 10 calendar extension point; the spelling is defined by core. */
    public function getCalenderQuery(
        string $userId,
        string $from,
        string $to,
        bool $skipAcl = false,
    ): Select {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from($this->entityType);

        if (!$skipAcl) {
            $builder->withStrictAccessControl();
        }

        $seed = $this->entityManager->getNewEntity($this->entityType);
        $select = [
            ['"HolidayRequest"', 'scope'],
            'id',
            'name',
            ['null', 'dateStart'],
            ['null', 'dateEnd'],
            ['status', 'status'],
            ['dateStartDate', 'dateStartDate'],
            ['dateEndDate', 'dateEndDate'],
            ['null', 'parentType'],
            ['null', 'parentId'],
            Field::CREATED_AT,
        ];

        $additionalAttributeList =
            $this->metadata->get(['app', 'calendar', 'additionalAttributeList']) ?? [];
        $requiresProfileJoin = false;

        foreach ($additionalAttributeList as $attribute) {
            if ($attribute === 'color') {
                $select[] = ['profile.calendarColor', 'color'];
                $requiresProfileJoin = true;

                continue;
            }

            $select[] = $seed->hasAttribute($attribute) ?
                [$attribute, $attribute] :
                ['null', $attribute];
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();

            if ($requiresProfileJoin) {
                $queryBuilder->leftJoin('profile');
            }

            return $queryBuilder
                ->select($select)
                ->where([
                    'dateStartDate<' => substr($to, 0, 10),
                    'dateEndDate>=' => substr($from, 0, 10),
                    'OR' => [
                        ['status!=' => 'Rejected'],
                        ['status' => null],
                    ],
                ])
                ->build();
        } catch (BadRequest | Forbidden $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
