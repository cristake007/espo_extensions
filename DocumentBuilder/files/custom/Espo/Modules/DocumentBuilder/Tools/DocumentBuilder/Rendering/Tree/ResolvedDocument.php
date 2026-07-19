<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree;

final readonly class ResolvedDocument
{
    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $defaults
     * @param list<ResolvedNode> $sections
     * @param list<DocumentWarning> $warnings
     * @param list<ResolvedNode> $header
     * @param list<ResolvedNode> $footer
     * @param array<string, mixed> $chrome
     */
    public function __construct(
        public array $page,
        public array $defaults,
        public array $sections,
        public array $warnings = [],
        public array $header = [],
        public array $footer = [],
        public array $chrome = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'defaults' => $this->defaults,
            'sections' => array_map(static fn (ResolvedNode $node): array => $node->toArray(), $this->sections),
            'header' => array_map(static fn (ResolvedNode $node): array => $node->toArray(), $this->header),
            'footer' => array_map(static fn (ResolvedNode $node): array => $node->toArray(), $this->footer),
            'chrome' => $this->chrome,
            'warnings' => array_map(static fn (DocumentWarning $warning): array => $warning->toArray(), $this->warnings),
        ];
    }

    public function canonicalJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
