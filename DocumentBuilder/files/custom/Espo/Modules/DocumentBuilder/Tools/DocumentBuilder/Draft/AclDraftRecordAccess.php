<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionAccessPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionPermission;
use Espo\ORM\Entity;

final readonly class AclDraftRecordAccess implements DraftRecordAccess
{
    public function __construct(private ActionAccessPolicy $policy)
    {}

    public function requireEdit(Entity $template): void
    {
        $this->policy->requireRecordEdit(ActionPermission::DesignTemplates, $template);
    }

    public function requireSpreadsheetSource(): void
    {
        $this->policy->requireAction(ActionPermission::UseSpreadsheetImports);
    }
}
