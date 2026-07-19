<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$client = "$root/files/client/custom/modules/document-builder";
$routes = json_decode(file_get_contents("$module/Resources/routes.json"), true, flags: JSON_THROW_ON_ERROR);
$access = file_get_contents("$module/Tools/DocumentBuilder/DataSource/EntityCatalogue/AclEntityCatalogueAccess.php");
$service = file_get_contents("$module/Tools/DocumentBuilder/DataSource/EntityCatalogue/EntityMetadataTreeService.php");
$policy = file_get_contents("$module/Tools/DocumentBuilder/DataSource/EntityCatalogue/EntityFieldPolicy.php");
$template = file_get_contents("$client/res/templates/editor/shell.tpl");
$shell = file_get_contents("$client/src/views/editor/shell.js");

$route = array_values(array_filter($routes, static fn (array $item): bool =>
    ($item['route'] ?? null) === '/DocumentBuilder/entity-catalogue/:entityType/metadata-tree'))[0] ?? [];
Assert::same('get', $route['method'] ?? null, 'Metadata-tree API must be read-only.');
Assert::contains('checkField($entityType, $field, Table::ACTION_READ)', $access, 'Field ACL filtering is missing.');
Assert::contains('checkLink($entityType, $link, Table::ACTION_READ)', $access, 'Link ACL filtering is missing.');
Assert::contains('visited[$target]', $service, 'Circular relationship guards are missing.');
Assert::contains('depthLimited', $service, 'Relationship depth limiting is missing.');
Assert::contains('TECHNICAL_FIELD_LIST', $policy, 'Technical-field exclusions are missing.');
Assert::contains('password|passwd|token|secret', $policy, 'Secret-name exclusions are missing.');
Assert::contains('data-variable-search', $template, 'Metadata search control is missing.');
Assert::contains('toggleMetadataRelationship', $template, 'Expandable relationship control is missing.');
Assert::contains('MetadataBrowser.flatten', $shell, 'The editor must render the filtered metadata model.');
Assert::isFalse(str_contains($shell, 'innerHTML'), 'The metadata browser must not render raw HTML.');
echo "Phase 25 metadata-tree route, ACL, exclusions, cycle, search, and UI contracts passed.\n";
