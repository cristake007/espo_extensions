<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayDocument\Api;

use Espo\Core\Api\{Action, Request, Response, ResponseComposer};
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayDocumentVerificationService;

final class VerifyHolidayDocument implements Action
{
    public function __construct(private HolidayDocumentVerificationService $service) {}

    public function process(Request $request): Response
    {
        $body = $request->getParsedBody();
        $data = get_object_vars($body);
        $allowed = ['code', 'fileName', 'fileContents'];

        if (array_diff(array_keys($data), $allowed) || !is_string($data['code'] ?? null)) {
            throw new BadRequest('Parametri de verificare invalizi.');
        }

        foreach (['fileName', 'fileContents'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                throw new BadRequest('Parametri de fișier invalizi.');
            }
        }

        return ResponseComposer::json($this->service->verify($data));
    }
}
