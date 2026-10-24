<?php

namespace Espo\Modules\ManagementAngajati\Controllers;

use DateTimeImmutable;
use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CereriConcediu;
use stdClass;

/** Adapteaza salvarea nativa din Calendar la fluxul contabil al concediilor. */
class CerereConcediu extends Record
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function actionCreate(array $params, stdClass $data, Request $request): array
    {
        $inceput = $this->extrageData($data->dateStartDate ?? $data->dateStart ?? null);
        $sfarsit = $this->extrageData($data->dateEndDate ?? $data->dateEnd ?? null);
        $descriere = $data->descriere ?? '';

        if ($inceput === null || $sfarsit === null ||
            !is_string($descriere) || mb_strlen($descriere) > 2000) {
            throw BadRequest::createWithBody(
                'perioadaConcediuInvalida',
                Body::create()->withMessageTranslation(
                    'perioadaConcediuInvalida',
                    'CerereConcediu'
                )
            );
        }

        $rezultat = $this->injectableFactory->create(CereriConcediu::class)->creeaza([
            'dataInceput' => $inceput,
            'dataSfarsit' => $sfarsit,
            'descriere' => $descriere,
        ]);

        return $rezultat + [
            'name' => $rezultat['nume'] ?? '',
            // Calendarul nativ incearca sa afiseze local orice creare. Cererea
            // devine eveniment numai dupa aprobare, prin interogarea dedicata.
            'dateStartDate' => null,
            'dateEndDate' => null,
            'status' => $rezultat['stare'] ?? 'InAsteptare',
        ];
    }

    private function esteData(string $valoare): bool
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valoare);

        return $data !== false && $data->format('Y-m-d') === $valoare;
    }

    private function extrageData(mixed $valoare): ?string
    {
        if (!is_string($valoare) || strlen($valoare) < 10) {
            return null;
        }

        $data = substr($valoare, 0, 10);

        return $this->esteData($data) ? $data : null;
    }
}
