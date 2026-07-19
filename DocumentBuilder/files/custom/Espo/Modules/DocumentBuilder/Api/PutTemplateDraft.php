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
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftSaveRequest;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftSaveService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\RevisionConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\SourceChangeConfirmationRequired;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\TemplateNotDraft;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error\ErrorCategory;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error\PublicError;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\InvalidLayout;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\LayoutProcessingException;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ValidationError;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class PutTemplateDraft implements Action
{
    public function __construct(private DraftSaveService $service)
    {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if ($id === null) {
            throw new BadRequest('Template ID is required.');
        }

        try {
            $input = $this->readInput($request->getParsedBody());
            $result = $this->service->save($id, $input);

            return ResponseComposer::json($result->toArray());
        } catch (RevisionConflict $exception) {
            throw Conflict::createWithBody('revisionConflict', $this->encode([
                'error' => (new PublicError(ErrorCategory::RevisionConflict))->toArray(),
                'expectedRevision' => $exception->expectedRevision,
                'actualRevision' => $exception->actualRevision,
            ]));
        } catch (SourceChangeConfirmationRequired $exception) {
            throw Conflict::createWithBody('sourceChangeConfirmation', $this->encode([
                'error' => (new PublicError(ErrorCategory::SourceChangeConfirmation))->toArray(),
                'requiresConfirmation' => true,
                'impact' => $exception->impactReport->toArray(),
            ]));
        } catch (PermissionDenied) {
            throw new Forbidden('Document Builder draft access denied.');
        } catch (InvalidLayout $exception) {
            throw BadRequest::createWithBody('validation', $this->encode([
                'error' => (new PublicError(ErrorCategory::Validation))->toArray(),
                'validationErrors' => array_map(
                    static fn (ValidationError $error): array => $error->toArray(),
                    $exception->result()->errors(),
                ),
            ]));
        } catch (LayoutProcessingException|TemplateNotDraft|InvalidArgumentException $exception) {
            throw BadRequest::createWithBody('validation', $this->encode([
                'error' => (new PublicError(ErrorCategory::Validation))->toArray(),
            ]));
        }
    }

    private function readInput(stdClass $body): DraftSaveRequest
    {
        $layout = $body->layout ?? null;

        if ($layout instanceof stdClass) {
            $layout = $this->encode($layout);
        }

        $expectedRevision = $body->expectedRevision ?? null;
        $changeNote = $body->changeNote ?? null;
        $confirmSourceChange = $body->confirmSourceChange ?? false;

        if (
            !is_string($layout) ||
            !is_int($expectedRevision) ||
            (!is_string($changeNote) && $changeNote !== null) ||
            !is_bool($confirmSourceChange)
        ) {
            throw new InvalidArgumentException('Draft save payload is invalid.');
        }

        return new DraftSaveRequest(
            $layout,
            $expectedRevision,
            $confirmSourceChange,
            $changeNote,
        );
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('Draft data could not be encoded.');
        }
    }
}
