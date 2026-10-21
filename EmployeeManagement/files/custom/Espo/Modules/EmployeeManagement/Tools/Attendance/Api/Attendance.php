<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\Attendance\Api;
use Espo\Core\Api\{Action,Request,Response,ResponseComposer};
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EmployeeManagement\Tools\Attendance\{AttendanceService,AttendanceOverviewService,AttendanceSettingsService,AttendanceAccessChecker};
use Espo\Modules\EmployeeManagement\Tools\Administration\AdministrationAccessChecker;

final class Attendance implements Action
{
    public function __construct(private AttendanceService $mine,private AttendanceOverviewService $overview,
        private AttendanceSettingsService $settings,private AttendanceAccessChecker $access,private AdministrationAccessChecker $keys) {}
    public function process(Request $request): Response
    {
        $this->access->assertActor();$op=$request->getRouteParam('operation');
        if(strtoupper($request->getMethod())==='GET'){
            $month=$request->getQueryParam('month');if($month!==null && !is_string($month)){throw new BadRequest('Invalid month.');}
            return ResponseComposer::json(match($op){
                'myAttendance'=>$this->mine->getMine($month),'overview'=>$this->overview->getOverview($month),
                'schedules'=>$this->overview->getScheduleSettings(),'settings'=>$this->settings->get(),
                default=>throw new BadRequest('Unknown Attendance query.'),
            });
        }
        $data=$request->getParsedBody()??(object)[];
        if(!$data instanceof \stdClass){throw new BadRequest('Object required.');}
        $allowed=match($op){
            'mark'=>['date','status','revision'],'managerMark'=>['userId','date','status','reason','revision'],
            'managerMarkRoutine'=>['userId','date','status','revision'],
            'schedule'=>['userId','remote','cic','cicStartDate','cicEndDate'],'remind'=>['month'],
            'initialize'=>[],'settings'=>['editablePastMonths','allowIncompleteExports'],
            default=>throw new BadRequest('Unknown Attendance operation.'),
        };
        $this->keys->assertKeys($data,$allowed);
        foreach(['date','status','userId','reason','month'] as $key){if(property_exists($data,$key) && (!is_string($data->$key)||strlen($data->$key)>2000)){throw new BadRequest('Invalid '.$key);}}
        if(property_exists($data,'revision') && (!is_int($data->revision)||$data->revision<0)){throw new BadRequest('Invalid revision.');}
        if(in_array($op,['mark','managerMark','managerMarkRoutine'],true) && (!isset($data->date,$data->status)||($op!=='mark'&&!isset($data->userId))||($op==='managerMark'&&!isset($data->reason)))){throw new BadRequest('Attendance fields required.');}
        if($op==='schedule'){
            if(!is_string($data->userId??null)||!is_bool($data->remote??null)||!is_bool($data->cic??null)){throw new BadRequest('Operational metadata fields required.');}
            foreach(['cicStartDate','cicEndDate'] as $key){if(isset($data->$key)&&(!is_string($data->$key)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$data->$key))){throw new BadRequest('Invalid CIC date.');}}
        }
        return ResponseComposer::json(match($op){
            'mark'=>$this->mine->mark($data->date,$data->status,$data->revision??null),
            'managerMark'=>$this->overview->markForUser($data->userId,$data->date,$data->status,$data->reason,$data->revision??null),
            'managerMarkRoutine'=>$this->overview->markForUserRoutine($data->userId,$data->date,$data->status,$data->revision??null),
            'schedule'=>$this->overview->saveSchedule($data->userId,$data->remote,$data->cic,$data->cicStartDate??null,$data->cicEndDate??null),
            'remind'=>$this->overview->sendReminders($data->month??null),
            'initialize'=>$this->settings->initializeArchive(),
            'settings'=>$this->settings->update($data),
            default=>throw new BadRequest('Unsupported Attendance operation.'),
        });
    }
}
