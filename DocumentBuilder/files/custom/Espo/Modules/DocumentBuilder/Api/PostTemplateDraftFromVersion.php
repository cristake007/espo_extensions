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
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\DraftFromVersionRequest;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;
use JsonException;

final readonly class PostTemplateDraftFromVersion implements Action
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
            $versionId = $body->versionId ?? null;
            $changeNote = $body->changeNote ?? null;

            if (
                !is_int($expectedRevision) ||
                !is_string($versionId) ||
                (!is_string($changeNote) && $changeNote !== null)
            ) {
                throw new InvalidArgumentException('Draft-from-version payload is invalid.');
            }

            return ResponseComposer::json(
                $this->service->createDraftFromVersion(
                    $id,
                    new DraftFromVersionRequest($expectedRevision, $versionId, $changeNote),
                )->toArray(),
            );
        } catch (RevisionConflict $exception) {
            throw Conflict::createWithBody('revisionConflict', $this->encode([
                'error' => (new PublicError(ErrorCategory::RevisionConflict))->toArray(),
                'expectedRevision' => $exception->expectedRevision,
                'actualRevision' => $exception->actualRevision,
            ]));
        } catch (TemplateLifecycleConflict) {
            throw Conflict::createWithBody('lifecycleConflict', $this->encode([
                'error' => (new PublicError(ErrorCategory::LifecycleConflict))->toArray(),
            ]));
        } catch (PermissionDenied) {
            throw new Forbidden('Document Builder draft-from-version access denied.');
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
            throw new InvalidArgumentException('Draft-from-version response could not be encoded.');
        }
    }
}
