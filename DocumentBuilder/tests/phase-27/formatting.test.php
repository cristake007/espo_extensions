<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\FormatType;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\MissingValueDisposition;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\MissingValuePolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\MissingValueResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\SystemVariableKind;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\SystemVariableRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\SystemVariableResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\TextCase;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormat;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormatContext;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormatter;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariablePresentation;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$source = "$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/Variable";

foreach ([
    'VariableValueState.php', 'VariableValueType.php', 'VariableValue.php', 'FormatType.php',
    'TextCase.php', 'MissingValuePolicy.php', 'MissingValueDisposition.php', 'VariableFormat.php',
    'VariablePresentation.php', 'VariableFormatContext.php', 'FormattedVariableValue.php',
    'MissingValueResolver.php', 'VariableFormatter.php', 'SystemVariableRegistry.php',
    'SystemVariableKind.php', 'SystemVariableResult.php', 'SystemVariableResolver.php',
] as $file) {
    require "$source/$file";
}

$formatter = new VariableFormatter(new MissingValueResolver());
$ro = new VariableFormatContext('ro_RO', 'Europe/Bucharest', ['active' => 'Activ', 'paused' => 'În pauză']);
$en = new VariableFormatContext('en_US', 'America/New_York', ['active' => 'Active']);
$auto = new VariablePresentation();
$format = static fn (VariableValue $value, VariablePresentation $presentation, VariableFormatContext $context): array =>
    $formatter->format($value, $presentation, $context)->toArray();

Assert::same('15 ianuarie 2025', $format(
    new VariableValue(VariableValueType::Date, VariableValueState::Present, '2025-01-15'),
    $auto,
    $ro,
)['text'], 'Romanian date formatting changed.');
Assert::same('15 ianuarie 2025 14:30', $format(
    new VariableValue(VariableValueType::DateTime, VariableValueState::Present, '2025-01-15T12:30:00+00:00'),
    $auto,
    $ro,
)['text'], 'Timezone-aware Romanian datetime formatting changed.');
Assert::same('01/15/2025 07:30', $format(
    new VariableValue(VariableValueType::DateTime, VariableValueState::Present, '2025-01-15T12:30:00+00:00'),
    new VariablePresentation(new VariableFormat(dateStyle: 'short')),
    $en,
)['text'], 'English timezone conversion changed.');
Assert::same('1.234,50', $format(
    new VariableValue(VariableValueType::Number, VariableValueState::Present, 1234.5),
    $auto,
    $ro,
)['text'], 'Romanian number formatting changed.');
Assert::same('1.234,50 lei', $format(
    new VariableValue(VariableValueType::Currency, VariableValueState::Present, ['currency'=>'RON','amount'=>1234.5]),
    $auto,
    $ro,
)['text'], 'Romanian currency formatting changed.');
Assert::same('Da', $format(
    new VariableValue(VariableValueType::Boolean, VariableValueState::Present, true),
    $auto,
    $ro,
)['text'], 'Romanian boolean formatting changed.');
Assert::same('Activ', $format(
    new VariableValue(VariableValueType::Enum, VariableValueState::Present, 'active'),
    $auto,
    $ro,
)['text'], 'Enum labels must come from the bounded label map.');
Assert::same('Activ / În pauză', $format(
    new VariableValue(VariableValueType::MultiValue, VariableValueState::Present, ['active', 'paused']),
    new VariablePresentation(new VariableFormat(separator: ' / ')),
    $ro,
)['text'], 'Multi-value formatting changed.');
Assert::same('[ȘCOALĂ]', $format(
    new VariableValue(VariableValueType::Text, VariableValueState::Present, '  școală  '),
    new VariablePresentation(new VariableFormat(case: TextCase::Upper, prefix: '[', suffix: ']')),
    $ro,
)['text'], 'Bounded trim/case/prefix/suffix transforms changed.');

foreach ([
    [MissingValuePolicy::Empty, MissingValueDisposition::Display],
    [MissingValuePolicy::Fallback, MissingValueDisposition::Display],
    [MissingValuePolicy::HideElement, MissingValueDisposition::HideElement],
    [MissingValuePolicy::HideRow, MissingValueDisposition::HideRow],
    [MissingValuePolicy::HideSection, MissingValueDisposition::HideSection],
    [MissingValuePolicy::Warning, MissingValueDisposition::Warning],
    [MissingValuePolicy::Required, MissingValueDisposition::Failure],
] as [$policy, $disposition]) {
    $fallback = $policy === MissingValuePolicy::Fallback ? 'Indisponibil' : null;
    $result = $formatter->format(
        new VariableValue(VariableValueType::Text, VariableValueState::Missing),
        new VariablePresentation(new VariableFormat(fallback: $fallback), $policy),
        $ro,
    );
    Assert::same($disposition, $result->disposition, "Missing policy {$policy->value} changed.");
}

$forbidden = $formatter->format(
    new VariableValue(VariableValueType::Text, VariableValueState::Forbidden),
    new VariablePresentation(new VariableFormat(fallback: 'Restricționat'), MissingValuePolicy::Warning),
    $ro,
);
Assert::same(VariableValueState::Forbidden, $forbidden->state, 'Forbidden and missing states must remain distinct.');
Assert::same(MissingValueDisposition::Warning, $forbidden->disposition, 'Forbidden warning policy changed.');
$invalid = $formatter->format(
    new VariableValue(VariableValueType::Text, VariableValueState::Invalid),
    new VariablePresentation(new VariableFormat(fallback: 'masked'), MissingValuePolicy::Fallback),
    $ro,
);
Assert::same(MissingValueDisposition::Failure, $invalid->disposition, 'Invalid data must never be hidden by fallback text.');
$wrongFormat = $formatter->format(
    new VariableValue(VariableValueType::Number, VariableValueState::Present, 10),
    new VariablePresentation(new VariableFormat(type: FormatType::Date)),
    $ro,
);
Assert::same(VariableValueState::Invalid, $wrongFormat->state, 'Type-incompatible formats must fail deterministically.');

$system = new SystemVariableResolver(new SystemVariableRegistry());
$now = new DateTimeImmutable('2025-01-15T23:30:00+00:00');
Assert::same('2025-01-16', $system->resolve('currentDate', $now, 'Ana', 'Europe/Bucharest')->value?->value, 'Current date must use the document timezone.');
Assert::same(VariableValueState::Missing, $system->resolve('currentUserName', $now, null)->value?->state, 'Absent current users must produce a missing state.');
Assert::same(SystemVariableKind::RendererPlaceholder, $system->resolve('pageNumber', $now, 'Ana')->kind, 'Page numbers must remain renderer-owned placeholders.');
Assert::throws(fn () => $system->resolve('phpExpression', $now, 'Ana'), InvalidArgumentException::class, 'Arbitrary system expressions were accepted.');
Assert::throws(fn () => new VariableFormat(decimals: 7), InvalidArgumentException::class, 'Unbounded precision was accepted.');
Assert::throws(fn () => new VariableValue(VariableValueType::Text, VariableValueState::Present, str_repeat('a', 10001)), InvalidArgumentException::class, 'An unbounded raw text value was accepted.');
Assert::throws(fn () => VariablePresentation::fromArray(['format'=>(new VariableFormat())->toArray(),'missing'=>'eval']), InvalidArgumentException::class, 'An expression-like missing policy was accepted.');

$shuffledFormat = array_reverse((new VariableFormat())->toArray(), true);
Assert::same((new VariableFormat())->toArray(), VariableFormat::fromArray($shuffledFormat)->toArray(), 'Canonical parsing must not depend on input object key order.');

echo "Phase 27 locale, timezone, typed formatting, transforms, system-variable, and missing-policy tests passed.\n";
