"""Emit provenance for Attendance source integrations, without modifying original files."""
from pathlib import Path
import json,hashlib
root=Path(__file__).resolve().parents[1];php='files/custom/Espo/Modules/EmployeeManagement/';js='files/client/custom/modules/employee-management/';items=[]
def add(source,destination,changes):items.append({'source':source,'sourceSha256':hashlib.sha256((root/source).read_bytes()).hexdigest(),'destination':destination,'adaptations':changes})
for name,changes in {
 'AttendanceOverviewService':'Original matrix assembly, counters, missing-user lists, metadata validation, schedule map, reminders and date policies retained/adapted. WTC monthly norms replace fixed weekdays/times; shared aggregate supplies own view/exports/freeze. One atomic write path with User→Month→Record locks, current revision, manager reason/audit. CO > CIC except MedicalLeave; CancellationPending effective. Native read/write/export scopes are distinct.',
 'AttendanceService':'Original personal DTO, statuses, editing window and UI flow preserved. Original marking and classification are consolidated in the existing Overview service to avoid a second WTC/CIC/freeze engine; service delegates commands and projects only the current user from the private server aggregate.',
 'AttendanceAccessChecker':'Native scopes and field permissions replace admin fallback. Regular/active actors, own/all read, overview vs management, metadata and native export permission; no automatic grants.',
 'ApprovedHolidayProvider':'Original read-only date projection extended to batch employees and Approved/CancellationPending; no Holiday accounting, approval or status mutations duplicated.',
}.items():add('pending/Attendance/'+php+'Tools/Attendance/'+name+'.php',php+'Tools/Attendance/'+name+'.php',changes)
for name in ['PostAttendancePdf','PostAttendanceXlsx']:
 add('pending/Attendance/'+php+'Tools/Attendance/Api/'+name+'.php',php+'Tools/Attendance/Api/'+name+'.php','Original generator response format, filenames/base64 and completeness check retained; native export and record/field ACL added. Current aggregate is read each time, never a cached downloaded file.')
for name in ['AttendancePdfGenerator','AttendanceXlsxGenerator']:
 add('../AttendanceManagement/files/custom/Espo/Modules/AttendanceManagement/Tools/Attendance/'+name+'.php',php+'Tools/Attendance/'+name+'.php','Previously mechanically imported renderer retained. Only per-cell schedule selection changed to derived WTC daily intervals, with legacy DTO fallback for regression; no rewrite of rendering, formatting, Remote/CIC/CO/CM or libraries.')
for name in ['my','overview','schedules']:
 add('pending/Attendance/'+js+'src/views/attendance/'+name+'.js',js+'src/views/attendance/'+name+'.js','Original page/table/actions/fields retained; own routes, backend capabilities, revision/reason commands, CIC MedicalLeave control, frozen state and native Roles. Schedules is now operational Remote/CIC only; technical WTC time editor omitted under approved D6. Existing global policies/archive are in delegated administration, not duplicated here.')
for name in ['attendance','attendance-overview']:
 add('pending/Attendance/'+js+'src/controllers/'+name+'.js',js+'src/controllers/'+name+'.js','Original view wiring, namespace/route adapted and regular/active native scope guard added. Native monthly route uses a full :month path segment.')
add('pending/Attendance/'+js+'css/attendance.css',js+'css/attendance.css','Original CSS reused unchanged through native app.client.cssList; no core/theme overwrite.')
add('pending/Attendance/'+php+'Resources/metadata/entityDefs/EmployeeAttendanceWorkSchedule.json',php+'Resources/metadata/entityDefs/EmployeeAttendanceWorkSchedule.json','Operational Remote/CIC/date metadata retained with assignedUser ownership and native audit; start/end normative fields deliberately not registered (D6). Native field edit ACL can govern commands; generic CRUD remains closed.')
add('../AttendanceManagement/files/custom/Espo/Modules/AttendanceManagement/Resources/metadata/entityDefs/AttendanceRecord.json',php+'Resources/metadata/entityDefs/EmployeeAttendanceRecord.json','Native daily record reused, now audited status/source/date/markedAt plus reason/revision. Status uses native field edit ACL while record service guards and entity checker still deny generic writes.')
add('pending/Attendance/'+php+'Tools/Attendance/AttendanceSettingsService.php',php+'Tools/Attendance/AttendanceSettingsService.php','Original initializeArchive method restored behind delegated native settings permission, retaining current-month visibility behavior and all data. Existing Stage 2 settings retained.')
add('pending/Holiday/'+js+'src/views/modals/cancellation-reason.js',js+'src/views/modals/attendance-correction.js','Existing native reason-modal pattern reused for mandatory attendance correction reason; labels/header adapted, no new widget framework.')
text=json.dumps(items,ensure_ascii=False,indent=2)
print('*** Begin Patch\n*** Add File: '+str(root/'STAGE-4-REUSE.json')+'\n'+'\n'.join('+'+x for x in text.splitlines())+'\n*** End Patch')
