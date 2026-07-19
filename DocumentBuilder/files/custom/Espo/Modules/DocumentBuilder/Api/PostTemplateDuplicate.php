<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\RevisionConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error\ErrorCategory;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error\PublicError;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\LayoutProcessingException;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\DuplicateTemplateRequest;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class PostTemplateDuplicate implements Action
{
    public function __construct(private TemplateLifecycleService $service)
    {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if ($id === null) {
            throw new BadRequest('Template ID is required.');
        }

        try {
            $body = $request->getParsedBody();
            $expectedRevision = $body->expectedRevision ?? null;
            $name = $body->name ?? null;

            if (!is_int($expectedRevision) || (!is_string($name) && $name !== null)) {
                throw new InvalidArgumentException('Duplicate-template payload is invalid.');
            }

            return ResponseComposer::json(
                $this->service->duplicate(
                    $id,
                    new DuplicateTemplateRequest($expectedRevision, $name),
                )->toArray(),
            );
        } catch (RevisionConflict $exception) {
            throw Conflict::createWithBody('revisionConflict', $this->encode([
                'error' => (new PublicError(ErrorCategory::RevisionConflict))->toArray(),
                'expectedRevision' => $exception->expectedRevision,
                'actualRevision' => $exception->actualRevision,
            ]));
        } catch (PermissionDenied) {
            throw new Forbidden('Document Builder duplication access denied.');
        } catch (LayoutProcessingException|InvalidArgumentException) {
            throw BadRequest::createWithBody('validation', $this->encode([
                'error' => (new PublicError(ErrorCategory::Validation))->toArray(),
            ]));
        }
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new InvalidArgumentException('Duplicate-template response could not be encoded.');
        }
    }
}
