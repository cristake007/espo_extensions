<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance\Api;

use Espo\Core\Api\{Action, Request, Response, ResponseComposer};
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayRequestService;
use Espo\Modules\EmployeeManagement\Tools\Administration\AdministrationAccessChecker;

/** Native actions expose validated domain commands, never unrestricted ORM setters. */
final class Holiday implements Action
{
    public function __construct(private HolidayRequestService $service, private AdministrationAccessChecker $keys) {}

    public function process(Request $request): Response
    {
        try { return $this->execute($request); }
        catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) { throw new \Espo\Core\Exceptions\Conflict('Holiday accounting key conflict. Reload and retry the same operation.'); }
            throw $e;
        }
    }

    private function execute(Request $request): Response
    {
        $this->service->assertActor();
        $operation = $request->getRouteParam('operation');
        if (strtoupper($request->getMethod()) === 'GET') {
            return ResponseComposer::json(match ($operation) {
                'balance' => $this->service->getMyBalance(),
                'requests' => $this->service->listRequests(),
                'queue' => $this->service->listRequests(true),
                'approved' => $this->service->listApprovedRequests(),
                'employees' => ['list' => $this->service->listManagedBookingUsers()],
                default => throw new BadRequest('Unknown Holiday query.'),
            });
        }
        $data = $request->getParsedBody();
        if (!$data instanceof \stdClass) { throw new BadRequest('Expected an object.'); }
        $fields = match ($operation) {
            'calculate' => ['dateStart', 'dateEnd', 'userId', 'managed', 'requestId'],
            'create' => ['idempotencyKey', 'dateStart', 'dateEnd', 'description', 'userId', 'managed'],
            'update' => ['idempotencyKey', 'revision', 'dateStart', 'dateEnd', 'description'],
            'withdraw', 'reject', 'approve', 'retryApproval' => ['idempotencyKey', 'revision'],
            'cancel', 'requestCancellation' => ['idempotencyKey', 'revision', 'reason'],
            'decideCancellation' => ['idempotencyKey', 'revision', 'decision'],
            default => throw new BadRequest('Unknown Holiday command.'),
        };
        $this->keys->assertKeys($data, $fields);
        $values = get_object_vars($data);
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values) && in_array($field, ['description', 'userId', 'managed', 'requestId'], true)) { continue; }
            $value = $values[$field] ?? null;
            $valid = match ($field) {
                'revision' => is_int($value) && $value >= 1,
                'managed' => is_bool($value),
                default => is_string($value) && strlen($value) <= (in_array($field, ['description', 'reason']) ? 2000 : 190),
            };
            if (!$valid) { throw new BadRequest('Invalid Holiday parameter: ' . $field); }
            if (in_array($field, ['dateStart', 'dateEnd'], true) && !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) { throw new BadRequest('Dates must use the YYYY-MM-DD format.'); }
        }
        if (isset($values['decision']) && !in_array($values['decision'], ['Approved', 'Cancelled'], true)) { throw new BadRequest('Invalid cancellation decision.'); }
        ksort($values);
        $id = $request->getRouteParam('id');
        if ($operation === 'calculate') {
            if ($id !== null) { throw new BadRequest('Calculation does not accept a request ID in the route.'); }
            return ResponseComposer::json($this->service->calculatePreview($values));
        }
        if ($operation === 'create') {
            if ($id !== null) { throw new BadRequest('Create does not accept a request ID.'); }
            return ResponseComposer::json($this->service->create($values));
        }
        if (!is_string($id) || $id === '') { throw new BadRequest('Request ID required.'); }
        return ResponseComposer::json($this->service->command($id, $operation, $values));
    }
}
