<?php

declare(strict_types=1);

use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\MecXmlBuilder;

$sourceRoot = dirname(__DIR__, 2) .
    '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri';

require_once $sourceRoot . '/MecXmlBuilder.php';

$checks = 0;
$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks, &$failures): void {
    $checks++;

    if ($expected !== $actual) {
        $failures[] = $message . '\n  expected: ' . var_export($expected, true) .
            '\n  actual:   ' . var_export($actual, true);
    }
};

$assertTrue = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;

    if (!$condition) {
        $failures[] = $message;
    }
};

$courseTitle = 'Măsurarea eficacității unui sistem Ș Ț ă â î & A < B';
$xml = (new MecXmlBuilder())->build([
    [
        'courseTitle' => $courseTitle,
        'dateRange' => '5.01.2026',
        'permalink' => 'https://example.test/cursuri/masurare/?a=1&b=2',
    ],
], 21000);

$assertTrue(
    str_starts_with($xml, '<?xml version="1.0" encoding="UTF-8" ?>' . "\n"),
    'The XML declaration must explicitly retain UTF-8 and compatibility spacing.'
);
$assertSame(1, preg_match('//u', $xml), 'The serialized XML must contain valid UTF-8 bytes.');

foreach (['Măsurarea eficacității unui sistem', 'Ș', 'Ț', 'ă', 'â', 'î'] as $literalText) {
    $assertTrue(str_contains($xml, $literalText), "The XML must preserve literal UTF-8 text: {$literalText}");
}

$assertSame(
    0,
    preg_match('/&#(?:[0-9]+|x[0-9a-f]+);/i', $xml),
    'Romanian characters must not be serialized as numeric character references.'
);
$assertTrue(
    str_contains(
        $xml,
        '<title>Măsurarea eficacității unui sistem Ș Ț ă â î &amp; A &lt; B</title>'
    ),
    'The title must preserve Romanian text while escaping XML-sensitive characters.'
);
$assertTrue(
    str_contains($xml, '<mec_read_more>https://example.test/cursuri/masurare/?a=1&amp;b=2</mec_read_more>'),
    'The permalink must remain safely escaped.'
);

$previousLibxmlState = libxml_use_internal_errors(true);
$document = new DOMDocument();
$loaded = $document->loadXML($xml);
$parseErrors = libxml_get_errors();
libxml_clear_errors();
libxml_use_internal_errors($previousLibxmlState);

$assertTrue($loaded, 'The serialized output must parse successfully as XML.');
$assertSame([], $parseErrors, 'The serialized output must parse without XML errors.');

if ($loaded) {
    $xpath = new DOMXPath($document);
    $nodeText = static function (string $expression) use ($xpath): ?string {
        $node = $xpath->query($expression)?->item(0);

        return $node?->textContent;
    };
    $childNames = static function (string $expression) use ($xpath): array {
        $names = [];
        $nodes = $xpath->query($expression);

        if ($nodes === false) {
            return $names;
        }

        foreach ($nodes as $node) {
            $names[] = $node->nodeName;
        }

        return $names;
    };

    $assertSame(
        ['ID', 'title', 'content', 'post', 'meta', 'mec', 'time'],
        $childNames('/events/item[1]/*'),
        'Top-level MEC node order must remain unchanged.'
    );
    $assertSame($courseTitle, $nodeText('/events/item[1]/title'), 'The title text must round-trip exactly.');
    $assertSame(
        $courseTitle,
        $nodeText('/events/item[1]/post/post_title'),
        'The post title text must round-trip exactly.'
    );
    $assertSame('21000', $nodeText('/events/item[1]/ID'), 'The configured starting post ID must remain exact.');
    $assertSame(
        '21000',
        $nodeText('/events/item[1]/post/ID'),
        'The nested post ID must remain aligned with the item ID.'
    );
    $assertSame(
        '2026-01-05 00:00:00',
        $nodeText('/events/item[1]/post/post_date'),
        'The post date format must remain unchanged.'
    );
    $assertSame(
        '1767592800',
        $nodeText('/events/item[1]/time/start_timestamp'),
        'The Europe/Bucharest start timestamp must remain unchanged.'
    );
    $assertSame(
        '1767628800',
        $nodeText('/events/item[1]/time/end_timestamp'),
        'The Europe/Bucharest end timestamp must remain unchanged.'
    );
}

foreach (['content', 'mec_color', 'id', 'end'] as $emptyElement) {
    $assertTrue(
        str_contains($xml, "<{$emptyElement}/>"),
        "The {$emptyElement} element must retain compact empty-element serialization."
    );
}

$assertTrue(
    str_contains($xml, "\n <item>\n  <ID>21000</ID>"),
    'The established one-space-per-level indentation must remain unchanged.'
);

if ($failures !== []) {
    fwrite(
        STDERR,
        "Offline MEC XML builder: " . count($failures) . " failure(s) across {$checks} checks.\n" .
            implode("\n", $failures) . "\n"
    );
    exit(1);
}

fwrite(STDOUT, "Offline MEC XML builder: {$checks} checks passed; no network or EspoCRM state used.\n");
