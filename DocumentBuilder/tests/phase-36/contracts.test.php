<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$loader = new FixtureLoader("$module/Resources");
$template = $loader->json('metadata/entityDefs/DocumentBuilderTemplate.json');
$version = $loader->json('metadata/entityDefs/DocumentBuilderTemplateVersion.json');
$templateRelationships = $loader->json('layouts/DocumentBuilderTemplate/relationships.json');
$versionRelationships = $loader->json('layouts/DocumentBuilderTemplateVersion/relationships.json');
$access = file_get_contents("$module/Classes/Acl/DocumentBuilderDocumentAccessChecker.php");
$createHook = file_get_contents("$module/Classes/Record/Hooks/DocumentBuilderDocument/BeforeCreate.php");
$updateHook = file_get_contents("$module/Classes/Record/Hooks/DocumentBuilderDocument/BeforeUpdate.php");
$install = file_get_contents("$root/scripts/AfterInstall.php");

Assert::same('DocumentBuilderDocument', $template['links']['generatedDocuments']['entity'] ?? null, 'Template history inverse link is missing.');
Assert::same('template', $template['links']['generatedDocuments']['foreign'] ?? null, 'Template history inverse is not canonical.');
Assert::same('templateVersion', $version['links']['generatedDocuments']['foreign'] ?? null, 'Version history inverse is not canonical.');
Assert::same(['versions', 'generatedDocuments'], $templateRelationships, 'Template generated history panel is missing.');
Assert::same(['generatedDocuments'], $versionRelationships, 'Version generated history panel is missing.');
Assert::contains('ActionPermission::DeleteGeneratedDocuments', $access, 'Deletion does not require the dedicated permission.');
Assert::contains('return false;', $access, 'Direct record creation is not denied by ACL.');
Assert::contains('can only be created by the generation service', $createHook, 'Native CRUD can create false history.');
Assert::contains('DocumentHistoryPolicy::IMMUTABLE_AFTER_SUCCESS', $updateHook, 'Record updates bypass immutable provenance policy.');
Assert::contains("'DocumentBuilderTemplate', 'DocumentBuilderDocument'", $install, 'Generated-document navigation is missing.');

echo "Phase 36 inverse links, ACL, hooks, and navigation contracts passed.\n";
