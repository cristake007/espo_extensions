<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final readonly class VariableFormatter
{
    private const ROMANIAN_MONTH_LIST = [
        1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie',
        'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie',
    ];
    private const ROMANIAN_WEEKDAY_LIST = [
        1 => 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă', 'duminică',
    ];

    public function __construct(private MissingValueResolver $missingValueResolver)
    {}

    public function format(
        VariableValue $value,
        VariablePresentation $presentation,
        VariableFormatContext $context,
    ): FormattedVariableValue {
        if ($value->state !== VariableValueState::Present) {
            return $this->missingValueResolver->resolve(
                $value->state,
                $presentation->missing,
                $presentation->format->fallback,
            );
        }

        try {
            $this->requireCompatibleType($value->type, $presentation->format->type);
            $text = $this->formatPresent($value, $presentation->format, $context);
            $text = $this->transform($text, $presentation->format);

            return new FormattedVariableValue(
                VariableValueState::Present,
                MissingValueDisposition::Display,
                $text,
            );
        } catch (InvalidArgumentException) {
            return new FormattedVariableValue(
                VariableValueState::Invalid,
                MissingValueDisposition::Failure,
                null,
            );
        }
    }

    private function requireCompatibleType(VariableValueType $valueType, FormatType $formatType): void
    {
        $expected = match ($valueType) {
            VariableValueType::Text => null,
            VariableValueType::Date => FormatType::Date,
            VariableValueType::DateTime => FormatType::DateTime,
            VariableValueType::Number => FormatType::Number,
            VariableValueType::Currency => FormatType::Currency,
            VariableValueType::Boolean => FormatType::Boolean,
            VariableValueType::Enum => FormatType::Enum,
            VariableValueType::MultiValue => FormatType::MultiValue,
        };

        if ($formatType !== FormatType::Auto && $formatType !== $expected) {
            throw new InvalidArgumentException('A variable format is incompatible with its value type.');
        }
    }

    private function formatPresent(
        VariableValue $value,
        VariableFormat $format,
        VariableFormatContext $context,
    ): string {
        return match ($value->type) {
            VariableValueType::Text => $value->value,
            VariableValueType::Date => $this->formatDate($value->value, $format, $context, false),
            VariableValueType::DateTime => $this->formatDate($value->value, $format, $context, true),
            VariableValueType::Number => $this->formatNumber($value->value, $format->decimals, $context->locale),
            VariableValueType::Currency => $this->formatCurrency($value->value, $format, $context->locale),
            VariableValueType::Boolean => $this->formatBoolean($value->value, $format, $context->locale),
            VariableValueType::Enum => $context->enumLabels[$value->value] ?? $value->value,
            VariableValueType::MultiValue => implode(
                $format->separator,
                array_map(
                    fn (mixed $item): string => $this->formatMultiItem($item, $context),
                    $value->value,
                ),
            ),
        };
    }

    private function formatDate(
        string $raw,
        VariableFormat $format,
        VariableFormatContext $context,
        bool $withTime,
    ): string {
        try {
            if ($withTime) {
                $databaseFormat = preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D', $raw) === 1;
                $date = DateTimeImmutable::createFromFormat(
                    $databaseFormat ? '!Y-m-d H:i:s' : DateTimeInterface::ATOM,
                    $raw,
                    $databaseFormat ? new DateTimeZone('UTC') : null,
                );
                $dateErrors = DateTimeImmutable::getLastErrors();

                if ($date === false || ($dateErrors !== false &&
                    ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                    throw new InvalidArgumentException('A datetime value is invalid.');
                }

                $date = $date->setTimezone(new DateTimeZone($context->timezone));
            } else {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));

                if ($date === false || $date->format('Y-m-d') !== $raw) {
                    throw new InvalidArgumentException('A date value is invalid.');
                }
            }
        } catch (Exception) {
            throw new InvalidArgumentException('A date value is invalid.');
        }

        $dateText = $this->localizedDate($date, $format->dateStyle, $context->locale);

        if (!$withTime) {
            return $dateText;
        }

        return $dateText . ' ' . $date->format($format->timeStyle === 'medium' ? 'H:i:s' : 'H:i');
    }

    private function localizedDate(DateTimeImmutable $date, string $style, string $locale): string
    {
        if (str_starts_with($locale, 'ro_')) {
            return match ($style) {
                'short' => $date->format('d.m.Y'),
                'medium' => sprintf('%d %s %d', (int) $date->format('j'), self::ROMANIAN_MONTH_LIST[(int) $date->format('n')], (int) $date->format('Y')),
                'long' => sprintf(
                    '%s, %d %s %d',
                    self::ROMANIAN_WEEKDAY_LIST[(int) $date->format('N')],
                    (int) $date->format('j'),
                    self::ROMANIAN_MONTH_LIST[(int) $date->format('n')],
                    (int) $date->format('Y'),
                ),
            };
        }

        return match ($style) {
            'short' => $date->format('m/d/Y'),
            'medium' => $date->format('M j, Y'),
            'long' => $date->format('F j, Y'),
        };
    }

    private function formatNumber(int|float $value, int $decimals, string $locale): string
    {
        $romanian = str_starts_with($locale, 'ro_');

        return number_format($value, $decimals, $romanian ? ',' : '.', $romanian ? '.' : ',');
    }

    /** @param array{amount: int|float, currency: string} $value */
    private function formatCurrency(array $value, VariableFormat $format, string $locale): string
    {
        $currency = $format->currency ?? $value['currency'];
        $number = $this->formatNumber($value['amount'], $format->decimals, $locale);
        $symbol = ['EUR' => '€', 'GBP' => '£', 'RON' => 'lei', 'USD' => '$'][$currency] ?? $currency;

        if (str_starts_with($locale, 'ro_')) {
            return $number . ' ' . $symbol;
        }

        return in_array($currency, ['EUR', 'GBP', 'USD'], true) ? $symbol . $number : "$currency $number";
    }

    private function formatBoolean(bool $value, VariableFormat $format, string $locale): string
    {
        if ($value) {
            return $format->trueLabel ?? (str_starts_with($locale, 'ro_') ? 'Da' : 'Yes');
        }

        return $format->falseLabel ?? (str_starts_with($locale, 'ro_') ? 'Nu' : 'No');
    }

    private function formatMultiItem(mixed $item, VariableFormatContext $context): string
    {
        if (is_bool($item)) {
            return $item ? (str_starts_with($context->locale, 'ro_') ? 'Da' : 'Yes') :
                (str_starts_with($context->locale, 'ro_') ? 'Nu' : 'No');
        }

        $key = (string) $item;

        return $context->enumLabels[$key] ?? $key;
    }

    private function transform(string $value, VariableFormat $format): string
    {
        if ($format->trim) {
            $value = trim($value);
        }

        if ($value === '' && $format->fallback !== null) {
            $value = $format->fallback;
        }

        $value = match ($format->case) {
            TextCase::None => $value,
            TextCase::Upper => mb_strtoupper($value),
            TextCase::Lower => mb_strtolower($value),
            TextCase::Title => mb_convert_case($value, MB_CASE_TITLE),
        };

        return $format->prefix . $value . $format->suffix;
    }
}
