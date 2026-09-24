<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Hooks\Integration;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

final readonly class ValidateContentFactoryN8n implements BeforeSave
{
    private const INTEGRATION = 'ContentFactoryN8n';

    public function __construct(private Config $config)
    {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getId() !== self::INTEGRATION) {
            return;
        }

        $this->normalizeInteger($entity, 'connectTimeoutSeconds', 5, 1, 30);
        $this->normalizeInteger($entity, 'responseTimeoutSeconds', 90, 10, 180);
        $this->validateToken($entity->get('authenticationToken'));

        $url = $entity->get('webhookUrl');

        if ($url === null || $url === '') {
            if ($entity->get('enabled') === true) {
                throw new BadRequest('Content Factory webhook URL is required.');
            }

            return;
        }

        if (!is_string($url) || !$this->isValidUrl($url)) {
            throw new BadRequest('Content Factory webhook URL is invalid.');
        }
    }

    private function normalizeInteger(
        Entity $entity,
        string $field,
        int $default,
        int $minimum,
        int $maximum,
    ): void {
        $value = $entity->get($field);

        if ($value === null || $value === '') {
            $entity->set($field, $default);
            return;
        }

        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new BadRequest("Content Factory $field is invalid.");
        }
    }

    private function validateToken(mixed $token): void
    {
        if ($token === null || $token === '') {
            return;
        }

        if (
            !is_string($token) ||
            $token !== trim($token) ||
            strlen($token) > 255 ||
            preg_match('/[\r\n]/', $token) === 1
        ) {
            throw new BadRequest('Content Factory authentication token is invalid.');
        }
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
}
