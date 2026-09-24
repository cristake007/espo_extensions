<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Subject;

use Espo\Core\Utils\Config;
use Espo\Entities\Integration;
use Espo\ORM\EntityManager;

final readonly class WordPressSettingsProvider
{
    public const INTEGRATION = 'ContentFactoryWordPress';

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
    ) {}

    public function get(): WordPressSettings
    {
        $entity = $this->entityManager
            ->getRDBRepositoryByClass(Integration::class)
            ->getById(self::INTEGRATION);

        if (!$entity || $entity->isNew()) {
            $this->configurationError();
        }

        $baseUrl = $this->string($entity->get('baseUrl'));
        $username = $this->string($entity->get('username'));
        $password = str_replace(' ', '', $this->string($entity->get('applicationPassword')));
        $enabled = $entity->get('enabled') === true;

        if (!$enabled || $baseUrl === '' || $username === '' || $password === '') {
            $this->configurationError();
        }

        $baseUrl = rtrim($baseUrl, '/');

        if (!$this->isValidUrl($baseUrl)) {
            $this->configurationError();
        }

        return new WordPressSettings(
            true,
            $baseUrl,
            $username,
            $password,
            $this->integer($entity->get('connectTimeoutSeconds'), 5, 1, 30),
            $this->integer($entity->get('responseTimeoutSeconds'), 20, 5, 60),
        );
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function integer(mixed $value, int $default, int $minimum, int $maximum): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            $this->configurationError();
        }

        return $value;
    }

    private function isValidUrl(string $url): bool
    {
        if (strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || !is_string($parts['host'] ?? null) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return $scheme === 'https' || (
            $scheme === 'http' && (bool) $this->config->get('isDeveloperMode')
        );
    }

    private function configurationError(): never
    {
        throw new SubjectLookupException(
            'WORDPRESS_NOT_CONFIGURED',
            503,
            'Conexiunea WordPress pentru lista de cursuri nu este configurată.',
        );
    }
}
