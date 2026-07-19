<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$client = "$root/files/client/custom/modules/document-builder";
$routes = json_decode(file_get_contents("$module/Resources/routes.json"), true, flags: JSON_THROW_ON_ERROR);
$binding = file_get_contents("$module/Binding.php");
$access = file_get_contents("$module/Tools/DocumentBuilder/DataSource/EntityCatalogue/AclEntityCatalogueAccess.php");
$configuredPolicy = file_get_contents("$module/Tools/DocumentBuilder/DataSource/EntityCatalogue/ConfiguredEntitySourcePolicy.php");
$policy = file_get_contents("$module/Tools/DocumentBuilder/DataSource/EntityCatalogue/EntitySourcePolicyRules.php");
$service = file_get_contents("$module/Tools/DocumentBuilder/DataSource/EntityCatalogue/EntityCatalogueService.php");
$draftService = file_get_contents("$module/Tools/DocumentBuilder/Draft/DraftSaveService.php");
$shell = file_get_contents("$client/src/views/editor/shell.js");
$template = file_get_contents("$client/res/templates/editor/shell.tpl");

$route = array_values(array_filter($routes, static fn (array $item): bool =>
    ($item['route'] ?? null) === '/DocumentBuilder/entity-catalogue'))[0] ?? [];
Assert::same('get', $route['method'] ?? null, 'Entity catalogue must be read-only.');
Assert::contains('EntityCatalogueAccess::class, AclEntityCatalogueAccess::class', $binding, 'Catalogue ACL binding is missing.');
Assert::contains('checkScope($entityType, Table::ACTION_READ)', $access, 'Every returned scope needs current-user read access.');
Assert::contains('DesignTemplates', $access, 'Catalogue access needs the template-design permission.');
Assert::contains('enabledSourceEntityTypeList', $configuredPolicy, 'Source allow configuration is missing.');
Assert::contains('disabledSourceEntityTypeList', $configuredPolicy, 'Source deny configuration is missing.');
Assert::contains('INTERNAL_ENTITY_TYPE_LIST', $policy, 'Internal scopes need an explicit exclusion policy.');
Assert::contains('Request-scoped; never shared across ACL contexts', $service, 'ACL-sensitive cache scope is undocumented.');
Assert::contains('requireEligible($nextSource', $draftService, 'Draft saves must enforce server-side entity eligibility.');
Assert::contains('data-source-setting="entityType"', $template, 'Entity source selector is missing.');
Assert::contains('new UpdateDataSourceCommand', $shell, 'Source changes must use editor history.');
Assert::contains('confirmEntitySourceChange', $shell, 'Source changes require explicit confirmation.');
echo "Phase 24 route, ACL, source policy, cache, and selector contracts passed.\n";
