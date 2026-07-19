<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Classes\Record\Hooks\DocumentBuilderTemplate;

use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\ConfigProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutDefaults;
use Espo\ORM\Entity;
use stdClass;

final readonly class BeforeCreate implements CreateHook
{
    public function __construct(private ConfigProvider $configProvider)
    {}

    public function process(Entity $entity, CreateParams $params): void
    {
        $settings = $this->configProvider->get();
        $layout = LayoutDefaults::create(
            $settings->defaultFont(),
            $settings->defaultLocale(),
            $settings->defaultPageSize(),
        );

        $entity->set([
            'status' => 'Draft',
            'sourceType' => 'none',
            'entityType' => null,
            'spreadsheetSchema' => new stdClass(),
            'currentDraftLayout' => $layout,
            'revision' => 0,
            'draftChangeNote' => null,
            'pageSize' => $layout['document']['page']['size'],
            'orientation' => $layout['document']['page']['orientation'],
            'isActive' => true,
            'currentPublishedVersionId' => null,
        ]);
    }
}
