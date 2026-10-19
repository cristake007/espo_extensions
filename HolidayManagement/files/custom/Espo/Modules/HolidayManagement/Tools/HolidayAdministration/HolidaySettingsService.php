<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayAdministration;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use stdClass;

final class HolidaySettingsService
{
    private const STRING_FIELDS = [
        'holidayManagementApprovalBlock1Title',
        'holidayManagementApprovalBlock1Name',
        'holidayManagementApprovalBlock2Title',
        'holidayManagementApprovalBlock2Name',
    ];

    public function __construct(
        private HolidayManagementAccessChecker $accessChecker,
        private Config $config,
        private ConfigWriter $configWriter,
        private EntityManager $entityManager,
        private User $user,
    ) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        $this->accessChecker->assertSettings();

        return [
            'holidayManagementAnnualEntitlementDays' =>
                $this->config->get('holidayManagementAnnualEntitlementDays'),
            'holidayManagementResetDate' =>
                $this->config->get('holidayManagementResetDate'),
            'holidayManagementAutomaticAnnualGrantEnabled' => (bool) $this->config->get(
                'holidayManagementAutomaticAnnualGrantEnabled',
                false,
            ),
            'holidayManagementCarryOverLimitDays' => (int) $this->config->get(
                'holidayManagementCarryOverLimitDays',
                90,
            ),
            'holidayManagementNegativeBalanceLimitDays' => (int) $this->config->get(
                'holidayManagementNegativeBalanceLimitDays',
                -21,
            ),
            'holidayManagementApproversIds' => $this->approverIds(),
            'holidayManagementApproversNames' =>
                $this->config->get('holidayManagementApproversNames') ?? (object) [],
            'holidayManagementApprovalBlock1Title' => (string) $this->config->get(
                'holidayManagementApprovalBlock1Title',
                '',
            ),
            'holidayManagementApprovalBlock1Name' => (string) $this->config->get(
                'holidayManagementApprovalBlock1Name',
                '',
            ),
            'holidayManagementApprovalBlock2Title' => (string) $this->config->get(
                'holidayManagementApprovalBlock2Title',
                '',
            ),
            'holidayManagementApprovalBlock2Name' => (string) $this->config->get(
                'holidayManagementApprovalBlock2Name',
                '',
            ),
            'isAdmin' => $this->user->isAdmin(),
        ];
    }

    /** @return array<string, mixed> */
    public function update(stdClass $data): array
    {
        $this->accessChecker->assertSettings();
        $annualEntitlement = $data->holidayManagementAnnualEntitlementDays ?? null;
        $resetDate = trim((string) ($data->holidayManagementResetDate ?? ''));
        $automaticGrant = $data->holidayManagementAutomaticAnnualGrantEnabled ?? null;
        $carryOverLimit = $data->holidayManagementCarryOverLimitDays ?? null;
        $negativeLimit = $data->holidayManagementNegativeBalanceLimitDays ?? null;
        $approverIds = $this->validateApprovers(
            $data->holidayManagementApproversIds ?? null,
        );

        if (!is_int($annualEntitlement) || $annualEntitlement < 1) {
            throw new BadRequest('Annual entitlement must be a positive integer.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $resetDate);

        if (!$date || $date->format('Y-m-d') !== $resetDate) {
            throw new BadRequest('The reset date must use the YYYY-MM-DD format.');
        }

        if (!is_bool($automaticGrant)) {
            throw new BadRequest('The automatic annual grant option is invalid.');
        }

        if (!is_int($carryOverLimit) || $carryOverLimit < 0) {
            throw new BadRequest('The accumulated-balance warning threshold is invalid.');
        }

        if (!is_int($negativeLimit) || $negativeLimit > 0) {
            throw new BadRequest('The negative-balance limit is invalid.');
        }

        $values = [
            'holidayManagementAnnualEntitlementDays' => $annualEntitlement,
            'holidayManagementResetDate' => $resetDate,
            'holidayManagementAutomaticAnnualGrantEnabled' => $automaticGrant,
            'holidayManagementCarryOverLimitDays' => $carryOverLimit,
            'holidayManagementNegativeBalanceLimitDays' => $negativeLimit,
            'holidayManagementApproversIds' => $approverIds,
            'holidayManagementApproversNames' => $this->approverNames($approverIds),
        ];

        foreach (self::STRING_FIELDS as $field) {
            $value = trim((string) ($data->{$field} ?? ''));

            if (mb_strlen($value) > 150) {
                throw new BadRequest('Printed approval values cannot exceed 150 characters.');
            }

            $values[$field] = $value;
        }

        $this->configWriter->setMultiple($values);
        $this->configWriter->save();

        return [...$values, 'isAdmin' => $this->user->isAdmin()];
    }

    /** @return list<string> */
    private function validateApprovers(mixed $value): array
    {
        if (!is_array($value)) {
            throw new BadRequest('One or two holiday approvers are required.');
        }

        $ids = array_values(array_unique(array_filter(
            $value,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if (count($ids) < 1 || count($ids) > 2) {
            throw new BadRequest('One or two holiday approvers are required.');
        }

        $count = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'id' => $ids,
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
                'isActive' => true,
            ])
            ->count();

        if ($count !== count($ids)) {
            throw new BadRequest('Holiday approvers must be active internal users.');
        }

        return $ids;
    }

    /** @param list<string> $ids */
    private function approverNames(array $ids): stdClass
    {
        $names = new stdClass();
        $users = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where(['id' => $ids])
            ->find();

        foreach ($users as $approver) {
            $names->{(string) $approver->getId()} = (string) $approver->get('name');
        }

        return $names;
    }

    /** @return list<string> */
    private function approverIds(): array
    {
        $value = $this->config->get('holidayManagementApproversIds') ?? [];

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
