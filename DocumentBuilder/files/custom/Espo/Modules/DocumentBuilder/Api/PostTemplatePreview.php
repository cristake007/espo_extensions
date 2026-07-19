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
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewMode;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRateLimitExceeded;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRequest;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRevisionConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewTemplateNotFound;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class PostTemplatePreview implements Action
{
    public function __construct(private PreviewService $service)
    {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if ($id === null) {
            throw new BadRequest('Template ID is required.');
        }

        try {
            return ResponseComposer::json(
                $this->service->preview($id, $this->input($request->getParsedBody()))->toArray(),
            );
        } catch (PreviewRevisionConflict $exception) {
            throw Conflict::createWithBody('previewRevisionConflict', $this->encode([
                'expectedRevision' => $exception->expectedRevision,
                'actualRevision' => $exception->actualRevision,
            ]));
        } catch (PreviewRateLimitExceeded) {
            throw Conflict::createWithBody('previewRateLimit', $this->encode(['retry' => true]));
        } catch (PreviewTemplateNotFound) {
            throw new NotFound('Document Builder template not found.');
        } catch (PermissionDenied) {
            throw new Forbidden('Document Builder preview access denied.');
        } catch (InvalidArgumentException|JsonException) {
            throw new BadRequest('Document Builder preview request is invalid.');
        }
    }

    private function input(stdClass $body): PreviewRequest
    {
        $values = get_object_vars($body);

        if (array_diff(array_keys($values), ['expectedRevision', 'mode', 'recordId']) !== []) {
            throw new InvalidArgumentException('Preview payload contains unsupported properties.');
        }

        $revision = $body->expectedRevision ?? null;
        $mode = is_string($body->mode ?? null) ? PreviewMode::tryFrom($body->mode) : null;
        $recordId = $body->recordId ?? null;

        if (!is_int($revision) || $mode === null || (!is_string($recordId) && $recordId !== null)) {
            throw new InvalidArgumentException('Preview payload is invalid.');
        }

        return new PreviewRequest($revision, $mode, $recordId);
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
