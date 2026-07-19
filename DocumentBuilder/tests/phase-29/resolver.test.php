<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity { public function getEntityType(): string; public function get(string $name): mixed; }
}

namespace {
use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityFieldPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourcePolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\RelationshipDepthLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityRecordReader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolutionAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityValueMapper;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedEntityQueryPlanner;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedEntityResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedRecordReader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedVariableCollector;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\ORM\Entity;

require dirname(__DIR__) . '/bootstrap.php';
$root=dirname(__DIR__,2);$module="$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder";
foreach([
'Security/PermissionDenied.php','DataSource/Variable/VariableSource.php','DataSource/Variable/VariableType.php',
'DataSource/Variable/VariableUsage.php','DataSource/Variable/VariablePath.php','DataSource/Variable/VariableIdentity.php',
'DataSource/Variable/VariableValueState.php','DataSource/Variable/VariableValueType.php','DataSource/Variable/VariableValue.php',
'DataSource/EntityCatalogue/EntityCatalogueMetadata.php','DataSource/EntityCatalogue/EntityFieldPolicy.php',
'DataSource/EntityCatalogue/EntitySourcePolicy.php','DataSource/EntityCatalogue/RelationshipDepthLimit.php',
'DataSource/EntityResolver/EntityResolver.php','DataSource/EntityResolver/EntityResolutionAccess.php',
'DataSource/EntityResolver/EntityRecordReader.php','DataSource/EntityResolver/RelatedRecordReader.php',
'DataSource/EntityResolver/DirectFieldResolution.php','DataSource/EntityResolver/RelatedLinkStep.php',
'DataSource/EntityResolver/RelatedPathPlan.php','DataSource/EntityResolver/RelatedVariableCollector.php',
'DataSource/EntityResolver/RelatedEntityQueryPlanner.php','DataSource/EntityResolver/SourceProvenance.php',
'DataSource/EntityResolver/ResolvedEntityValue.php','DataSource/EntityResolver/EntityResolutionResult.php',
'DataSource/EntityResolver/EntityValueMapper.php','DataSource/EntityResolver/RelatedEntityResolver.php']as$f)require"$module/$f";

final readonly class E implements Entity { public function __construct(private string $t,private array $v=[]){}public function getEntityType():string{return$this->t;}public function get(string $n):mixed{return$this->v[$n]??null;} }
final class A implements EntityResolutionAccess { public array $links=[],$fields=[],$records=[];public function canReadScope(string $e):bool{return true;}public function canReadRecord(Entity $r):bool{return$this->records[spl_object_id($r)]??true;}public function canReadField(string $e,string $f):bool{return$this->fields["$e.$f"]??true;}public function canReadLink(string $e,string $l):bool{return$this->links["$e.$l"]??true;} }
final class Roots implements EntityRecordReader {public int $calls=0;public function __construct(public ?Entity $record){}public function find(string $e,string $id,array $f):?Entity{$this->calls++;return$this->record;} }
final class Relations implements RelatedRecordReader {public array $records=[],$calls=[];public function find(Entity $s,string $l,array $f):?Entity{$this->calls[]=['link'=>$l,'fields'=>$f];return$this->records[spl_object_id($s).".$l"]??null;} }
final readonly class M implements EntityCatalogueMetadata {public function __construct(private array $e){}public function scopeDefinitions():array{return[];}public function hasEntityDefinition(string $e):bool{return isset($this->e[$e]);}public function isCustom(string $e):bool{return str_starts_with($e,'C');}public function entityDefinition(string $e):array{return$this->e[$e]??[];} }
final class P implements EntitySourcePolicy {public array $allowed=[];public function allows(string $e):bool{return$this->allowed[$e]??true;} }
final readonly class D implements RelationshipDepthLimit {public function get():int{return 2;} }

$metadata=new M([
'Contact'=>['fields'=>[],'links'=>['account'=>['type'=>'belongsTo','entity'=>'Account'],'advisor'=>['type'=>'hasOne','entity'=>'CAdvisor'],'children'=>['type'=>'hasMany','entity'=>'Contact'],'parent'=>['type'=>'belongsTo','entity'=>'Contact']]],
'Account'=>['fields'=>['name'=>['type'=>'varchar']],'links'=>['owner'=>['type'=>'belongsTo','entity'=>'User']]],
'User'=>['fields'=>['name'=>['type'=>'varchar']],'links'=>['profile'=>['type'=>'hasOne','entity'=>'CProfile']]],
'CProfile'=>['fields'=>['label'=>['type'=>'varchar']]],'CAdvisor'=>['fields'=>['name'=>['type'=>'varchar','custom'=>true]]]]);
$access=new A();$policy=new P();$contact=new E('Contact');$account=new E('Account',['name'=>'Acme']);$owner=new E('User',['name'=>'Maria']);$advisor=new E('CAdvisor',['name'=>'Consultant']);
$roots=new Roots($contact);$relations=new Relations();$relations->records[spl_object_id($contact).'.account']=$account;$relations->records[spl_object_id($account).'.owner']=$owner;$relations->records[spl_object_id($contact).'.advisor']=$advisor;
$resolver=new RelatedEntityResolver(new RelatedVariableCollector(),new RelatedEntityQueryPlanner($metadata,new EntityFieldPolicy(),$policy,new D(),$access),$roots,$relations,$access,new EntityValueMapper());
$identity=static fn(array$p):array=>['source'=>'entity','type'=>'related','entityType'=>'Contact','path'=>$p];$token=static fn(array$p):array=>['type'=>'variable','identity'=>$identity($p)];
$make=static fn(array$tokens):array=>['dataSource'=>['type'=>'entity','entityType'=>'Contact'],'sections'=>[['content'=>$tokens]]];
$result=$resolver->resolve($make([$token(['account','name']),$token(['account','name']),$token(['account','owner','name']),$token(['advisor','name'])]),'contact1');
Assert::same(1,$roots->calls,'Root record query was not bounded.');Assert::same(['account','owner','advisor'],array_column($relations->calls,'link'),'Relationship prefixes were not deduplicated.');Assert::same(['id','name'],$relations->calls[0]['fields'],'Unused related fields were selected.');Assert::same(3,count($result->values),'Duplicate paths were not coalesced.');
Assert::same('Acme',$result->find(VariableIdentity::fromArray($identity(['account','name'])))?->value->value,'One-level path failed.');Assert::same('Maria',$result->find(VariableIdentity::fromArray($identity(['account','owner','name'])))?->value->value,'Two-level path failed.');Assert::same('Consultant',$result->find(VariableIdentity::fromArray($identity(['advisor','name'])))?->value->value,'Custom link failed.');
$relations->calls=[];$access->links['Contact.account']=false;$denied=$resolver->resolve($make([$token(['account','name'])]),'contact1');Assert::same(VariableValueState::Forbidden,$denied->values[0]->value->state,'Denied link marker changed.');Assert::same([],$relations->calls,'Denied link was queried.');unset($access->links['Contact.account']);
$relations->records[spl_object_id($contact).'.account']=null;$missing=$resolver->resolve($make([$token(['account','name'])]),'contact1');Assert::same(VariableValueState::Missing,$missing->values[0]->value->state,'Null/deleted link marker changed.');$relations->records[spl_object_id($contact).'.account']=$account;
$access->records[spl_object_id($account)]=false;$denied=$resolver->resolve($make([$token(['account','name'])]),'contact1');Assert::same(VariableValueState::Forbidden,$denied->values[0]->value->state,'Related record ACL leaked data.');unset($access->records[spl_object_id($account)]);
$access->fields['Account.name']=false;$before=count($relations->calls);$denied=$resolver->resolve($make([$token(['account','name'])]),'contact1');Assert::same(VariableValueState::Forbidden,$denied->values[0]->value->state,'Field ACL marker changed.');Assert::same($before,count($relations->calls),'Field-denied path was queried.');unset($access->fields['Account.name']);
$policy->allowed['User']=false;$denied=$resolver->resolve($make([$token(['account','owner','name'])]),'contact1');Assert::same(VariableValueState::Forbidden,$denied->values[0]->value->state,'Administrator-disabled target was traversed.');unset($policy->allowed['User']);
foreach([['children','name'],['parent','name'],['account','owner','profile','label']]as$p)Assert::throws(fn()=>$resolver->resolve($make([$token($p)]),'contact1'),InvalidArgumentException::class,'Collection, cycle, or excess-depth path accepted.');
echo"Phase 29 related-path ACL, depth, cycle, custom-link, and bounded-query tests passed.\n";
}
