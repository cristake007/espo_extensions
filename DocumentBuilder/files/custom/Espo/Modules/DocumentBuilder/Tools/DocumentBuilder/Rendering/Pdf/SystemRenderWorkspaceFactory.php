<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

final readonly class SystemRenderWorkspaceFactory implements RenderWorkspaceFactory
{
    public function create(): RenderWorkspace
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'document-builder-' . bin2hex(random_bytes(16));
            if (mkdir($path, 0700)) return new SystemRenderWorkspace($path);
        }

        throw new PdfRenderFailure('Could not create an isolated render workspace.');
    }
}
