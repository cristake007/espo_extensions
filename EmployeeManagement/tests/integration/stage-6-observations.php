<?php
declare(strict_types=1);
if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') { throw new RuntimeException('Isolated lab only.'); }
chdir('/var/www/html'); require 'bootstrap.php';
$c=(new Espo\Core\Application())->getContainer(); $em=$c->get('entityManager'); $config=$c->get('config');
if ($config->get('version')!=='10.0.1'||$config->get('siteUrl')!=='http://127.0.0.1:18089') { throw new RuntimeException('Wrong environment.'); }
$state=json_decode(file_get_contents('/feasibility/employee-stage6-state.json'),true,512,JSON_THROW_ON_ERROR);
$jobs=[];
foreach (['self','normal','failure','crash','finalSmoke','rendererFailure','browserSelf','browserRetry'] as $name) {
    $request=$em->getEntityById('EmployeeHolidayRequest',$state[$name]); $history=[];
    foreach ($em->getRDBRepository('Job')->where(['targetId'=>$request->getId(),'className'=>Espo\Modules\EmployeeManagement\Jobs\GenerateHolidayApproval::class])->order('number')->find() as $j) {
        $row=[];
        foreach (['id','status','createdAt','executeTime','startedAt','executedAt','attempts','failedAttempts','group'] as $field) { $row[$field]=$j->get($field); }
        $row['scheduledToStartSeconds']=$row['startedAt']?strtotime($row['startedAt'])-strtotime($row['executeTime']):null;
        $history[]=$row;
    }
    $jobs[$name]=['requestStatus'=>$request->get('status'),'approvalState'=>$request->get('approvalState'),'approvalAttempts'=>$request->get('approvalAttempts'),'nativeJobs'=>$history];
}
$extensions=[];
foreach ($em->getRDBRepository('Extension')->where(['isInstalled'=>true])->find() as $e) { $extensions[$e->get('name')]=$e->get('version'); }
$triggerCount=(int)$em->getPDO()->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='employee_stage6_fault'")->fetchColumn();
$template='custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx';
$result=['version'=>$config->get('version'),'php'=>PHP_VERSION,'database'=>$em->getPDO()->query('SELECT VERSION()')->fetchColumn(),'extensions'=>$extensions,
    'nativeJobSettings'=>['daemonInterval'=>$config->get('daemonInterval'),'daemonMaxProcessNumber'=>$config->get('daemonMaxProcessNumber'),'jobRunInParallel'=>$config->get('jobRunInParallel'),'jobPeriodForNotExistingProcess'=>$config->get('jobPeriodForNotExistingProcess',300)],
    'faultTriggerCount'=>$triggerCount,'templateBackupExists'=>is_file('/feasibility/employee-stage6-template-backup.docx'),'templateSha256'=>hash_file('sha256',$template),'jobs'=>$jobs];
if ($triggerCount!==0||$result['templateBackupExists']||$result['nativeJobSettings']['jobPeriodForNotExistingProcess']!==300) { throw new RuntimeException('Laboratory fault cleanup incomplete.'); }
file_put_contents('/feasibility/employee-stage6-observations.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
