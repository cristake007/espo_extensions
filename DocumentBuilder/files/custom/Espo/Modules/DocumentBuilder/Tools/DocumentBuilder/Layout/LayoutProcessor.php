<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\InvalidLayout;

final readonly class LayoutProcessor
{
    public function __construct(
        private LayoutParser $parser,
        private LayoutMigrator $migrator,
        private LayoutNormalizer $normalizer,
        private LayoutValidator $validator,
        private CanonicalSerializer $serializer,
    ) {}

    public function process(string $json): ProcessedLayout
    {
        $layout = $this->parser->parse($json);
        $layout = $this->migrator->migrate($layout);
        $layout = $this->normalizer->normalize($layout);
        $result = $this->validator->validate($layout);

        if (!$result->isValid()) {
            throw new InvalidLayout($result);
        }

        return new ProcessedLayout($layout, $this->serializer->serialize($layout));
    }
}
