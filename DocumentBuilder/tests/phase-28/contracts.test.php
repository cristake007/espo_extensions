<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$source = "$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityResolver";
$acl = file_get_contents("$source/AclEntityResolutionAccess.php");
$reader = file_get_contents("$source/OrmEntityRecordReader.php");
$planner = file_get_contents("$source/DirectEntityQueryPlanner.php");
$resolver = file_get_contents("$source/DirectEntityResolver.php");
$binding = file_get_contents("$root/files/custom/Espo/Modules/DocumentBuilder/Binding.php");

Assert::contains('checkScope($entityType, Table::ACTION_READ)', $acl, 'Generation-time source scope ACL is missing.');
Assert::contains('checkEntity($record, Table::ACTION_READ)', $acl, 'Generation-time record ACL is missing.');
Assert::contains('checkField($entityType, $field, Table::ACTION_READ)', $acl, 'Generation-time field ACL is missing.');
Assert::contains("->select(\$fields)", $reader, 'The ORM reader must use the bounded select plan.');
Assert::contains("'deleted' => false", $reader, 'Deleted source records must be excluded explicitly.');
Assert::contains('MAX_SELECT_FIELDS', $planner, 'The direct select plan has no hard bound.');
Assert::contains('VariableValueState::Forbidden', $resolver, 'Restricted direct values need a non-leaking marker.');
Assert::isFalse(str_contains($resolver . $planner, 'getMessage()'), 'Resolver errors must not relay internal or raw-value details.');
Assert::contains('bindImplementation(EntityResolver::class, EntityPathResolver::class)', $binding, 'The entity-path resolver is not available through dependency injection.');
Assert::contains('bindImplementation(EntityRecordReader::class, OrmEntityRecordReader::class)', $binding, 'The bounded ORM reader is not bound.');
Assert::contains('bindImplementation(EntityResolutionAccess::class, AclEntityResolutionAccess::class)', $binding, 'The resolution ACL adapter is not bound.');

echo "Phase 28 ORM, generation-time ACL, bounded-query, and redaction contracts passed.\n";
