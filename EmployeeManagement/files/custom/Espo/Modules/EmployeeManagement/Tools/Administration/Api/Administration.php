<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\Administration\Api;

use Espo\Core\Api\{Action, Request, Response, ResponseComposer};
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EmployeeManagement\Tools\Administration\AdministrationAccessChecker;
use Espo\Modules\EmployeeManagement\Tools\Attendance\AttendanceSettingsService;
use Espo\Modules\EmployeeManagement\Tools\Attendance\AttendanceDataResetService;
use Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\HolidaySettingsService;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayProfileService;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayDataResetService;

final class Administration implements Action
{
    public function __construct(private AdministrationAccessChecker $access,
        private AttendanceSettingsService $attendance, private HolidaySettingsService $holiday,
        private HolidayProfileService $profiles, private HolidayDataResetService $holidayReset,
        private AttendanceDataResetService $attendanceReset) {}

    public function process(Request $request): Response
    {
        try { return $this->execute($request); }
        catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) { throw new \Espo\Core\Exceptions\Conflict('Profile accounting key conflict. Reload and retry the same operation.'); }
            throw $e;
        }
    }

    private function execute(Request $request): Response
    {
        // Check the entry scope even for malformed or unknown operations.
        $this->access->assertScope('EmployeeAdministration');
        $section = $request->getRouteParam('section');
        $method = strtoupper($request->getMethod());
        if ($method === 'GET') {
            if ($section === 'attendance' && $request->getRouteParam('operation') === 'testDataResetPreview') {
                $this->access->assertAttendanceDataReset();
                return ResponseComposer::json($this->attendanceReset->preview());
            }
            if ($section === 'holiday' && $request->getRouteParam('operation') === 'testDataResetPreview') {
                $this->access->assertHolidayDataReset();
                return ResponseComposer::json($this->holidayReset->preview());
            }
            return ResponseComposer::json(match ($section) {
                'attendance' => $this->attendance->get(),
                'holiday' => $this->holiday->get(),
                'approvers' => ['list' => $this->holiday->approverOptions()],
                'profiles' => ['list' => $this->profiles->listProfiles()],
                default => throw new BadRequest('Unknown administration section.'),
            });
        }
        $data = $request->getParsedBody();
        if (!$data instanceof \stdClass) { throw new BadRequest('Expected an object.'); }
        if ($method === 'PUT') {
            return ResponseComposer::json(match ($section) {
                'attendance' => $this->attendance->update($data),
                'holiday' => $this->holiday->update($data),
                default => throw new BadRequest('Unknown settings section.'),
            });
        }
        if ($method === 'POST' && $section === 'profiles' && $request->getRouteParam('operation') === 'bulkInitialize') {
            $this->access->assertProfiles();
            $this->access->assertKeys($data, ['items']);
            if (!is_array($data->items ?? null) || !array_is_list($data->items)) { throw new BadRequest('Expected profile items.'); }
            foreach ($data->items as $item) {
                if (!$item instanceof \stdClass || !is_string($item->userId ?? null)) { throw new BadRequest('Invalid profile item.'); }
            }
            return ResponseComposer::json(['list' => $this->profiles->bulkInitialize($data->items)]);
        }
        if ($method === 'POST' && $section === 'profiles' && $request->getRouteParam('operation') === 'completeInitialSetup') {
            $this->access->assertProfiles();
            $this->access->assertKeys($data, ['profileId', 'openingBalance', 'idempotencyKey']);
            if (!is_string($data->profileId ?? null) || !is_string($data->idempotencyKey ?? null) || !is_numeric($data->openingBalance ?? null)) {
                throw new BadRequest('Invalid opening balance setup parameters.');
            }
            return ResponseComposer::json($this->profiles->completeInitialSetup($data->profileId, (float) $data->openingBalance, $data->idempotencyKey));
        }
        if ($method === 'POST' && $section === 'profiles' && $request->getRouteParam('operation') === 'correct') {
            $this->access->assertProfiles();
            $this->access->assertScope('EmployeeHolidayBalanceCorrection');
            $this->access->assertKeys($data, ['profileId', 'delta', 'reason', 'idempotencyKey', 'expectedBalance']);
            if (!is_string($data->profileId ?? null) || !is_string($data->reason ?? null) || strlen($data->reason) > 2000 ||
                !is_string($data->idempotencyKey ?? null) || !is_numeric($data->delta ?? null) || !is_finite((float) $data->delta)) {
                throw new BadRequest('Invalid balance correction parameters.');
            }
            if (property_exists($data, 'expectedBalance') && (!is_numeric($data->expectedBalance) || !is_finite((float) $data->expectedBalance))) { throw new BadRequest('Invalid expected balance.'); }
            return ResponseComposer::json($this->profiles->correct($data->profileId, (float) $data->delta, $data->reason, $data->idempotencyKey,
                property_exists($data, 'expectedBalance') ? (float) $data->expectedBalance : null));
        }
        if ($method === 'POST' && $section === 'profiles' && $request->getRouteParam('operation') === 'corrections') {
            $this->access->assertProfiles();
            $this->access->assertScope('EmployeeHolidayBalanceCorrection');
            $this->access->assertKeys($data, ['profileId']);
            if (!is_string($data->profileId ?? null)) { throw new BadRequest('Profile ID required.'); }
            return ResponseComposer::json(['list' => $this->profiles->listCorrections($data->profileId)]);
        }
        if ($section === 'holiday' && $request->getRouteParam('operation') === 'testDataReset') {
            $this->access->assertHolidayDataReset();
            $this->access->assertKeys($data, ['confirmation']);
            if (($data->confirmation ?? null) !== 'RESET HOLIDAY DATA') {
                throw new BadRequest('The Holiday test-data reset confirmation is invalid.');
            }
            return ResponseComposer::json($this->holidayReset->reset());
        }
        if ($section === 'attendance' && $request->getRouteParam('operation') === 'testDataReset') {
            $this->access->assertAttendanceDataReset();
            $this->access->assertKeys($data, ['confirmation']);
            if (($data->confirmation ?? null) !== 'RESET ATTENDANCE DATA') {
                throw new BadRequest('The Attendance test-data reset confirmation is invalid.');
            }
            return ResponseComposer::json($this->attendanceReset->reset());
        }
        throw new BadRequest('Operation not enabled in this stage.');
    }
}
