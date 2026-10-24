<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\Json;
use JsonException;

/** Converteste valoarea JSON ORM a instantaneului intr-un array verificat. */
class NormalizatorInstantaneuCondica
{
    /** @return array<string, mixed> */
    public function normalizeaza(mixed $valoare): array
    {
        if (is_array($valoare)) {
            return $valoare;
        }

        try {
            $rezultat = Json::decode(Json::encode($valoare), true);
        } catch (JsonException) {
            $this->aruncaInvalid();
        }

        if (!is_array($rezultat)) {
            $this->aruncaInvalid();
        }

        /** @var array<string, mixed> $rezultat */
        return $rezultat;
    }

    /** @return never */
    private function aruncaInvalid(): void
    {
        throw BadRequest::createWithBody(
            'instantaneuCondicaInvalid',
            Body::create()->withMessageTranslation(
                'instantaneuCondicaInvalid',
                'CondicaPrezenta'
            )
        );
    }
}
