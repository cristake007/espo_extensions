<?php
declare(strict_types=1);
namespace Espo\ORM { interface Entity {public function getEntityType():string;public function get(string $name):mixed;} }
namespace {
use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityFieldPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourcePolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\RelationshipDepthLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\DirectEntityQueryPlanner;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\DirectVariableCollector;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolutionAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolutionResult;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedEntityQueryPlanner;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedVariableCollector;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\ResolvedEntityValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\SourceProvenance;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftRecordAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewMode;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRateLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRateLimitExceeded;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRequest;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewRevisionConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\PreviewTemplateStore;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\SamplePreviewResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview\TypeAwareSampleGenerator;
use Espo\ORM\Entity;
require dirname(__DIR__).'/bootstrap.php';$root=dirname(__DIR__,2);$module="$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder";
foreach(['Security/PermissionDenied.php','DataSource/Variable/VariableSource.php','DataSource/Variable/VariableType.php','DataSource/Variable/VariableUsage.php','DataSource/Variable/VariablePath.php','DataSource/Variable/VariableIdentity.php','DataSource/Variable/VariableValueState.php','DataSource/Variable/VariableValueType.php','DataSource/Variable/VariableValue.php','DataSource/EntityCatalogue/EntityCatalogueMetadata.php','DataSource/EntityCatalogue/EntityFieldPolicy.php','DataSource/EntityCatalogue/EntitySourcePolicy.php','DataSource/EntityCatalogue/RelationshipDepthLimit.php','DataSource/EntityResolver/EntityResolver.php','DataSource/EntityResolver/EntityResolutionAccess.php','DataSource/EntityResolver/DirectVariableReference.php','DataSource/EntityResolver/DirectVariableCollector.php','DataSource/EntityResolver/DirectFieldResolution.php','DataSource/EntityResolver/DirectEntityQueryPlan.php','DataSource/EntityResolver/DirectEntityQueryPlanner.php','DataSource/EntityResolver/RelatedLinkStep.php','DataSource/EntityResolver/RelatedPathPlan.php','DataSource/EntityResolver/RelatedVariableCollector.php','DataSource/EntityResolver/RelatedEntityQueryPlanner.php','DataSource/EntityResolver/SourceProvenance.php','DataSource/EntityResolver/ResolvedEntityValue.php','DataSource/EntityResolver/EntityResolutionResult.php','Draft/DraftRecordAccess.php','Preview/PreviewMode.php','Preview/PreviewRequest.php','Preview/PreviewRateLimit.php','Preview/PreviewRateLimitExceeded.php','Preview/PreviewRevisionConflict.php','Preview/PreviewTemplateStore.php','Preview/PreviewTemplateNotFound.php','Preview/PreviewValueOrigin.php','Preview/PreviewValue.php','Preview/PreviewResult.php','Preview/TypeAwareSampleGenerator.php','Preview/SamplePreviewResolver.php','Preview/PreviewService.php']as$f)require"$module/$f";
final readonly class E implements Entity{public function __construct(private string$t,private array$v){}public function getEntityType():string{return$this->t;}public function get(string$n):mixed{return$this->v[$n]??null;}}
final class Store implements PreviewTemplateStore{public int$calls=0;public function __construct(public ?Entity$entity){}public function find(string$id):?Entity{$this->calls++;return$this->entity;}}
final class Access implements DraftRecordAccess{public int$calls=0;public function requireEdit(Entity$e):void{$this->calls++;}public function requireSpreadsheetSource():void{}}
final class Limit implements PreviewRateLimit{public int$calls=0;public bool$deny=false;public function consume(string$id,PreviewMode$m):void{$this->calls++;if($this->deny)throw new PreviewRateLimitExceeded();}}
final class EntityValues implements EntityResolver{public array$layouts=[];public function resolve(array$l,string$id):EntityResolutionResult{$this->layouts[]=$l;$identity=VariableIdentity::fromArray($l['sections'][0]['content'][0]['identity']);return new EntityResolutionResult([new ResolvedEntityValue($identity,new VariableValue(VariableValueType::Text,VariableValueState::Forbidden),new SourceProvenance('Contact',$id,'name'))]);}}
final readonly class Meta implements EntityCatalogueMetadata{public function scopeDefinitions():array{return[];}public function hasEntityDefinition(string$e):bool{return$e==='Contact';}public function isCustom(string$e):bool{return false;}public function entityDefinition(string$e):array{return['fields'=>['name'=>['type'=>'varchar']],'links'=>[]];}}
final readonly class SourcePolicy implements EntitySourcePolicy{public function allows(string$e):bool{return true;}}
final readonly class Depth implements RelationshipDepthLimit{public function get():int{return 2;}}
final readonly class ResolutionAccess implements EntityResolutionAccess{public function canReadScope(string$e):bool{return true;}public function canReadRecord(Entity$e):bool{return true;}public function canReadField(string$e,string$f):bool{return true;}public function canReadLink(string$e,string$l):bool{return true;}}
$samples=new TypeAwareSampleGenerator();foreach(VariableValueType::cases()as$type){$value=$samples->generate($type);Assert::same($type,$value->type,"Sample type {$type->value} changed.");Assert::same(VariableValueState::Present,$value->state,"Sample state {$type->value} changed.");}
$layout=['dataSource'=>['type'=>'entity','entityType'=>'Contact'],'sections'=>[['content'=>[['type'=>'variable','identity'=>['source'=>'entity','type'=>'direct','entityType'=>'Contact','path'=>['name']]]]]]];
$resolutionAccess=new ResolutionAccess();$sampleResolver=new SamplePreviewResolver(new DirectVariableCollector(),new DirectEntityQueryPlanner(new Meta(),new EntityFieldPolicy(),$resolutionAccess),new RelatedVariableCollector(),new RelatedEntityQueryPlanner(new Meta(),new EntityFieldPolicy(),new SourcePolicy(),new Depth(),$resolutionAccess),$samples);
$store=new Store(new E('DocumentBuilderTemplate',['status'=>'Draft','revision'=>7,'currentDraftLayout'=>$layout]));$access=new Access();$limit=new Limit();$entities=new EntityValues();$service=new PreviewService($store,$access,$limit,$sampleResolver,$entities);
$sample=$service->preview('template1',new PreviewRequest(7,PreviewMode::Sample));Assert::same('sample',$sample->toArray()['values'][0]['origin'],'Sample origin changed.');Assert::same('Exemplu',$sample->values[0]->value->value,'Stored draft did not drive sample path.');
$real=$service->preview('template1',new PreviewRequest(7,PreviewMode::Record,'contact1'));$realValue=$real->toArray()['values'][0];Assert::same('real',$realValue['origin'],'Real origin changed.');Assert::same('restricted',$realValue['state'],'Restricted real-record distinction changed.');Assert::same(null,$realValue['value'],'Restricted real value leaked.');Assert::same($layout,$entities->layouts[0],'Real preview did not derive paths from stored draft.');
Assert::throws(fn()=>$service->preview('template1',new PreviewRequest(6,PreviewMode::Sample)),PreviewRevisionConflict::class,'Stale draft revision was accepted.');
$calls=$store->calls;$limit->deny=true;Assert::throws(fn()=>$service->preview('template1',new PreviewRequest(7,PreviewMode::Sample)),PreviewRateLimitExceeded::class,'Rate-limit denial was ignored.');Assert::same($calls,$store->calls,'Rate limit ran after template access.');
Assert::throws(fn()=>new PreviewRequest(7,PreviewMode::Record,'../query'),InvalidArgumentException::class,'Arbitrary record selector was accepted.');
echo"Phase 30 sample, real-record, stale-revision, server-derived-path, and rate-limit tests passed.\n";
}
