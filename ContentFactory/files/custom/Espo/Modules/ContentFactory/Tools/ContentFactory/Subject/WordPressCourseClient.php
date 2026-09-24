<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Subject;

use Espo\Core\Utils\Log;
use JsonException;

final readonly class WordPressCourseClient
{
    private const MAX_RESPONSE_BYTES = 2 * 1024 * 1024;

    public function __construct(private Log $log)
    {}

    /** @return list<array{type: string, id: string, label: string, slug: string, url: string, source: string}> */
    public function search(string $query, int $limit, WordPressSettings $settings): array
    {
        $parameters = http_build_query([
            'search' => $query,
            'per_page' => $limit,
            'status' => 'publish',
            '_fields' => 'id,title,slug,link,acf',
        ], '', '&', PHP_QUERY_RFC3986);
        $url = $settings->baseUrl . '/wp-json/wp/v2/cursuri?' . $parameters;
        $curlResolve = $this->curlResolve($settings->baseUrl);
        $startedAt = microtime(true);

        $this->log->info('ContentFactory WordPress course search started.', [
            'host' => (string) parse_url($settings->baseUrl, PHP_URL_HOST),
            'queryLength' => mb_strlen($query),
            'limit' => $limit,
        ]);

        $handle = curl_init();

        if ($handle === false) {
            $this->unavailable('curl_init');
        }

        $body = '';
        $tooLarge = false;
        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERNAME => $settings->username,
            CURLOPT_PASSWORD => $settings->applicationPassword,
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'User-Agent: insomnia/11.0.2',
                'Origin: ' . $settings->baseUrl,
                'Referer: ' . $settings->baseUrl . '/',
            ],
            CURLOPT_CONNECTTIMEOUT => $settings->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $settings->responseTimeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$curlResolve],
            CURLOPT_NOSIGNAL => true,
            CURLOPT_WRITEFUNCTION => static function (\CurlHandle $unused, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    $tooLarge = true;
                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
        $errorCode = curl_errno($handle);
        curl_close($handle);

        $this->log->info('ContentFactory WordPress course search completed.', [
            'elapsedMilliseconds' => (int) round((microtime(true) - $startedAt) * 1000),
            'httpStatus' => $status,
            'responseBytes' => strlen($body),
            'curlErrorCode' => $errorCode,
        ]);

        if ($success === false || $tooLarge || $status < 200 || $status >= 300 ||
            !str_starts_with($contentType, 'application/json')) {
            $this->unavailable($tooLarge ? 'response_too_large' : 'http_request', $status, $errorCode);
        }

        try {
            $records = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->unavailable('invalid_json', $status);
        }

        if (!is_array($records) || !array_is_list($records)) {
            $this->unavailable('invalid_payload', $status);
        }

        $list = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $id = $record['id'] ?? null;
            $title = is_array($record['title'] ?? null) ? ($record['title']['rendered'] ?? null) : null;
            $slug = $record['slug'] ?? null;
            $link = $record['link'] ?? null;

            if (!is_int($id) || $id <= 0 || !is_string($title) || !is_string($slug) || !is_string($link)) {
                continue;
            }

            $label = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($label === '' || filter_var($link, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $list[] = [
                'type' => 'course',
                'id' => (string) $id,
                'label' => $label,
                'slug' => $slug,
                'url' => $link,
                'source' => 'wordpress',
            ];
        }

        return $list;
    }

    private function curlResolve(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    $address = $record['ip'] ?? $record['ipv6'] ?? null;

                    if (is_string($address)) {
                        $addresses[] = $address;
                    }
                }
            }
        }

        if ($addresses === []) {
            $this->unavailable('host_resolution');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                $this->unavailable('prohibited_destination');
            }
        }

        $address = $addresses[0];
        $curlHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $curlAddress = str_contains($address, ':') ? '[' . $address . ']' : $address;

        return $curlHost . ':' . $port . ':' . $curlAddress;
    }

    private function unavailable(string $reason, ?int $status = null, ?int $curlErrorCode = null): never
    {
        $this->log->warning('ContentFactory WordPress course search failed.', [
            'reason' => $reason,
            'httpStatus' => $status,
            'curlErrorCode' => $curlErrorCode,
        ]);

        throw new SubjectLookupException(
            'WORDPRESS_UNAVAILABLE',
            502,
            'Lista de cursuri nu a putut fi încărcată.',
        );
    }
}
