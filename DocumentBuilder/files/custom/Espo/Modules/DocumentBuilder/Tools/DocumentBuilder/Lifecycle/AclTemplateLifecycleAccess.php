<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionAccessPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionPermission;
use Espo\ORM\Entity;

final readonly class AclTemplateLifecycleAccess implements TemplateLifecycleAccess
{
    private const TEMPLATE = 'DocumentBuilderTemplate';

    public function __construct(private ActionAccessPolicy $policy)
    {}

    public function requireDuplicate(Entity $template): void
    {
        $this->policy->requireRecordRead(ActionPermission::DesignTemplates, $template);
        $this->policy->requireScopeCreate(ActionPermission::DesignTemplates, self::TEMPLATE);
    }

    public function requireArchive(Entity $template): void
    {
        $this->policy->requireRecordEdit(ActionPermission::PublishTemplates, $template);
    }

    public function requireDraftFromVersionTemplate(Entity $template): void
    {
        $this->policy->requireRecordEdit(ActionPermission::DesignTemplates, $template);
    }

    public function requireVersionRead(Entity $version): void
    {
        $this->policy->requireRecordRead(ActionPermission::DesignTemplates, $version);
    }
}
