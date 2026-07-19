<?php
declare(strict_types=1);
use DocumentBuilder\Tests\Support\Assert;
require dirname(__DIR__) . '/bootstrap.php';
$root=dirname(__DIR__,2);$base="$root/files/custom/Espo/Modules/DocumentBuilder";$source="$base/Tools/DocumentBuilder/DataSource/EntityResolver";
$planner=file_get_contents("$source/RelatedEntityQueryPlanner.php");$resolver=file_get_contents("$source/RelatedEntityResolver.php");$acl=file_get_contents("$source/AclEntityResolutionAccess.php");$binding=file_get_contents("$base/Binding.php");
Assert::contains('SINGLE_LINK_TYPES',$planner,'Single-link allowlist is missing.');
Assert::contains('min(2, $this->depthLimit->get())',$planner,'Two-level relationship bound is missing.');
Assert::contains('$this->sourcePolicy->allows($target)',$planner,'Administrator target restrictions are not rechecked.');
Assert::contains('canReadLink($entityType, $link)',$planner,'Link ACL is not planned.');
Assert::contains('checkLink($entityType, $link, Table::ACTION_READ)',$acl,'Link ACL adapter is missing.');
Assert::contains('MAX_RELATION_QUERIES',$resolver,'Related query count is not bounded.');
Assert::contains('canReadRecord($related)',$resolver,'Related-record ACL is not rechecked.');
Assert::contains('bindImplementation(RelatedRecordReader::class, OrmRelatedRecordReader::class)',$binding,'Related ORM reader is not bound.');
echo"Phase 29 single-link, ACL, depth, policy, and query-bound contracts passed.\n";
