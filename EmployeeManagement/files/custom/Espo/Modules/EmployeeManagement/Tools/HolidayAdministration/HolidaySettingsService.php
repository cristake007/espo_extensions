<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayAdministration;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\User;
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
        private \Espo\Modules\EmployeeManagement\Tools\Administration\AdministrationAccessChecker $accessChecker,
        private Config $config,
        private ConfigWriter $configWriter,
        private User $user,
        private HolidayApproverProvider $approvers,
    ) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        $this->accessChecker->assertScope('EmployeeHolidaySettings');

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
            'holidayTestDataResetAllowed' => $this->accessChecker->canResetHolidayData(),
            'isAdmin' => $this->user->isAdmin(),
        ];
    }

    /** @return array<string, mixed> */
    public function update(stdClass $data): array
    {
        $this->accessChecker->assertScope('EmployeeHolidaySettings');
        $this->accessChecker->assertKeys($data, [...self::STRING_FIELDS, 'employeeHolidayAnnualEntitlementDays', 'employeeHolidayResetDate', 'employeeHolidayAutomaticAnnualGrantEnabled', 'employeeHolidayCarryOverLimitDays', 'employeeHolidayNegativeBalanceLimitDays']);
        $annualEntitlement = $data->employeeHolidayAnnualEntitlementDays ?? null;
        if (!is_string($data->employeeHolidayResetDate ?? null)) { throw new BadRequest('Invalid reset date type.'); }
        $resetDate = trim($data->employeeHolidayResetDate);
        $automaticGrant = $data->employeeHolidayAutomaticAnnualGrantEnabled ?? null;
        $carryOverLimit = $data->employeeHolidayCarryOverLimitDays ?? null;
        $negativeLimit = $data->employeeHolidayNegativeBalanceLimitDays ?? null;

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
        ];

        foreach (self::STRING_FIELDS as $field) {
            if (!is_string($data->{$field} ?? '')) { throw new BadRequest('Printed approval values must be strings.'); }
            $value = trim($data->{$field} ?? '');

            if (mb_strlen($value) > 150) {
                throw new BadRequest('Printed approval values cannot exceed 150 characters.');
            }

            $values[$field] = $value;
        }

        $this->configWriter->setMultiple($values);
        $this->configWriter->save();

        return [...$values, 'isAdmin' => $this->user->isAdmin()];
    }

    /** Read-only discovery from effective native Roles; no separately configured routing list. */
    public function approverOptions(): array
    {
        $this->accessChecker->assertScope('EmployeeHolidaySettings');
        return array_map(static fn (User $candidate): array => [
            'id' => $candidate->getId(), 'name' => $candidate->get('name'),
        ], $this->approvers->users());
    }
}
