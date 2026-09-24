<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Validation;

use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationRequest;
use stdClass;

final readonly class RequestValidator
{
    private const KEY_LIST = [
        'brief',
        'platform',
        'format',
        'style',
        'customInstructions',
        'locale',
        'contentType',
        'course',
    ];

    public function __construct(private ContractRegistry $registry)
    {}

    public function validate(stdClass $body): GenerationRequest
    {
        $values = get_object_vars($body);
        $unknown = array_diff(array_keys($values), self::KEY_LIST);

        if ($unknown !== []) {
            $this->invalid();
        }

        $brief = $this->text($body->brief ?? null, 1, 20000);
        $platform = $this->text($body->platform ?? null, 1, 30);
        $format = $this->text($body->format ?? null, 1, 30);
        $style = $this->text($body->style ?? null, 1, 30);
        $instructions = $this->text($body->customInstructions ?? '', 0, 5000);
        $locale = $this->text($body->locale ?? null, 2, 2);
        $contentType = $this->text($body->contentType ?? null, 1, 20);
        $course = $this->course($contentType, $body->course ?? null);
        $platformDefinition = $this->registry->platform($platform);

        if (
            $platformDefinition === null ||
            $this->registry->format($format) === null ||
            $this->registry->style($style) === null ||
            !in_array($format, $platformDefinition['formats'] ?? [], true) ||
            !in_array($locale, ['ro', 'en'], true) ||
            ($style === 'custom' && $instructions === '') ||
            !in_array($contentType, ['general', 'course', 'webinar', 'top10', 'service', 'postEvent'], true)
        ) {
            $this->invalid();
        }

        return new GenerationRequest(
            $brief,
            $platform,
            $format,
            $style,
            $instructions,
            $locale,
            $contentType,
            $course,
        );
    }

    /** @return array{id: string, name: string, slug: ?string, url: ?string}|null */
    private function course(string $contentType, mixed $value): ?array
    {
        if ($contentType !== 'course') {
            if ($value !== null) {
                $this->invalid();
            }

            return null;
        }

        if (!$value instanceof stdClass) {
            $this->invalid();
        }

        $values = get_object_vars($value);
        $keys = ['id', 'name', 'slug', 'url'];

        if (array_diff(array_keys($values), $keys) !== [] || array_diff($keys, array_keys($values)) !== []) {
            $this->invalid();
        }

        $id = $this->nullableText($value->id ?? null, 255);
        $name = $this->text($value->name ?? null, 1, 500);
        $slug = $this->nullableText($value->slug ?? null, 500);
        $url = $this->nullableText($value->url ?? null, 2048);

        if ($id === null || ($url !== null && filter_var($url, FILTER_VALIDATE_URL) === false)) {
            $this->invalid();
        }

        return compact('id', 'name', 'slug', 'url');
    }

    private function nullableText(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->text($value, 1, $maximum);
    }

    private function text(mixed $value, int $minimum, int $maximum): string
    {
        if (!is_string($value)) {
            $this->invalid();
        }

        $value = trim($value);
        $length = mb_strlen($value);

        if ($length < $minimum || $length > $maximum) {
            $this->invalid();
        }

        return $value;
    }

    private function invalid(): never
    {
        throw new GenerationException(
            'INVALID_REQUEST',
            400,
            'Datele trimise către generator nu sunt valide.',
        );
    }
}
