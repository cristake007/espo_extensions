"""Read-only source mapping; emits apply_patch for the Stage 3 reuse manifest."""
from pathlib import Path
import hashlib,json
root=Path(__file__).resolve().parents[1]
php='files/custom/Espo/Modules/EmployeeManagement/';js='files/client/custom/modules/employee-management/src/'
items=[]
def record(source,destination,changes):
    items.append({'source':source,'sourceSha256':hashlib.sha256((root/source).read_bytes()).hexdigest(),'destination':destination,'adaptations':changes})
record('pending/Holiday/'+php+'Tools/HolidayBalance/HolidayBalanceService.php',php+'Tools/HolidayBalance/HolidayRequestService.php',
    'Extracted getMyBalance/getPendingDays, managed-user listing, prepare/reserve/adjust, original dates/overlap/negative-limit helpers. Existing cancellation/rejection refund formulas and validation conditions adapted into explicit transactional commands. Native Roles, regular-only, User→Profile→Request locks, revision guards, idempotent receipts; positive active overlap states (P3/P5). No synchronous approval transferred (D7).')
record('pending/Holiday/'+php+'Tools/HolidayBalance/HolidayBalanceService.php',php+'Tools/HolidayBalance/HolidayProfileService.php',
    'Original correct/listCorrections methods added to Stage 2 profile service. Same delta, ledger, reason/history; native correction scope, regular eligibility, User-first lock and payload-bound retry. Existing ledger/snapshot helpers reused by request service, not duplicated.')
for name in ['WorkingDayCalculator','NonWorkingDayProvider']:
    record('pending/Holiday/'+php+'Tools/EmployeeHolidayRequest/'+name+'.php',php+'Tools/EmployeeHolidayRequest/'+name+'.php',
        'Original date parser, interval bound and no-working-days validation retained. Count now delegates normative day/hour evaluation to assigned native WTC and composes original ZileSarbatoare dates; personal absence excluded from normative source. Booking snapshot fixes computed dates/hours/month totals.' if name=='WorkingDayCalculator' else
        'Original RO ZileLibere query retained; missing dependency fails explicitly instead of silently assuming no public days.')
for component in ['views/modals/managed-booking.js','views/modals/managed-employee-select.js','views/modals/cancellation-reason.js','views/holiday-request/fields/managed-employee.js','views/holiday-request/fields/start-date.js','views/modals/balance-correction.js','views/modals/correction-history.js']:
    record('pending/Holiday/'+js+component,js+component,
        'Native fields/modal retained; namespaced endpoints, own/managed/Pending-edit options and stable idempotency key adapted where applicable. No replacement numeric/date/link widgets.')
record('pending/Holiday/'+js+'views/admin/profiles.js',js+'views/administration/profiles.js',
    'Original correction/history actions restored to Stage 2 page only for native EmployeeHolidayBalanceCorrection scope; original modals reused.')
record('pending/Holiday/'+js+'views/dashlets/holiday-approvals.js',js+'views/holiday-request/index.js',
    'Original private queue row content, dates, escaped description/reason and decision UX used as basis for a thin regular-view workspace adapter. Own requests and queue share existing modals; native confirmations, scope-gated controls. Pending approval intentionally absent until Jobs; no Calendar override.')
text=json.dumps(items,ensure_ascii=False,indent=2)+'\n'
print('*** Begin Patch\n*** Add File: '+str(root/'STAGE-3-REUSE.json')+'\n'+'\n'.join('+'+x for x in text.splitlines())+'\n*** End Patch')
