<?php

declare(strict_types=1);

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use Closure;
use RuntimeException;
use Throwable;

class WordPressUrlException extends RuntimeException
{
    public function __construct(private string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

class WordPressUrlGuard
{
    private const HOSTNAME_PATTERN = '/^(?=.{1,253}\.?$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.?$/i';

    private const BLOCKED_METADATA_HOSTS = [
        'metadata.google.internal',
        'metadata.azure.internal',
        'instance-data.ec2.internal',
    ];

    private Closure $resolver;

    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host, int $port): array {
            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                return [$host];
            }

            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if ($records === false) {
                return [];
            }

            $addresses = [];

            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;

                if (is_string($address) && !in_array($address, $addresses, true)) {
                    $addresses[] = $address;
                }
            }

            return $addresses;
        };
    }

    public function normalize(string $url): string
    {
        return $this->normalizeUrl($url, false);
    }

    private function normalizeUrl(string $url, bool $allowQuery): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            throw new WordPressUrlException('invalid_url', 'The WordPress URL is invalid.');
        }

        try {
            $parts = parse_url($url);
        } catch (Throwable $exception) {
            throw new WordPressUrlException('invalid_url', 'The WordPress URL is invalid.');
        }

        if (!is_array($parts)) {
            throw new WordPressUrlException('invalid_url', 'The WordPress URL is invalid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new WordPressUrlException('invalid_url', 'The WordPress URL must use HTTP or HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || array_key_exists('fragment', $parts) ||
            (!$allowQuery && array_key_exists('query', $parts))) {
            throw new WordPressUrlException('invalid_url', 'The WordPress URL contains unsupported components.');
        }

        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));

        if ($host === '' || !$this->isValidHost($host)) {
            throw new WordPressUrlException('invalid_url', 'The WordPress URL contains an invalid hostname.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') ||
            in_array($host, self::BLOCKED_METADATA_HOSTS, true)) {
            throw new WordPressUrlException('prohibited_destination', 'The WordPress URL points to a prohibited destination.');
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;
        $port = $parts['port'] ?? $defaultPort;

        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new WordPressUrlException('invalid_url', 'The WordPress URL contains an invalid port.');
        }

        $netloc = str_contains($host, ':') ? '[' . $host . ']' : $host;

        if ($port !== $defaultPort) {
            $netloc .= ':' . $port;
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        $query = $allowQuery && array_key_exists('query', $parts) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $netloc . $path . $query;
    }

    /**
     * @return array{url: string, scheme: string, host: string, port: int, explicitPort: bool, pinnedAddress: string, curlResolve: string}
     */
    public function approve(string $url): array
    {
        $normalized = $this->normalizeUrl($url, true);
        $parts = parse_url($normalized);
        $scheme = (string) $parts['scheme'];
        $host = $this->normalizeHost((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        try {
            $addresses = ($this->resolver)($host, $port);
        } catch (Throwable $exception) {
            throw new WordPressUrlException('host_resolution', 'The WordPress host could not be resolved.');
        }

        if (!is_array($addresses) || $addresses === []) {
            throw new WordPressUrlException('host_resolution', 'The WordPress host could not be resolved.');
        }

        $approvedAddresses = [];

        foreach ($addresses as $address) {
            if (!is_string($address) || !$this->isGlobalAddress($address)) {
                throw new WordPressUrlException('prohibited_destination', 'The WordPress host resolved to a prohibited destination.');
            }

            if (!in_array($address, $approvedAddresses, true)) {
                $approvedAddresses[] = $address;
            }
        }

        $pinnedAddress = $approvedAddresses[0];
        $curlAddress = str_contains($pinnedAddress, ':') ? '[' . $pinnedAddress . ']' : $pinnedAddress;
        $curlHost = str_contains($host, ':') ? '[' . $host . ']' : $host;

        return [
            'url' => $normalized,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'explicitPort' => isset($parts['port']),
            'pinnedAddress' => $pinnedAddress,
            'curlResolve' => $curlHost . ':' . $port . ':' . $curlAddress,
        ];
    }

    /**
     * @param array{url: string, scheme: string, host: string, port: int, explicitPort: bool, pinnedAddress: string, curlResolve: string} $current
     * @return array{url: string, scheme: string, host: string, port: int, explicitPort: bool, pinnedAddress: string, curlResolve: string}
     */
    public function approveRedirect(array $current, string $location, bool $authenticated): array
    {
        $targetUrl = $this->resolveRedirectUrl($current['url'], $location);
        $normalized = $this->normalize($targetUrl);
        $targetParts = parse_url($normalized);
        $targetScheme = (string) $targetParts['scheme'];
        $targetHost = $this->normalizeHost((string) $targetParts['host']);
        $targetPort = isset($targetParts['port']) ? (int) $targetParts['port'] : ($targetScheme === 'https' ? 443 : 80);

        if ($authenticated) {
            if ($targetHost !== $current['host']) {
                throw new WordPressUrlException('unsafe_redirect', 'WordPress returned an unsafe authenticated redirect.');
            }

            if ($current['scheme'] === 'https' && $targetScheme !== 'https') {
                throw new WordPressUrlException('unsafe_redirect', 'WordPress returned an unsafe authenticated redirect.');
            }

            $defaultUpgrade = $current['scheme'] === 'http' && $current['port'] === 80 &&
                $targetScheme === 'https' && $targetPort === 443;

            if ($targetPort !== $current['port'] && !$defaultUpgrade) {
                throw new WordPressUrlException('unsafe_redirect', 'WordPress returned an unsafe authenticated redirect.');
            }
        }

        return $this->approve($normalized);
    }

    private function isValidHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return preg_match(self::HOSTNAME_PATTERN, $host) === 1;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim($host, '.'));

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return $host;
    }

    private function isGlobalAddress(string $address): bool
    {
        if (str_contains($address, '%')) {
            return false;
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        $location = trim($location);

        if ($location === '') {
            throw new WordPressUrlException('invalid_redirect', 'WordPress returned an invalid redirect.');
        }

        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $current = parse_url($currentUrl);

        if (!is_array($current)) {
            throw new WordPressUrlException('invalid_redirect', 'WordPress returned an invalid redirect.');
        }

        $currentHost = $this->normalizeHost((string) $current['host']);
        $origin = $current['scheme'] . '://' . (str_contains($currentHost, ':') ? '[' . $currentHost . ']' : $currentHost);

        if (isset($current['port'])) {
            $origin .= ':' . $current['port'];
        }

        if (str_starts_with($location, '//')) {
            return $current['scheme'] . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $origin . $this->removeDotSegments($location);
        }

        $currentPath = (string) ($current['path'] ?? '/');
        $directory = str_ends_with($currentPath, '/') ? $currentPath : dirname($currentPath) . '/';

        return $origin . $this->removeDotSegments($directory . $location);
    }

    private function removeDotSegments(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}
