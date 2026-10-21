"""Emit a read-only, hash-based inventory of every original runtime/lifecycle file."""
from pathlib import Path
import hashlib,json
root=Path(__file__).resolve().parents[1];repo=root.parent
legacy=json.loads((root/'REUSE-MAP.json').read_text())
by_source={i['source']:i for i in legacy}
by_destination={i['destination']:i for i in legacy}
stage=json.loads((root/'STAGE-2-REUSE.json').read_text())
def integrate(items):
    integrated=[]
    for item in items:
        source=item['source'];old=by_destination.get(source)
        integrated.append({**item,'original':old['source'] if old else (root/source).resolve().relative_to(repo).as_posix(),
            'destinationSha256':hashlib.sha256((root/item['destination']).read_bytes()).hexdigest()})
    return integrated
integrated=integrate(stage)
integrated3=integrate(json.loads((root/'STAGE-3-REUSE.json').read_text())) if (root/'STAGE-3-REUSE.json').is_file() else []
integrated4=integrate(json.loads((root/'STAGE-4-REUSE.json').read_text())) if (root/'STAGE-4-REUSE.json').is_file() else []
integrated5=integrate(json.loads((root/'STAGE-5-REUSE.json').read_text())) if (root/'STAGE-5-REUSE.json').is_file() else []
integrated6=integrate(json.loads((root/'STAGE-6-REUSE.json').read_text())) if (root/'STAGE-6-REUSE.json').is_file() else []
integrated7=integrate(json.loads((root/'STAGE-7-REUSE.json').read_text())) if (root/'STAGE-7-REUSE.json').is_file() else []
inventory=[]
for extension in ['AttendanceManagement','HolidayManagement']:
    for directory in ['files','scripts']:
        for path in sorted((repo/extension/directory).rglob('*')):
            if not path.is_file():continue
            source=path.relative_to(repo).as_posix();mapped=by_source.get(source)
            destinations=list(dict.fromkeys(([mapped['destination']] if mapped else [])+[i['destination'] for i in integrated+integrated3+integrated4+integrated5+integrated6+integrated7 if i['original']==source]))
            reason='Preserved in original source and imported copy; activation follows the approved incremental stages.'
            if not destinations:
                if path.suffix=='.docx':
                    destinations=['files/custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx'];reason='Byte-identical template preserved.'
                elif directory=='scripts':reason='Original lifecycle scripts preserved in source, not executed by the new package. Native metadata lifecycle replaces legacy global navigation mutations (D10/P10); Jobs remain pending.'
                elif 'entityDefs/Settings.json' in source:reason='Field declarations reused selectively for delegated settings; global Settings registration intentionally not installed (D3).'
                elif '/src/views/settings/' in source:reason='Legacy global tab editor adapter not installed; no global tabList override (P10). Original source preserved.'
                else:reason='Global Admin/Calendar/menu/mandatory-ACL registration intentionally not installed under the approved D3/P2/P6/P11 decisions; original source preserved.'
            inventory.append({'original':source,'originalSha256':hashlib.sha256(path.read_bytes()).hexdigest(),'preservedAt':destinations,'reason':reason})
data={'stage':json.loads((root/'manifest.json').read_text())['version'],'originalVersions':{'AttendanceManagement':'1.11.0','HolidayManagement':'1.9.4'},'integratedStage2':integrated,'integratedStage3':integrated3,'integratedStage4':integrated4,'integratedStage5':integrated5,'integratedStage6':integrated6,'integratedStage7':integrated7,'originalRuntimeAndLifecycleFiles':inventory,
      'newIntegrationComponentsStage5':[
          {'file': 'files/custom/Espo/Modules/EmployeeManagement/Tools/Attendance/HolidayAttendanceGuard.php', 'reason': 'D8 cross-component ORM check over existing Attendance statuses, called under native User/month transaction locks. No accounting, workflow or API endpoint.'},
          {'file': 'files/custom/Espo/Modules/EmployeeManagement/Tools/Attendance/AttendanceMonthService.php', 'baseVersion': '0.4.0', 'reason': 'Extend the existing immutable month result with sorted range locking, slicing for Holiday snapshots, and observable revisions. Reuses the WTC calculator; never configures or duplicates a schedule.'}],
      'newIntegrationComponentsStage6':[
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayApprovalService.php','reason':'Necessary native Job orchestration and durable manifest/fencing for P3/P8; does not own accounting, implement a queue, or replace the original DOCX renderer.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Jobs/GenerateHolidayApproval.php','reason':'Thin Espo\\Core\\Job\\Job entrypoint; native group/scheduler/daemon, no custom runner.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/entityAcl/EmployeeHolidayRequest.json','reason':'Native forbidden field restrictions protect staging documents and operation internals without an Attachment ACL or core override.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Tools/Attendance/AttendanceMonthService.php','reason':'Existing Stage4/5 month state: explicit locking projection avoids foreign frozenBy User locks on MariaDB; norms/freeze rules unchanged.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Tools/Attendance/HolidayAttendanceGuard.php','reason':'Existing Stage5 guard: lock only id/date of incompatible records, not author User joins; same predicate and validations.'}],
      'newIntegrationComponentsStage7':[
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Tools/Calendar/EmployeeAbsenceService.php','reason':'Approved minimal read projection only; synchronous source transaction and native soft-delete/restore with unique source. No accounting or second Calendar engine.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Hooks/EmployeeHolidayRequest/Absence.php','reason':'Native ORM afterSave couples projection with existing command/Job transaction, no parallel accounting hook.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Classes/Select/EligibleEmployee.php','reason':'Native mandatory AdditionalApplier excludes inactive/admin targets in all native list/Calendar reads without a User lifecycle override.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Classes/Acl/AbsenceAccessChecker.php','reason':'Reuse maintained read-only checker and native own/all semantics; eligible-target record reads, no mutation grants.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Entities/EmployeeAbsence.php','reason':'Native Event class for the approved minimal public entity; no source link, documents, description or balance.'},
          {'file':'files/custom/Espo/Modules/EmployeeManagement/Controllers/EmployeeAbsence.php','reason':'Native Record controller; existing record hooks, native entityAcl and strict defaults protect generic APIs.'}]}
text=json.dumps(data,ensure_ascii=False,indent=2)+'\n'
from difflib import unified_diff
path=root/'MIGRATION-INVENTORY.json'
if path.exists():
    diff=list(unified_diff(path.read_text().splitlines(),text.splitlines(),n=3))[2:]
    print('*** Begin Patch\n*** Update File: '+str(path)+'\n'+'\n'.join('@@' if l.startswith('@@') else l for l in diff)+'\n*** End Patch')
else:
    print('*** Begin Patch\n*** Add File: '+str(path)+'\n'+'\n'.join('+'+line for line in text.splitlines())+'\n*** End Patch')
