<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\ConfigProvider;

final readonly class ConfiguredRelationshipDepthLimit implements RelationshipDepthLimit
{
    public function __construct(private ConfigProvider $configProvider)
    {}

    public function get(): int
    {
        return $this->configProvider->get()->maxRelationshipDepth();
    }
}
