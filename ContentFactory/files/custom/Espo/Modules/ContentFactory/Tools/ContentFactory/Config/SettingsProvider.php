<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Config;

use Espo\Core\Utils\Config;
use Espo\Entities\Integration;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
use Espo\ORM\EntityManager;

final readonly class SettingsProvider
{
    public const INTEGRATION = 'ContentFactoryN8n';

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
    ) {}

    public function get(): Settings
    {
        $entity = $this->entityManager
            ->getRDBRepositoryByClass(Integration::class)
            ->getById(self::INTEGRATION);

        if (!$entity || $entity->isNew()) {
            $this->configurationError();
        }

        $enabled = $entity->get('enabled') === true;
        $urlValue = $entity->get('webhookUrl');
        $tokenValue = $entity->get('authenticationToken');
        $url = is_string($urlValue) ? $urlValue : '';
        $token = is_string($tokenValue) ? $tokenValue : '';
        $connectTimeout = $this->integer(
            $entity->get('connectTimeoutSeconds'),
            5,
            1,
            30,
        );
        $responseTimeout = $this->integer(
            $entity->get('responseTimeoutSeconds'),
            90,
            10,
            180,
        );

        if ($url !== '' && !$this->isValidUrl($url)) {
            $this->configurationError(true);
        }

        if (
            $token !== trim($token) ||
            strlen($token) > 255 ||
            preg_match('/[\r\n]/', $token) === 1
        ) {
            $this->configurationError(true);
        }

        return new Settings($enabled, $url, $token, $connectTimeout, $responseTimeout);
    }

    private function integer(mixed $value, int $default, int $minimum, int $maximum): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            $this->configurationError(true);
        }

        return $value;
    }

    private function isValidUrl(string $url): bool
    {
        if (
            $url !== trim($url) ||
            preg_match('/\s/u', $url) === 1 ||
            strlen($url) > 2048 ||
            filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }

        $parts = parse_url($url);

        if (
            !is_array($parts) ||
            !is_string($parts['host'] ?? null) ||
            isset($parts['user']) ||
            isset($parts['pass'])
        ) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return $scheme === 'https' || (
            $scheme === 'http' && (bool) $this->config->get('isDeveloperMode')
        );
    }

    private function configurationError(bool $invalid = false): never
    {
        throw new GenerationException(
            'CONFIGURATION_ERROR',
            503,
            $invalid
                ? 'Serviciul de generare nu este configurat corect.'
                : 'Serviciul de generare nu este configurat.',
        );
    }
}
