<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use Espo\Core\HttpClient;
use Throwable;

final class EspoHttpTransport implements HttpTransport
{
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const RESPONSE_TIMEOUT_SECONDS = 15;
    private const READ_CHUNK_BYTES = 8192;

    public function __construct(private HttpClient\ClientFactory $clientFactory)
    {}

    public function get(string $url, int $maximumBytes): HttpResponse
    {
        $client = $this->clientFactory->create(new HttpClient\Options(
            protocols: [HttpClient\Protocol::https],
            redirect: new HttpClient\Options\Redirect(allow: false),
            timeout: self::RESPONSE_TIMEOUT_SECONDS,
            connectTimeout: self::CONNECT_TIMEOUT_SECONDS,
            internalHostRestriction: new HttpClient\Options\InternalHostRestriction(restrict: true),
        ));
        $request = HttpClient\RequestCreator::create('GET', $url)
            ->withHeader('Accept', 'application/json');

        try {
            $response = $client->send($request);
        } catch (Throwable $e) {
            throw new ClientException(
                ClientException::TRANSPORT,
                'Nager.Date could not be reached.',
                $e,
            );
        }

        $contentLength = $response->getHeaderLine('Content-Length');

        if (preg_match('/^[0-9]+$/', $contentLength) && (int) $contentLength > $maximumBytes) {
            throw new ClientException(
                ClientException::RESPONSE_SIZE,
                'Nager.Date returned a response larger than the allowed limit.',
            );
        }

        $body = '';
        $stream = $response->getBody();

        try {
            while (!$stream->eof()) {
                $remaining = $maximumBytes - strlen($body);
                $chunk = $stream->read(min(self::READ_CHUNK_BYTES, $remaining + 1));

                if ($chunk === '' && !$stream->eof()) {
                    throw new ClientException(
                        ClientException::TRANSPORT,
                        'The Nager.Date response stopped before completion.',
                    );
                }

                $body .= $chunk;

                if (strlen($body) > $maximumBytes) {
                    throw new ClientException(
                        ClientException::RESPONSE_SIZE,
                        'Nager.Date returned a response larger than the allowed limit.',
                    );
                }
            }
        } catch (ClientException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ClientException(
                ClientException::TRANSPORT,
                'The Nager.Date response could not be read.',
                $e,
            );
        }

        $location = trim($response->getHeaderLine('Location')) ?: null;

        return new HttpResponse($response->getStatusCode(), $body, $location);
    }
}
