"""Emit native metadata changes for stage 2."""
from pathlib import Path
import json
root=Path(__file__).resolve().parents[1]
base=root/'files/custom/Espo/Modules/EmployeeManagement/Resources/metadata'
out={}
def put(path,obj): out[str(path)]=json.dumps(obj,ensure_ascii=False,indent=4)+'\n'
scopes=['EmployeeAttendanceSettings','EmployeeHolidaySettings','EmployeeHolidayProfileManagement','EmployeeHolidayApproval']
for scope in scopes:
    put(base/'scopes'/f'{scope}.json',{'entity':False,'object':False,'acl':'boolean','aclPortal':False,'tab':False,'module':'EmployeeManagement'})
data=json.loads((base/'app/acl.json').read_text())
data['strictDefault']['scopeLevel'].update({s:False for s in scopes})
put(base/'app/acl.json',data)
data=json.loads((base/'app/clientRoutes.json').read_text())
data['EmployeeAdministration/:section']={'params':{'controller':'employee-management:controllers/workspace','action':'section'},'order':-101}
put(base/'app/clientRoutes.json',data)
data=json.loads((base/'entityDefs/EmployeeHolidayLedger.json').read_text())
data['fields']['operationHash']={'type':'varchar','maxLength':64,'readOnly':True}
put(base/'entityDefs/EmployeeHolidayLedger.json',data)
attendance={'editablePastMonths':{'type':'int','viewType':'int','required':True,'min':0,'max':120},
    'allowIncompleteExports':{'type':'bool','viewType':'bool'},'archiveStartMonth':{'type':'varchar','viewType':'varchar','readOnly':True}}
holiday={
    'employeeHolidayAnnualEntitlementDays':{'type':'int','viewType':'int','required':True,'min':1},
    'employeeHolidayResetDate':{'type':'date','viewType':'date','required':True},
    'employeeHolidayAutomaticAnnualGrantEnabled':{'type':'bool','viewType':'bool','readOnly':True},
    'employeeHolidayCarryOverLimitDays':{'type':'int','viewType':'int','required':True,'min':0},
    'employeeHolidayNegativeBalanceLimitDays':{'type':'int','viewType':'int','required':True,'max':0},
    'employeeHolidayApproversIds':{'type':'multiEnum','viewType':'multi-enum','required':True,'maxCount':2},
}
for n in ['1Title','1Name','2Title','2Name']:
    holiday['employeeHolidayApprovalBlock'+n]={'type':'varchar','viewType':'varchar','maxLength':150}
put(base/'app/employeeManagement.json',{'settings':{'attendance':attendance,'holiday':holiday}})
configKeys=list(holiday)+['employeeHolidayApproversNames','employeeAttendanceEditablePastMonths','employeeAttendanceAllowIncompleteExports','employeeAttendanceArchiveStartMonth']
put(base/'app/config.json',{'params':{name:{'level':'admin'} for name in configKeys}})
labels={'en_US':{'attendance':'Attendance settings','holiday':'Holiday settings','profiles':'Holiday profiles'},'ro_RO':{'attendance':'Configurări pontaj','holiday':'Configurări concedii','profiles':'Profiluri concedii'}}
fields={'editablePastMonths':['Editable past months','Luni anterioare editabile'],'allowIncompleteExports':['Allow incomplete exports','Permite exporturi incomplete'],
'archiveStartMonth':['Archive start month (read-only)','Prima lună a arhivei (numai citire)'],
'employeeHolidayAnnualEntitlementDays':['Annual entitlement (days)','Drept anual (zile)'],
'employeeHolidayResetDate':['Annual reset date','Data resetării anuale'],
'employeeHolidayAutomaticAnnualGrantEnabled':['Automatic grants (not enabled yet)','Acordări automate (încă inactive)'],
'employeeHolidayCarryOverLimitDays':['Accumulated balance warning threshold','Prag avertizare sold acumulat'],
'employeeHolidayNegativeBalanceLimitDays':['Negative balance limit','Limită sold negativ'],
'employeeHolidayApproversIds':['Approvers (routing only, native Role required)','Aprobatori (rutare, necesită rol nativ)']}
for n in ['1','2']:
    fields['employeeHolidayApprovalBlock'+n+'Title']=[f'Approval block {n}: title',f'Bloc aprobare {n}: titlu']
    fields['employeeHolidayApprovalBlock'+n+'Name']=[f'Approval block {n}: name',f'Bloc aprobare {n}: nume']
for i,lang in enumerate(['en_US','ro_RO']):
    path=base.parent/'i18n'/lang/'EmployeeAdministration.json'
    data=json.loads(path.read_text())
    data.setdefault('labels',{}).update(labels[lang])
    data.setdefault('fields',{}).update({k:v[i] for k,v in fields.items()})
    put(path,data)
print('*** Begin Patch')
for path,text in out.items():
    print('*** Add File: '+path)
    print('\n'.join('+'+line for line in text.splitlines()))
print('*** End Patch')
