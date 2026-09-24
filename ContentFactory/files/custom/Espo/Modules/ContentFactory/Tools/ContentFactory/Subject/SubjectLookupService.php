<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Subject;

use Espo\Core\Acl;

final readonly class SubjectLookupService
{
    public function __construct(
        private Acl $acl,
        private WordPressSettingsProvider $settingsProvider,
        private WordPressCourseClient $courseClient,
    ) {}

    /** @return array{list: list<array<string, string>>, providerAvailable: bool} */
    public function search(string $type, string $query, int $limit): array
    {
        if (!$this->acl->checkScope('ContentFactoryGenerator')) {
            throw new SubjectLookupException('FORBIDDEN', 403, 'Nu aveți permisiunea de a căuta subiecte.');
        }

        $type = strtolower(trim($type));
        $query = trim($query);

        if ($type !== 'course' || mb_strlen($query) > 200 || $limit < 1 || $limit > 50) {
            throw new SubjectLookupException('INVALID_REQUEST', 400, 'Căutarea subiectului nu este validă.');
        }

        if (mb_strlen($query) < 2) {
            return ['list' => [], 'providerAvailable' => true];
        }

        return [
            'list' => $this->courseClient->search($query, $limit, $this->settingsProvider->get()),
            'providerAvailable' => true,
        ];
    }
}
