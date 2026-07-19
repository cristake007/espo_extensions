<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

interface RenderWorkspace
{
    public function path(): string;
    public function cleanup(): void;
}
