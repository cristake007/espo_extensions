<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

final readonly class EntitySourcePolicyRules implements EntitySourcePolicy
{
    private const INTERNAL_ENTITY_TYPE_LIST = [
        'ActionHistoryRecord',
        'Attachment',
        'AuditRecord',
        'AuthLogRecord',
        'AuthToken',
        'ApiKey',
        'DashboardTemplate',
        'DocumentBuilderTemplate',
        'DocumentBuilderTemplateVersion',
        'Email',
        'EmailAccount',
        'EmailFilter',
        'EmailFolder',
        'EmailTemplate',
        'EmailTemplateCategory',
        'Import',
        'ImportError',
        'InboundEmail',
        'Job',
        'LayoutSet',
        'MassEmail',
        'NextNumber',
        'Note',
        'Notification',
        'Portal',
        'Preferences',
        'Role',
        'Reminder',
        'ScheduledJob',
        'ScheduledJobLogRecord',
        'SystemData',
        'Team',
        'UniqueId',
        'User',
        'Webhook',
        'WebhookEventQueueItem',
    ];

    /**
     * @param list<string> $enabled
     * @param list<string> $disabled
     */
    public function __construct(
        private array $enabled,
        private array $disabled,
    ) {}

    public function allows(string $entityType): bool
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9]{0,99}\z/D', $entityType) !== 1) {
            return false;
        }

        if (
            in_array($entityType, self::INTERNAL_ENTITY_TYPE_LIST, true) ||
            in_array($entityType, $this->disabled, true)
        ) {
            return false;
        }

        return $this->enabled === [] || in_array($entityType, $this->enabled, true);
    }
}
