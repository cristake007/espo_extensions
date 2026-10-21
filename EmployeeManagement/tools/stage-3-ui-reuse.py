"""Historical UI adaptation recipe; emits apply_patch. Do not overwrite maintained code."""
from pathlib import Path
root=Path(__file__).resolve().parents[1];base=Path('files/client/custom/modules/employee-management/src');pending=root/'pending/Holiday'/base
patch=[]
def save(path,text):patch.append('*** Add File: '+str(root/path)+'\n'+'\n'.join('+'+x for x in text.rstrip().splitlines()))
for component in ['views/modals/managed-employee-select.js','views/holiday-request/fields/managed-employee.js','views/holiday-request/fields/start-date.js','views/modals/cancellation-reason.js']:
    save(base/component,(pending/component).read_text())
text=(pending/'views/modals/managed-booking.js').read_text()
text=text.replace("const today = this.getDateTime().getToday();", "this.operationKey = `booking:${Date.now()}:${Math.random().toString(16).slice(2)}`;\n            const today = this.getDateTime().getToday();")
text=text.replace("assignedUserId: null,", "assignedUserId: this.options.managed ? null : this.getUser().id,").replace("assignedUserName: null,", "assignedUserName: this.options.managed ? null : this.getUser().get('name'),")
text=text.replace("this.formModel.entityType = 'EmployeeHolidayRequest';", "this.formModel.entityType = 'EmployeeHolidayRequest';\n            if (this.options.record) { this.formModel.set({...this.options.record}); }\n            if (!this.options.managed) { this.headerText = this.translate('EmployeeHolidayRequest', 'scopeNames'); }")
text=text.replace("const response = await Espo.Ajax.getRequest(", "if (!this.options.managed) { this.eligibleUsers = [{id:this.getUser().id, name:this.getUser().get('name')}]; return; }\n            const response = await Espo.Ajax.getRequest(",1)
text=text.replace("'EmployeeManagement/managedBookingUsers'", "'EmployeeManagement/holiday/employees'")
text=text.replace("readOnlyDisabled: true,\n                        createDisabled", "readOnlyDisabled: true,\n                        readOnly: !this.options.managed,\n                        createDisabled")
text=text.replace("'EmployeeManagement/managedBookings',\n                    {userId, dateStart, dateEnd, description},", "this.options.record ? `EmployeeManagement/holiday/${this.options.record.id}/update` : 'EmployeeManagement/holiday/create',\n                    this.options.record ? {dateStart, dateEnd, description, revision:this.options.record.accountingRevision, idempotencyKey:this.operationKey}\n                        : {userId, managed:!!this.options.managed, dateStart, dateEnd, description, idempotencyKey:this.operationKey},")
save(base/'views/modals/managed-booking.js',text)
for component in ['views/modals/balance-correction.js','views/modals/correction-history.js']:
    text=(pending/component).read_text().replace("scope='Admin'", "scope='EmployeeAdministration'").replace("'Admin'", "'EmployeeAdministration'")
    text=text.replace("'EmployeeManagement/correct'", "'EmployeeManagement/administration/profiles/correct'")
    text=text.replace("this.profileId = String", "this.operationKey = `correction:${Date.now()}:${Math.random().toString(16).slice(2)}`;\n            this.profileId = String",1)
    start=text.find('idempotencyKey: `correction:')
    if start!=-1:
        end=text.index('`,',start)+2
        text=text[:start]+'idempotencyKey: this.operationKey,'+text[end:]
    text=text.replace('Espo.Ajax.getRequest(\n                    `EmployeeManagement/profiles/${encodeURIComponent(this.profileId)}/corrections`',"Espo.Ajax.postRequest(\n                    'EmployeeManagement/administration/profiles/corrections', {profileId:this.profileId}")
    save(base/component,text)
path=base/'views/administration/profiles.js';text=(root/path).read_text()
original=(pending/'views/admin/profiles.js').read_text()
extra=original[original.index('        actionCorrectBalance(event)'):original.rfind('    }')]
extra=extra.replace("'HolidayBalanceCorrection'", "'EmployeeHolidayBalanceCorrection'").replace("'Admin'", "'EmployeeAdministration'")
text=text.replace("'click [data-action=\"save\"]': 'actionSave',", "'click [data-action=\"save\"]': 'actionSave',\n            'click [data-action=\"correctBalance\"]': 'actionCorrectBalance',\n            'click [data-action=\"viewCorrectionHistory\"]': 'actionViewCorrectionHistory',")
text=text.replace('const canCorrectBalance = false;', "const canCorrectBalance = this.getAcl().checkScope('EmployeeHolidayBalanceCorrection');")
text=text.replace('canViewHistory: false,', 'canViewHistory: Boolean(row.isInitialized && canCorrectBalance),')
text=text[:text.rfind('    }')]+extra+text[text.rfind('    }'):]
save(path,text)
print('*** Begin Patch\n'+'\n'.join(patch)+'\n*** End Patch')
