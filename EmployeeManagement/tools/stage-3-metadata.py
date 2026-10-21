"""Historical metadata activation recipe, emits a patch; not a runtime migration."""
from pathlib import Path
import json
root=Path(__file__).resolve().parents[1];base=Path('files/custom/Espo/Modules/EmployeeManagement/Resources');patch=[]
def save(path,value):
    path=root/path;text=json.dumps(value,ensure_ascii=False,indent=4)+'\n'
    patch.append('*** Add File: '+str(path)+'\n'+'\n'.join('+'+x for x in text.rstrip().splitlines()))
def read(path):return json.loads((root/path).read_text())
for scope in ['EmployeeHolidayBooking','EmployeeHolidayRequestManagement','EmployeeHolidayBalanceCorrection']:
    save(base/'metadata/scopes'/f'{scope}.json',read(base/'metadata/scopes/EmployeeHolidayApproval.json'))
acl=read(base/'metadata/app/acl.json')
for scope in ['EmployeeHolidayBooking','EmployeeHolidayRequestManagement','EmployeeHolidayBalanceCorrection']:acl['strictDefault']['scopeLevel'][scope]=False
save(base/'metadata/app/acl.json',acl)
routes=read(base/'routes.json')
for suffix,method in [('/:operation','get'),('/:operation','post'),('/:id/:operation','post')]:
    routes.append({'route':'/EmployeeManagement/holiday'+suffix,'method':method,'actionClassName':r'Espo\Modules\EmployeeManagement\Tools\HolidayBalance\Api\Holiday'})
save(base/'routes.json',routes)
defs=read(base/'metadata/entityDefs/EmployeeHolidayRequest.json')
for name in ['calculationSnapshot','operationReceipts']:
    defs['fields'][name]={'type':'jsonObject','readOnly':True}
defs['fields']['creationHash']={'type':'varchar','maxLength':64,'readOnly':True}
defs['fields']['accountingRevision']['audited']=True
save(base/'metadata/entityDefs/EmployeeHolidayRequest.json',defs)
routes=read(base/'metadata/app/clientRoutes.json')
routes['EmployeeManagement/holiday']={'params':{'controller':'employee-management:controllers/workspace','action':'holiday'},'order':-102}
save(base/'metadata/app/clientRoutes.json',routes)
for lang in ['en_US','ro_RO']:
    role=read(base/'i18n'/lang/'Global.json')
    role.setdefault('scopeNames',{}).update({'EmployeeHolidayBooking':'Holiday — Book own requests' if lang=='en_US' else 'Concedii — Cereri proprii',
        'EmployeeHolidayRequestManagement':'Holiday — Manage employee requests' if lang=='en_US' else 'Concedii — Gestiune cereri angajați',
        'EmployeeHolidayBalanceCorrection':'Holiday — Balance corrections' if lang=='en_US' else 'Concedii — Corecții sold'})
    save(base/'i18n'/lang/'Global.json',role)
manifest=read(Path('manifest.json'));manifest['version']='0.3.0';manifest['description']='Employee Management: delegated administration, Holiday requests, reservations, cancellations and transactional ledger. Approval awaits native Jobs/documents; not production-ready.'
save(Path('manifest.json'),manifest)
print('*** Begin Patch\n'+'\n'.join(patch)+'\n*** End Patch')
