<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use JsonException;

final class NagerDateClient implements HolidayProvider
{
    private const ORIGIN = 'https://date.nager.at/api/v4';
    private const MAXIMUM_RESPONSE_BYTES = 1048576;

    public function __construct(
        private HttpTransport $transport,
        private PayloadNormalizer $normalizer,
        private HolidayFilter $filter,
    ) {}

    /**
     * @param list<string> $acceptedTypes
     * @return list<Holiday>
     * @throws ClientException
     */
    public function fetch(
        string $countryCode,
        int $year,
        array $acceptedTypes = ['Public'],
        bool $nationalOnly = true,
    ): array {
        $this->validateInput($countryCode, $year, $acceptedTypes);

        $url = self::ORIGIN . "/Holidays/$countryCode/$year";
        $response = $this->transport->get($url, self::MAXIMUM_RESPONSE_BYTES);

        if ($response->statusCode >= 300 && $response->statusCode < 400) {
            throw new ClientException(
                ClientException::REDIRECT,
                'Nager.Date redirects are not accepted.',
            );
        }

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new ClientException(
                ClientException::STATUS,
                "Nager.Date returned HTTP status {$response->statusCode}.",
            );
        }

        if (strlen($response->body) > self::MAXIMUM_RESPONSE_BYTES) {
            throw new ClientException(
                ClientException::RESPONSE_SIZE,
                'Nager.Date returned a response larger than the allowed limit.',
            );
        }

        try {
            $payload = json_decode($response->body, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ClientException(
                ClientException::JSON,
                'Nager.Date returned invalid JSON.',
                $e,
            );
        }

        $holidays = $this->normalizer->normalize($payload, $countryCode, $year);

        return $this->filter->filter($holidays, $acceptedTypes, $nationalOnly);
    }

    /** @param list<string> $acceptedTypes */
    private function validateInput(string $countryCode, int $year, array $acceptedTypes): void
    {
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new ClientException(ClientException::INPUT, 'Country must be a two-letter uppercase ISO code.');
        }

        if ($year < 1970 || $year > 2100) {
            throw new ClientException(ClientException::INPUT, 'Year must be between 1970 and 2100.');
        }

        if ($acceptedTypes === [] || !array_is_list($acceptedTypes)) {
            throw new ClientException(ClientException::INPUT, 'At least one holiday type is required.');
        }

        foreach ($acceptedTypes as $type) {
            if (!is_string($type) || !in_array($type, HolidayType::ALL, true)) {
                throw new ClientException(ClientException::INPUT, 'Unknown holiday type.');
            }
        }
    }
}
