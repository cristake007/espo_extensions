<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree;

final readonly class DocumentWarning
{
    public function __construct(
        public string $code,
        public string $path,
        public string $nodeId,
    ) {}

    /** @return array{code: string, path: string, nodeId: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'path' => $this->path, 'nodeId' => $this->nodeId];
    }
}
