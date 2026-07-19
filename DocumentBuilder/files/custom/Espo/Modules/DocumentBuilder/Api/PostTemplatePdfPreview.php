<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PdfPreviewService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewMode;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRateLimitExceeded;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRequest;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRevisionConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewTemplateNotFound;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\PdfRenderFailure;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;
use stdClass;

final readonly class PostTemplatePdfPreview implements Action
{
    public function __construct(private PdfPreviewService $service)
    {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        if ($id === null) throw new BadRequest('Template ID is required.');
        try {
            $body = $request->getParsedBody();
            $result = $this->service->preview($id, $this->input($body));

            if (($body->response ?? null) === 'base64') {
                return ResponseComposer::json([
                    'content' => base64_encode($result->bytes),
                    'mediaType' => 'application/pdf',
                    'pageCount' => $result->pageCount,
                    'warningCount' => count($result->warnings),
                ]);
            }

            return ResponseComposer::empty()
                ->writeBody($result->bytes)
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="document-preview.pdf"')
                ->setHeader('Content-Length', (string) strlen($result->bytes))
                ->setHeader('Cache-Control', 'no-store, private')
                ->setHeader('X-Document-Builder-Pages', (string) $result->pageCount)
                ->setHeader('X-Document-Builder-Warnings', (string) count($result->warnings));
        } catch (PreviewRevisionConflict $exception) {
            throw Conflict::createWithBody('previewRevisionConflict', json_encode([
                'expectedRevision'=>$exception->expectedRevision,'actualRevision'=>$exception->actualRevision,
            ], JSON_THROW_ON_ERROR));
        } catch (PreviewRateLimitExceeded) {
            throw Conflict::createWithBody('previewRateLimit', json_encode(['retry'=>true], JSON_THROW_ON_ERROR));
        } catch (PreviewTemplateNotFound) {
            throw new NotFound('Document Builder template not found.');
        } catch (PermissionDenied) {
            throw new Forbidden('Document Builder PDF preview access denied.');
        } catch (PdfRenderFailure $exception) {
            throw new Error('Document Builder PDF rendering failed.', previous: $exception);
        } catch (InvalidArgumentException) {
            throw new BadRequest('Document Builder PDF preview request is invalid.');
        }
    }

    private function input(stdClass $body): PreviewRequest
    {
        $values = get_object_vars($body);
        if (array_diff(array_keys($values), ['expectedRevision','mode','recordId','response']) !== []) {
            throw new InvalidArgumentException('PDF preview payload contains unsupported properties.');
        }
        $revision = $body->expectedRevision ?? null;
        $mode = is_string($body->mode ?? null) ? PreviewMode::tryFrom($body->mode) : null;
        $recordId = $body->recordId ?? null;
        $response = $body->response ?? null;
        if (
            !is_int($revision) ||
            $mode === null ||
            (!is_string($recordId) && $recordId !== null) ||
            ($response !== null && $response !== 'base64')
        ) {
            throw new InvalidArgumentException('PDF preview payload is invalid.');
        }
        return new PreviewRequest($revision, $mode, $recordId);
    }
}
