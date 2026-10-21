"""Historical minimal UI adaptation of Attendance 1.11.0; emit patch only."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1];base=Path('files/client/custom/modules/employee-management');patch=[]
def save(path,text):patch.append('*** Add File: '+str(root/path)+'\n'+'\n'.join('+'+x for x in text.rstrip().splitlines()))
for name in ['attendance','attendance-overview']:
    old=(root/'pending/Attendance'/base/'src/controllers'/f'{name}.js').read_text()
    scope='EmployeeAttendance' if name=='attendance' else 'EmployeeAttendanceOverview'
    old=old.replace("scope: 'Attendance'", "scope: 'EmployeeAttendance'").replace("scope: 'AttendanceOverview'", "scope: 'EmployeeAttendanceOverview'")
    old=old.replace("this.main(",f"if (!this.getUser().isRegular() || !this.getUser().get('isActive') || !this.getAcl().checkScope('{scope}') || !this.getAcl().checkScope('EmployeeManagement')) {{ this.baseController.error403(); return; }}\n            this.main(")
    save(base/'src/controllers'/f'{name}.js',old)
for name in ['my','overview','schedules']:
    path=base/'src/views/attendance'/f'{name}.js';text=(root/path).read_text()
    for old,new in [('EmployeeManagement/myAttendance','EmployeeManagement/attendance/myAttendance'),('EmployeeManagement/overview/mark','EmployeeManagement/attendance/managerMark'),('EmployeeManagement/overview/remind','EmployeeManagement/attendance/remind'),('EmployeeManagement/overview/schedule','EmployeeManagement/attendance/schedule'),('EmployeeManagement/overview/','EmployeeManagement/attendance/'),('EmployeeManagement/overview','EmployeeManagement/attendance/overview'),('EmployeeManagement/mark','EmployeeManagement/attendance/mark'),('EmployeeManagement/schedules','EmployeeManagement/attendance/schedules'),('EmployeeManagement/settings/initialize','EmployeeManagement/attendance/initialize'),('EmployeeManagement/settings','EmployeeManagement/attendance/settings')]:text=text.replace(old,new)
    text=text.replace('#Attendance/index/', '#EmployeeAttendance/index/').replace("'Attendance/index/", "'EmployeeAttendance/index/")
    if name=='my':
        text=text.replace(".removeClass('attendance-state-active');", ".removeClass('attendance-state-active');\n            actions.find('[data-status=\"MedicalLeave\"]').prop('disabled', !data.todayCanMarkMedical);")
        text=text.replace('if (day.canMark) {','if (day.canMark || day.canMarkMedical) {').replace("for (const status of ['AtWork', 'BusinessTrip', 'MedicalLeave'])", "for (const status of ['AtWork', 'BusinessTrip', 'MedicalLeave'].filter(s => day.canMark || s === 'MedicalLeave'))")
        text=text.replace("{date, status});", "{date, status, revision:this.attendance.days.find(d=>d.date===date)?.revision ?? 0});")
    if name=='overview':
        text=text.replace("const scheduleLabel = schedule.startTime", "const scheduleLabel = user.normHours !== undefined ? `${user.normHours} h / WTC` : schedule.startTime")
        text=text.replace("if (cell.isFuture && !cell.status)", "if (cell.isWorkingDay === false || (cell.isFuture && !cell.status))")
        text=text.replace("if (['ApprovedHoliday', 'CicPeriod'].includes(cell.source))", "if (!cell.canManage || cell.source === 'ApprovedHoliday')")
        text=text.replace("'data-date': cell.date || '',", "'data-date': cell.date || '',\n                    'data-revision': cell.revision || 0,")
        text=text.replace("['AtWork', 'BusinessTrip', 'MedicalLeave'].forEach", "['AtWork', 'BusinessTrip', 'MedicalLeave'].filter(s => cell.source !== 'CicPeriod' || s === 'MedicalLeave').forEach")
        text=text.replace("select.prop('disabled', true);\n\n            try {", '''select.prop('disabled', true);
            const reason = await new Promise(resolve => this.createView('attendanceReason', 'employee-management:views/modals/attendance-correction', {}).then(modal => {
                this.listenToOnce(modal, 'submit', resolve); this.listenToOnce(modal, 'close', () => resolve(null)); modal.render();
            }));
            if (!reason) { select.prop('disabled', false); return; }
            try {''',1)
        text=text.replace('''                    status,
                });''','''                    status, reason, revision:Number(select.attr('data-revision')),
                });''',1)
    if name=='schedules':
        a=text.index('            <div class="attendance-settings-section attendance-manager-settings-panel">');b=text.index('            <div class="attendance-schedules-page attendance-schedule-editor">')
        text=text[:a]+text[b:]
        text=text.replace("this.wait(Promise.all([this.loadSettings(), this.loadSchedules()]));", "this.wait(this.loadSchedules());")
        a=text.index('                const startControl = this.createTimeControl(');b=text.index('                const remoteControl',a);text=text[:a]+text[b:]
        text=text.replace('                    startControl,\n                    endControl,\n','')
        a=text.index("            const startTime = row.find(");b=text.index('            const remote',a);text=text[:a]+text[b:]
        a=text.index('            if (!startTime || !endTime');b=text.index('            if (cic &&',a);text=text[:a]+text[b:]
        text=text.replace('                    startTime,\n                    endTime,\n','')
        a=text.index('        createTimeControl(');b=text.index('        createRemoteControl',a);text=text[:a]+text[b:]
        text=text.replace("scope='Admin'", "scope='EmployeeAdministration'")
    save(path,text)
path=base/'src/views/modals/attendance-correction.js';text=(root/base/'src/views/modals/cancellation-reason.js').read_text().replace('EmployeeHolidayRequest','EmployeeAdministration').replace('Cancellation Reason','Correction Reason').replace("this.options.headerLabel || 'Request Cancellation'", "'Correct Balance'").replace("this.options.submitLabel || 'Request Cancellation'", "'Save'").replace('cancellationReasonHelp','correctionReasonRequired').replace('cancellationReasonEmpty','correctionReasonRequired')
save(path,text)
print('*** Begin Patch\n'+'\n'.join(patch)+'\n*** End Patch')
