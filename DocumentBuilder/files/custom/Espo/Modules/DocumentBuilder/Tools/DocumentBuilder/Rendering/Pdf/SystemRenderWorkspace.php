<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class SystemRenderWorkspace implements RenderWorkspace
{
    private bool $cleaned = false;

    public function __construct(private readonly string $directory)
    {}

    public function path(): string
    {
        return $this->directory;
    }

    public function cleanup(): void
    {
        if ($this->cleaned || !is_dir($this->directory)) return;
        $root = realpath($this->directory);
        $base = realpath(sys_get_temp_dir());
        if ($root === false || $base === false || !str_starts_with($root, $base . DIRECTORY_SEPARATOR . 'document-builder-')) {
            throw new PdfRenderFailure('The render workspace cleanup target is invalid.');
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
        $this->cleaned = true;
    }
}
