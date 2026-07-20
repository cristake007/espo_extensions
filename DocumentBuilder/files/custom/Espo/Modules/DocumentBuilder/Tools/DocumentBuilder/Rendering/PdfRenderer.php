<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\SettingsProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\PdfEngineFactory;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\PdfRenderFailure;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\PdfRenderResult;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\RenderWorkspace;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf\RenderWorkspaceFactory;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;
use Throwable;

final readonly class PdfRenderer
{
    private const PAGE_COUNT_RENDER_LIMIT = 4;

    public function __construct(
        private PdfEngineFactory $engines,
        private RenderWorkspaceFactory $workspaces,
        private SettingsProvider $settingsProvider,
    ) {}

    public function render(ResolvedDocument $document, string $html): PdfRenderResult
    {
        $settings = $this->settingsProvider->get();
        if (strlen($html) > $settings->maxLayoutBytes() * 4) {
            throw new PdfRenderFailure('The generated HTML exceeds the render limit.');
        }
        $workspace = $this->workspaces->create();
        $started = hrtime(true);
        try {
            $result = $this->renderWithPageCount($document, $workspace, $html);
            $elapsed = (hrtime(true) - $started) / 1_000_000_000;
            $maximumBytes = max(1_048_576, $settings->renderMemoryMegabytes() * 524_288);
            if ($elapsed > $settings->renderTimeoutSeconds() || strlen($result->bytes) > $maximumBytes ||
                $result->pageCount < 1 || $result->pageCount > $settings->maxRenderedPages()) {
                throw new PdfRenderFailure('The rendered PDF exceeds configured resource limits.');
            }

            return $result;
        } catch (PdfRenderFailure $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PdfRenderFailure('The PDF engine failed.', previous: $exception);
        } finally {
            $workspace->cleanup();
        }
    }

    private function renderWithPageCount(
        ResolvedDocument $document,
        RenderWorkspace $workspace,
        string $html,
    ): PdfRenderResult {
        if (!str_contains($html, PageCountPlaceholder::TOKEN)) {
            return $this->engines->create($document, $workspace)->render($html);
        }

        $expectedPageCount = 1;

        for ($attempt = 0; $attempt < self::PAGE_COUNT_RENDER_LIMIT; $attempt++) {
            $resolvedHtml = str_replace(PageCountPlaceholder::TOKEN, (string) $expectedPageCount, $html);
            $result = $this->engines->create($document, $workspace)->render($resolvedHtml);

            if ($result->pageCount === $expectedPageCount) return $result;

            $expectedPageCount = $result->pageCount;
        }

        throw new PdfRenderFailure('The total page count did not stabilize.');
    }
}
