<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionAccessPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionPermission;
use Espo\ORM\Entity;

final readonly class AclPublicationRecordAccess implements PublicationRecordAccess
{
    public function __construct(private ActionAccessPolicy $policy)
    {}

    public function requirePublish(Entity $template): void
    {
        $this->policy->requireRecordEdit(ActionPermission::PublishTemplates, $template);
    }
}
