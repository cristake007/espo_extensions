<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayAdministration;

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
        'employeeHolidayApprovalBlock1Title',
        'employeeHolidayApprovalBlock1Name',
        'employeeHolidayApprovalBlock2Title',
        'employeeHolidayApprovalBlock2Name',
    ];

    public function __construct(
        private EmployeeManagementAccessChecker $accessChecker,
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
            'employeeHolidayAnnualEntitlementDays' =>
                $this->config->get('employeeHolidayAnnualEntitlementDays'),
            'employeeHolidayResetDate' =>
                $this->config->get('employeeHolidayResetDate'),
            'employeeHolidayAutomaticAnnualGrantEnabled' => (bool) $this->config->get(
                'employeeHolidayAutomaticAnnualGrantEnabled',
                false,
            ),
            'employeeHolidayCarryOverLimitDays' => (int) $this->config->get(
                'employeeHolidayCarryOverLimitDays',
                90,
            ),
            'employeeHolidayNegativeBalanceLimitDays' => (int) $this->config->get(
                'employeeHolidayNegativeBalanceLimitDays',
                -21,
            ),
            'employeeHolidayApproversIds' => $this->approverIds(),
            'employeeHolidayApproversNames' =>
                $this->config->get('employeeHolidayApproversNames') ?? (object) [],
            'employeeHolidayApprovalBlock1Title' => (string) $this->config->get(
                'employeeHolidayApprovalBlock1Title',
                '',
            ),
            'employeeHolidayApprovalBlock1Name' => (string) $this->config->get(
                'employeeHolidayApprovalBlock1Name',
                '',
            ),
            'employeeHolidayApprovalBlock2Title' => (string) $this->config->get(
                'employeeHolidayApprovalBlock2Title',
                '',
            ),
            'employeeHolidayApprovalBlock2Name' => (string) $this->config->get(
                'employeeHolidayApprovalBlock2Name',
                '',
            ),
            'isAdmin' => $this->user->isAdmin(),
        ];
    }

    /** @return array<string, mixed> */
    public function update(stdClass $data): array
    {
        $this->accessChecker->assertSettings();
        $annualEntitlement = $data->employeeHolidayAnnualEntitlementDays ?? null;
        $resetDate = trim((string) ($data->employeeHolidayResetDate ?? ''));
        $automaticGrant = $data->employeeHolidayAutomaticAnnualGrantEnabled ?? null;
        $carryOverLimit = $data->employeeHolidayCarryOverLimitDays ?? null;
        $negativeLimit = $data->employeeHolidayNegativeBalanceLimitDays ?? null;
        $approverIds = $this->validateApprovers(
            $data->employeeHolidayApproversIds ?? null,
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
            'employeeHolidayAnnualEntitlementDays' => $annualEntitlement,
            'employeeHolidayResetDate' => $resetDate,
            'employeeHolidayAutomaticAnnualGrantEnabled' => $automaticGrant,
            'employeeHolidayCarryOverLimitDays' => $carryOverLimit,
            'employeeHolidayNegativeBalanceLimitDays' => $negativeLimit,
            'employeeHolidayApproversIds' => $approverIds,
            'employeeHolidayApproversNames' => $this->approverNames($approverIds),
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
        $value = $this->config->get('employeeHolidayApproversIds') ?? [];

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
