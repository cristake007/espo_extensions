from pathlib import Path
import json,hashlib
root=Path(__file__).resolve().parents[1]
records=json.loads((root/'STAGE-2-REUSE.json').read_text())
out={}
def save(src,dst,text,treatment):
    out[str(root/dst)]=text
    records.append({'source':src,'destination':dst,'sourceSha256':hashlib.sha256((root/src).read_bytes()).hexdigest(),'treatment':treatment})
src='pending/Holiday/files/client/custom/modules/employee-management/src/views/fields/annual-reset-date.js'
save(src,'files/client/custom/modules/employee-management/src/views/fields/annual-reset-date.js',(root/src).read_text(),'Identical preserved MM-DD/date normalization; no functional rewrite.')
src='pending/Attendance/files/client/custom/modules/employee-management/src/views/admin/manager-index.js'
t=(root/src).read_text()
t=t.replace("{{translate 'Administration'}}",'Employee Management — {{translate \'Administration\'}}')
t=t.replace('<div class="col-sm-6 col-md-4">','{{#each sections}}<div class="col-sm-6 col-md-4">')
t=t.replace('                    </div>\n                </div>', '                    </div>{{/each}}\n                </div>')
t=t.replace("{{translate 'Attendance Management' category='labels' scope='Admin'}}",'{{label}}').replace("{{translate 'Attendance Settings' category='labels' scope='Admin'}}",'{{label}}')
t=t.replace('#Admin/employeeAttendanceSettings','#EmployeeAdministration/{{name}}')
t=t.replace("{{translate 'attendanceManagerSettings' category='descriptions' scope='Admin'}}",'')
t=t.replace('        afterRender() {',"        data() {\n            return {sections: this.options.sections.map(name => ({name, label: this.translate(name, 'labels', 'EmployeeAdministration')}))};\n        }\n\n        afterRender() {")
save(src,'files/client/custom/modules/employee-management/src/views/administration/index.js',t,'Preserved panel/grid view; dynamic ACL-filtered sections, own labels and EmployeeAdministration routes instead of #Admin.')
path=root/'files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/app/employeeManagement.json'
data=json.loads(path.read_text())
# Reuse original field declarations, but do not register them in global Settings.
legacy=json.loads((root.parent/'HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/entityDefs/Settings.json').read_text())['fields']
for name,defs in data['settings']['holiday'].items():
    original=name.replace('employeeHoliday','holidayManagement')
    if original in legacy:
        for key in ['default','min','max','maxLength','required']:
            if key in legacy[original]: defs[key]=legacy[original][key]
data['settings']['holiday']['employeeHolidayResetDate']['fieldView']='employee-management:views/fields/annual-reset-date'
out[str(path)]=json.dumps(data,ensure_ascii=False,indent=4)+'\n'
out[str(root/'STAGE-2-REUSE.json')]=json.dumps(records,ensure_ascii=False,indent=2)+'\n'
print('*** Begin Patch')
for path,text in out.items():
    print('*** Add File: '+path)
    print('\n'.join('+'+line for line in text.splitlines()))
print('*** End Patch')
