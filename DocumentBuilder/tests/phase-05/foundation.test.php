<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use DocumentBuilder\Tests\Support\RuntimeGuard;
use DocumentBuilder\Tests\Support\RuntimeIdentity;

require dirname(__DIR__) . '/bootstrap.php';

$fixtureRoot = dirname(__DIR__) . '/fixtures';
$loader = new FixtureLoader($fixtureRoot);
$catalogue = $loader->json('catalogue.json');

Assert::same(1, $catalogue['schemaVersion'] ?? null, 'Unexpected fixture catalogue version.');
Assert::same(
    'Every listed fixture is original project test data with no third-party media.',
    $catalogue['licensePolicy'] ?? null,
    'Fixture license policy changed.',
);

$cataloguePaths = [];

foreach ($catalogue['fixtures'] ?? [] as $entry) {
    Assert::isTrue(is_array($entry), 'Fixture catalogue entries must be objects.');
    Assert::same(
        'original-project-test-data',
        $entry['license'] ?? null,
        'Every fixture must declare the approved original-data license marker.',
    );

    $path = $entry['path'] ?? null;
    Assert::isTrue(is_string($path), 'Every fixture catalogue entry must have a path.');
    $cataloguePaths[] = $path;
    Assert::isTrue(strlen($loader->text($path)) <= 65536, "Fixture must remain at most 64 KiB: $path");
}

sort($cataloguePaths);
$actualPaths = array_values(array_filter(
    $loader->relativeFiles(),
    static fn (string $path): bool => $path !== 'catalogue.json',
));
Assert::same($cataloguePaths, $actualPaths, 'Fixture catalogue and filesystem inventory differ.');
Assert::same(count($cataloguePaths), count(array_unique($cataloguePaths)), 'Fixture paths must be unique.');

Assert::throws(
    fn () => $loader->text('../catalogue.json'),
    RuntimeException::class,
    'Fixture loading must reject traversal.',
);
Assert::throws(
    fn () => $loader->text('/etc/passwd'),
    RuntimeException::class,
    'Fixture loading must reject absolute paths.',
);

$metadata = [];

foreach (['Account', 'Contact', 'CourseEnrollment'] as $entityType) {
    $entity = $loader->json("metadata/entityDefs/$entityType.json");
    Assert::same(1, $entity['schemaVersion'] ?? null, "$entityType metadata version changed.");
    Assert::same($entityType, $entity['entityType'] ?? null, "$entityType fixture identity changed.");
    Assert::isTrue(is_array($entity['fields'] ?? null), "$entityType must expose fields.");
    Assert::isTrue(is_array($entity['links'] ?? null), "$entityType must expose links.");
    $metadata[$entityType] = $entity;
}

Assert::same(false, $metadata['Contact']['custom'] ?? null, 'Contact must represent a standard entity.');
Assert::same(false, $metadata['Account']['custom'] ?? null, 'Account must represent a standard entity.');
Assert::same(true, $metadata['CourseEnrollment']['custom'] ?? null, 'CourseEnrollment must represent a custom entity.');
Assert::same(
    'Account',
    $metadata['Contact']['links']['account']['entity'] ?? null,
    'Contact must exercise a standard belongs-to relationship.',
);
Assert::same(
    'CourseEnrollment',
    $metadata['Contact']['links']['attendedCourses']['entity'] ?? null,
    'Contact must exercise a custom collection relationship.',
);
Assert::same(
    true,
    $metadata['Contact']['fields']['internalToken']['documentBuilderDisabled'] ?? null,
    'Metadata must include an explicitly forbidden field.',
);

$acl = $loader->json('acl/cases.json');
Assert::same(1, $acl['schemaVersion'] ?? null, 'Unexpected ACL fixture version.');
$aclReasons = [];
$aclDecisions = [];

foreach ($acl['cases'] ?? [] as $case) {
    Assert::isTrue(is_array($case), 'ACL cases must be objects.');
    $expected = $case['expected'] ?? null;
    Assert::isTrue(is_array($expected), 'ACL cases must define an expected result.');
    Assert::same(false, $expected['discloseForbiddenValue'] ?? null, 'ACL cases must never disclose forbidden values.');
    $aclDecisions[] = $expected['decision'] ?? null;
    $aclReasons[] = $expected['reason'] ?? null;
}

foreach (['allow', 'deny', 'filter'] as $decision) {
    Assert::isTrue(in_array($decision, $aclDecisions, true), "ACL corpus must cover $decision decisions.");
}

foreach (['action', 'scope', 'record', 'field', 'link', 'relatedRecord'] as $reason) {
    Assert::isTrue(in_array($reason, $aclReasons, true), "ACL corpus must cover $reason enforcement.");
}

$security = $loader->json('security/malicious-inputs.json');
Assert::same(1, $security['schemaVersion'] ?? null, 'Unexpected security fixture version.');
Assert::same('inert-data-only', $security['executionPolicy'] ?? null, 'Security fixtures must remain inert.');
$securityCategories = [];

foreach ($security['cases'] ?? [] as $case) {
    Assert::isTrue(is_array($case), 'Security cases must be objects.');
    Assert::isTrue(is_string($case['payload'] ?? null), 'Security payloads must be inert strings.');
    Assert::isTrue(is_string($case['expected'] ?? null), 'Security cases must state an expected disposition.');
    $securityCategories[] = $case['category'] ?? null;
}

foreach ([
    'xss-source',
    'xss-static',
    'css-injection',
    'unsafe-svg',
    'path-traversal',
    'remote-resource',
    'spreadsheet-formula',
    'malformed-json',
    'circular-relationship',
    'authorization-boundary',
    'resource-limit',
    'stale-revision',
    'unauthorized-download',
] as $category) {
    Assert::isTrue(in_array($category, $securityCategories, true), "Security corpus must cover $category.");
}

$media = $loader->json('media/media-cases.json');
Assert::same(1, $media['schemaVersion'] ?? null, 'Unexpected media fixture version.');
Assert::same(6, count($media['cases'] ?? []), 'Media fixture inventory changed unexpectedly.');
$safeSvg = $loader->text('media/safe-reference-free.svg');

foreach (['<script', '<foreignObject', 'href="http', "href='http", 'url(', '<!ENTITY'] as $forbiddenSvgText) {
    Assert::isFalse(
        stripos($safeSvg, $forbiddenSvgText) !== false,
        "Safe SVG contains a forbidden construct: $forbiddenSvgText",
    );
}

$acceptanceIds = [];
$sourceTypes = [];

foreach (['diploma', 'history', 'offer', 'spreadsheet-certificate'] as $acceptanceId) {
    $scenario = $loader->json("acceptance/$acceptanceId.json");
    Assert::same(1, $scenario['schemaVersion'] ?? null, 'Unexpected acceptance fixture version.');
    Assert::same('acceptance-scenario', $scenario['fixtureKind'] ?? null, 'Unexpected acceptance fixture kind.');
    Assert::same(false, $scenario['canonicalLayoutSchema'] ?? null, 'Phase 05 scenarios must not define the layout schema.');
    Assert::same($acceptanceId, $scenario['id'] ?? null, 'Acceptance scenario identity changed.');
    Assert::isTrue(count($scenario['requiredCapabilities'] ?? []) >= 5, 'Acceptance scenarios need representative capabilities.');
    Assert::isTrue(count($scenario['acceptance'] ?? []) >= 5, 'Acceptance scenarios need representative outcomes.');
    $acceptanceIds[] = $scenario['id'];
    $sourceTypes[] = $scenario['source']['type'] ?? null;
}

Assert::same(
    ['diploma', 'history', 'offer', 'spreadsheet-certificate'],
    $acceptanceIds,
    'The four named acceptance documents must remain present.',
);
Assert::isTrue(in_array('entity', $sourceTypes, true), 'Acceptance fixtures must cover entity sources.');
Assert::isTrue(in_array('spreadsheet', $sourceTypes, true), 'Acceptance fixtures must cover spreadsheet sources.');

$identity = new RuntimeIdentity('20260719-phase05');
$contactMarker = $identity->marker('contact-diploma');
Assert::same('DBT 20260719-phase05 contact-diploma', $identity->recordName('contact-diploma'), 'Runtime record name changed.');
Assert::isTrue($identity->owns($contactMarker, 'contact-diploma'), 'A complete marker must prove fixture ownership.');
$reorderedMarker = array_reverse($contactMarker, true);
Assert::isTrue($identity->owns($reorderedMarker, 'contact-diploma'), 'Marker key order must not affect ownership.');
Assert::isFalse(
    $identity->owns(array_merge($contactMarker, ['fixtureId' => 'contact-history']), 'contact-diploma'),
    'A marker for another fixture must not prove ownership.',
);
Assert::isFalse(
    $identity->owns(['suite' => RuntimeIdentity::SUITE, 'runId' => '20260719-phase05'], 'contact-diploma'),
    'A partial marker must never prove ownership.',
);
Assert::same($contactMarker, $identity->cleanupCriteria('contact-diploma'), 'Cleanup must require the complete exact marker.');

Assert::throws(
    fn () => new RuntimeIdentity('unsafe fixture'),
    InvalidArgumentException::class,
    'Unsafe runtime run identifiers must be rejected.',
);
Assert::throws(
    fn () => RuntimeGuard::assertExplicitNonProductionPath(''),
    InvalidArgumentException::class,
    'Runtime helpers must not provide a default path.',
);
Assert::throws(
    fn () => RuntimeGuard::assertExplicitNonProductionPath('relative/test-instance'),
    InvalidArgumentException::class,
    'Runtime helpers must require an absolute path.',
);
Assert::throws(
    fn () => RuntimeGuard::assertExplicitNonProductionPath('/opt/crm.cursurituv.ro'),
    InvalidArgumentException::class,
    'Runtime helpers must prohibit the production root.',
);
Assert::throws(
    fn () => RuntimeGuard::assertExplicitNonProductionPath('/opt/crm.cursurituv.ro/data'),
    InvalidArgumentException::class,
    'Runtime helpers must prohibit production descendants.',
);
Assert::throws(
    fn () => RuntimeGuard::assertExplicitNonProductionPath('/opt/document-builder-test/../crm.cursurituv.ro'),
    InvalidArgumentException::class,
    'Runtime helpers must prohibit lexical aliases of production.',
);
Assert::same(
    '/opt/document-builder-test',
    RuntimeGuard::assertExplicitNonProductionPath('/opt/fixtures/../document-builder-test/'),
    'Runtime helper path normalization changed.',
);

echo "Phase 05 test and fixture foundation checks passed.\n";
