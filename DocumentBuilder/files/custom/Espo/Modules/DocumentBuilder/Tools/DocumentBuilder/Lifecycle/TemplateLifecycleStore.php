<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

use Espo\ORM\Entity;

interface TemplateLifecycleStore
{
    /** @param callable(Entity, list<string>): TemplateDuplicateData $duplicator */
    public function duplicateLocked(string $templateId, callable $duplicator): TemplateLifecycleResult;

    /** @param callable(Entity): TemplateLifecycleResult $updater */
    public function updateLocked(string $templateId, callable $updater): TemplateLifecycleResult;

    /**
     * @param callable(Entity): void $templateAuthorizer
     * @param callable(Entity, Entity): TemplateLifecycleResult $restorer
     */
    public function restoreLocked(
        string $templateId,
        string $versionId,
        callable $templateAuthorizer,
        callable $restorer,
    ): TemplateLifecycleResult;
}
