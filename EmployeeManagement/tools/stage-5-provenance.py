"""Emit targeted reuse provenance; does not edit originals, pending, or runtime."""
from pathlib import Path
import hashlib, json

root = Path(__file__).resolve().parents[1]
base = 'files/custom/Espo/Modules/EmployeeManagement/'
items = []
for source, destination, adaptations in [
    ('pending/Holiday/' + base + 'Tools/HolidayBalance/HolidayBalanceService.php',
     base + 'Tools/HolidayBalance/HolidayRequestService.php',
     'Continue the existing 0.3/0.4 extracted request, reservation, adjustment, refund, ledger, revision and receipt implementation. Add the shared daily-record conflict guard to reserve/adjust/approval, including description-only edits. Lock affected normative months in ascending order and touch month revisions for effective cancellation transitions. Normalize command key order at the service boundary for API/internal caller retry interoperability. No second accounting owner, no synchronous or provisional approval.'),
    ('pending/Attendance/' + base + 'Tools/Attendance/AttendanceOverviewService.php',
     base + 'Tools/Attendance/AttendanceOverviewService.php',
     'Continue original matrix, CIC/CCC precedence, completion, freeze, reminders and export aggregate. Remove locking record reads from the read-only aggregate so record and Holiday reads use one DB snapshot behind the month lock. Retain authoritative locking reads on the write path. Keep reminder selection and native Notification persistence in the same native transaction/month lock as cancellation. Fix demonstrated delivery defect: native Notification.setUserId instead of nonexistent assignedUserId.'),
    ('pending/Holiday/' + base + 'Tools/EmployeeHolidayRequest/WorkingDayCalculator.php',
     base + 'Tools/EmployeeHolidayRequest/WorkingDayCalculator.php',
     'Retain the original strict parser, inclusive period and 367-day validation, and the WTC/ZileSarbatoare calculator already integrated. Expose the same period validator for slicing immutable monthly results, instead of duplicating validation or introducing another normative engine.'),
]:
    items.append({'source': source, 'sourceSha256': hashlib.sha256((root/source).read_bytes()).hexdigest(),
                  'destination': destination, 'adaptations': adaptations})
text = json.dumps(items, ensure_ascii=False, indent=2) + '\n'
print('*** Begin Patch\n*** Add File: ' + str(root/'STAGE-5-REUSE.json'))
print('\n'.join('+' + line for line in text.splitlines()))
print('*** End Patch')
