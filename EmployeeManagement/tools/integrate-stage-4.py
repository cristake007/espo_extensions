"""Historical source extraction recipe; emits apply_patch, never run over maintained code."""
from pathlib import Path
import json
root=Path(__file__).resolve().parents[1];base=Path('files/custom/Espo/Modules/EmployeeManagement');p=root/'pending/Attendance'/base
patch=[]
def save(path,text):patch.append('*** Add File: '+str(root/path)+'\n'+'\n'.join('+'+x for x in text.rstrip().splitlines()))
for name in ['AttendanceService','AttendanceOverviewService','ApprovedHolidayProvider']:
    text=(p/'Tools/Attendance'/f'{name}.php').read_text().replace("'userId'", "'assignedUserId'").replace("'userName'", "'assignedUserName'")
    save(base/'Tools/Attendance'/f'{name}.php',text)
for name in ['PostAttendancePdf','PostAttendanceXlsx']:
    save(base/'Tools/Attendance/Api'/f'{name}.php',(p/'Tools/Attendance/Api'/f'{name}.php').read_text())
js=Path('files/client/custom/modules/employee-management')
for name in ['my','overview','schedules']:
    save(js/'src/views/attendance'/f'{name}.js',(root/'pending/Attendance'/js/'src/views/attendance'/f'{name}.js').read_text())
save(js/'css/attendance.css',(root/'pending/Attendance'/js/'css/attendance.css').read_text())
defs=json.loads((p/'Resources/metadata/entityDefs/EmployeeAttendanceWorkSchedule.json').read_text())
for name in ['startTime','endTime']:defs['fields'].pop(name,None)
for name in ['remote','cic','cicStartDate','cicEndDate','effectiveFrom']:defs['fields'][name]['audited']=True
defs['fields']['assignedUser']=defs['fields'].pop('user');defs['fields']['assignedUser']['readOnly']=True
defs['links']['assignedUser']=defs['links'].pop('user')
for index in defs.get('indexes',{}).values():index['columns']=['assignedUserId' if x=='userId' else x for x in index['columns']]
for name in ['createdBy','modifiedBy']:
    defs['fields'][name]={'type':'link','readOnly':True};defs['links'][name]={'type':'belongsTo','entity':'User'}
save(base/'Resources/metadata/entityDefs/EmployeeAttendanceWorkSchedule.json',json.dumps(defs,indent=4))
for name in ['scopes','aclDefs','recordDefs','clientDefs']:
    source=root/base/'Resources/metadata'/name/'EmployeeAttendanceRecord.json'
    defs=json.loads(source.read_text())
    if name=='scopes':defs['tab']=False;defs['type']='Base'
    save(base/'Resources/metadata'/name/'EmployeeAttendanceWorkSchedule.json',json.dumps(defs,indent=4))
print('*** Begin Patch\n'+'\n'.join(patch)+'\n*** End Patch')
