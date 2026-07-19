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
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\InvalidLayout;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\LayoutProcessingException;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ValidationError;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationRequest;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationValidationException;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class PostTemplatePublish implements Action
{
    public function __construct(private PublicationService $service)
    {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if ($id === null) {
            throw new BadRequest('Template ID is required.');
        }

        try {
            $result = $this->service->publish($id, $this->readInput($request->getParsedBody()));

            return ResponseComposer::json($result->toArray());
        } catch (RevisionConflict $exception) {
            throw Conflict::createWithBody('revisionConflict', $this->encode([
                'error' => (new PublicError(ErrorCategory::RevisionConflict))->toArray(),
                'expectedRevision' => $exception->expectedRevision,
                'actualRevision' => $exception->actualRevision,
            ]));
        } catch (PublicationConflict) {
            throw Conflict::createWithBody('publicationConflict', $this->encode([
                'error' => (new PublicError(ErrorCategory::PublicationConflict))->toArray(),
            ]));
        } catch (PermissionDenied) {
            throw new Forbidden('Document Builder publication access denied.');
        } catch (PublicationValidationException $exception) {
            throw BadRequest::createWithBody('validation', $this->encode([
                'error' => (new PublicError(ErrorCategory::Validation))->toArray(),
                'publicationBlocker' => $exception->toArray(),
            ]));
        } catch (InvalidLayout $exception) {
            throw BadRequest::createWithBody('validation', $this->encode([
                'error' => (new PublicError(ErrorCategory::Validation))->toArray(),
                'validationErrors' => array_map(
                    static fn (ValidationError $error): array => $error->toArray(),
                    $exception->result()->errors(),
                ),
            ]));
        } catch (LayoutProcessingException|InvalidArgumentException) {
            throw BadRequest::createWithBody('validation', $this->encode([
                'error' => (new PublicError(ErrorCategory::Validation))->toArray(),
            ]));
        }
    }

    private function readInput(stdClass $body): PublicationRequest
    {
        $expectedRevision = $body->expectedRevision ?? null;
        $changeNote = $body->changeNote ?? null;

        if (!is_int($expectedRevision) || (!is_string($changeNote) && $changeNote !== null)) {
            throw new InvalidArgumentException('Publication payload is invalid.');
        }

        return new PublicationRequest($expectedRevision, $changeNote);
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('Publication data could not be encoded.');
        }
    }
}
