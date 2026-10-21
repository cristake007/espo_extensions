"""Emit native metadata activation patch for Attendance 0.4.0."""
from pathlib import Path
import json
root=Path(__file__).resolve().parents[1];base=Path('files/custom/Espo/Modules/EmployeeManagement/Resources');patch=[]
def read(path):return json.loads((root/path).read_text())
def save(path,value):patch.append('*** Add File: '+str(root/path)+'\n'+'\n'.join('+'+x for x in json.dumps(value,ensure_ascii=False,indent=4).splitlines()))
for scope in ['EmployeeAttendance','EmployeeAttendanceOverview','EmployeeAttendanceManagement']:
    save(base/'metadata/scopes'/f'{scope}.json',read(base/'metadata/scopes/EmployeeHolidayBooking.json'))
acl=read(base/'metadata/app/acl.json')
for scope in ['EmployeeAttendance','EmployeeAttendanceOverview','EmployeeAttendanceManagement','EmployeeAttendanceMonth','EmployeeAttendanceWorkSchedule']:acl['strictDefault']['scopeLevel'][scope]=False
save(base/'metadata/app/acl.json',acl)
scope=read(base/'metadata/scopes/EmployeeAttendanceRecord.json');scope.update({'tab':False,'type':'Base','stream':False})
save(base/'metadata/scopes/EmployeeAttendanceMonth.json',scope)
for name in ['aclDefs','recordDefs','clientDefs']:save(base/'metadata'/name/'EmployeeAttendanceMonth.json',read(base/'metadata'/name/'EmployeeAttendanceRecord.json'))
fields={name:{'type':kind,'readOnly':True} for name,kind in [('name','varchar'),('month','varchar'),('norms','jsonObject'),('policies','jsonObject'),('frozen','bool'),('frozenAt','datetime'),('frozenBy','link'),('revision','int'),('createdAt','datetime'),('modifiedAt','datetime')]}
fields['norms']['dbType']='longtext';fields['policies']['dbType']='longtext'
fields['frozen']['audited']=True
save(base/'metadata/entityDefs/EmployeeAttendanceMonth.json',{'fields':fields,'links':{'frozenBy':{'type':'belongsTo','entity':'User'}},'indexes':{'monthUnique':{'unique':True,'columns':['month']}},'collection':{'orderBy':'month','order':'desc'}})
defs=read(base/'metadata/entityDefs/EmployeeAttendanceRecord.json')
for name in ['date','status','source','markedAt']:defs['fields'][name]['audited']=True
defs['fields']['revision']={'type':'int','readOnly':True,'default':0}
defs['fields']['correctionReason']={'type':'text','readOnly':True,'audited':True}
save(base/'metadata/entityDefs/EmployeeAttendanceRecord.json',defs)
routes=read(base/'routes.json')
# Specific native export actions must precede the operation placeholder.
for op,name in [('pdf','PostAttendancePdf'),('xlsx','PostAttendanceXlsx')]:
    routes.append({'route':'/EmployeeManagement/attendance/'+op,'method':'post','actionClassName':'Espo\\Modules\\EmployeeManagement\\Tools\\Attendance\\Api\\'+name})
for method in ['get','post','put']:routes.append({'route':'/EmployeeManagement/attendance/:operation','method':method,'actionClassName':r'Espo\Modules\EmployeeManagement\Tools\Attendance\Api\Attendance'})
save(base/'routes.json',routes)
routes=read(base/'metadata/app/clientRoutes.json')
for route,controller in [('EmployeeAttendance','attendance'),('EmployeeAttendance/index/month=:month','attendance'),('EmployeeAttendanceOverview','attendance-overview')]:
    routes[route]={'params':{'controller':'employee-management:controllers/'+controller,'action':'index'},'order':-103}
save(base/'metadata/app/clientRoutes.json',routes)
save(base/'metadata/app/client.json',{'cssList':['__APPEND__','client/custom/modules/employee-management/css/attendance.css']})
for lang in ['en_US','ro_RO']:
    defs=read(base/'i18n'/lang/'Global.json');defs['scopeNames'].update({'EmployeeAttendance':'Attendance — Own entries','EmployeeAttendanceOverview':'Attendance — Overview','EmployeeAttendanceManagement':'Attendance — Management','EmployeeAttendanceWorkSchedule':'Attendance — Remote / CIC','EmployeeAttendanceMonth':'Attendance — Historical norm (internal)'})
    save(base/'i18n'/lang/'Global.json',defs)
    defs=read(base/'i18n'/lang/'EmployeeAdministration.json');defs.setdefault('labels',{})['attendanceMetadata']='Attendance — Remote / CIC';save(base/'i18n'/lang/'EmployeeAdministration.json',defs)
defs=read(Path('manifest.json'));defs['version']='0.4.0';defs['description']='Employee Management: native WTC Attendance, historical norms, monthly freeze, audited corrections, reminders and exports; Holiday approval awaits Jobs. Not production-ready.';save(Path('manifest.json'),defs)
print('*** Begin Patch\n'+'\n'.join(patch)+'\n*** End Patch')
