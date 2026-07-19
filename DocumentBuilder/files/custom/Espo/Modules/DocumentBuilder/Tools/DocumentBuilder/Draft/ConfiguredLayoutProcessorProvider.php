<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\ConfigProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CanonicalSerializer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutMigrator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutNormalizer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutParser;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutProcessor;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;

final readonly class ConfiguredLayoutProcessorProvider implements LayoutProcessorProvider
{
    public function __construct(private ConfigProvider $configProvider)
    {}

    public function get(): LayoutProcessor
    {
        $settings = $this->configProvider->get();

        return new LayoutProcessor(
            new LayoutParser($settings),
            new LayoutMigrator(),
            new LayoutNormalizer($settings),
            new LayoutValidator($settings, new NodeRegistry(), CapabilityRegistry::phase08()),
            new CanonicalSerializer(),
        );
    }
}
