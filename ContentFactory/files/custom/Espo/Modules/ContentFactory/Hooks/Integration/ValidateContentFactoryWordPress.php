<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Hooks\Integration;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

final readonly class ValidateContentFactoryWordPress implements BeforeSave
{
    private const INTEGRATION = 'ContentFactoryWordPress';

    public function __construct(private Config $config)
    {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getId() !== self::INTEGRATION) {
            return;
        }

        $this->normalizeInteger($entity, 'connectTimeoutSeconds', 5, 1, 30);
        $this->normalizeInteger($entity, 'responseTimeoutSeconds', 20, 5, 60);
        $url = $entity->get('baseUrl');
        $username = $entity->get('username');
        $password = $entity->get('applicationPassword');

        if ($entity->get('enabled') === true && (
            !is_string($url) || trim($url) === '' ||
            !is_string($username) || trim($username) === '' ||
            !is_string($password) || trim($password) === ''
        )) {
            throw new BadRequest('WordPress URL, username and application password are required.');
        }

        if ($url !== null && $url !== '' && (!is_string($url) || !$this->isValidUrl($url))) {
            throw new BadRequest('WordPress URL is invalid.');
        }

        if ($username !== null && $username !== '' && (
            !is_string($username) || $username !== trim($username) || mb_strlen($username) > 150 ||
            preg_match('/[\r\n]/', $username) === 1
        )) {
            throw new BadRequest('WordPress username is invalid.');
        }

        if ($password !== null && $password !== '' && (
            !is_string($password) || mb_strlen($password) > 255 || preg_match('/[\r\n]/', $password) === 1
        )) {
            throw new BadRequest('WordPress application password is invalid.');
        }
    }

    private function normalizeInteger(Entity $entity, string $field, int $default, int $minimum, int $maximum): void
    {
        $value = $entity->get($field);

        if ($value === null || $value === '') {
            $entity->set($field, $default);
            return;
        }

        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new BadRequest("Content Factory $field is invalid.");
        }
    }

    private function isValidUrl(string $url): bool
    {
        if ($url !== trim($url) || preg_match('/\s/u', $url) === 1 || strlen($url) > 2048 ||
            filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || !is_string($parts['host'] ?? null) || isset($parts['user']) ||
            isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return $scheme === 'https' || ($scheme === 'http' && (bool) $this->config->get('isDeveloperMode'));
    }
}
