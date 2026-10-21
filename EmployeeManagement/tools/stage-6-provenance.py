"""Emit provenance for the native approval integration; never modify originals or pending."""
from pathlib import Path
import hashlib,json
root=Path(__file__).resolve().parents[1];b='files/custom/Espo/Modules/EmployeeManagement/';j='files/client/custom/modules/employee-management/';items=[]
def add(source,destination,adaptations):
    items.append({'source':source,'sourceSha256':hashlib.sha256((root/source).read_bytes()).hexdigest(),'destination':destination,'adaptations':adaptations})
add('pending/Holiday/'+b+'Tools/HolidayDocument/HolidayApprovalDocumentService.php',b+'Tools/HolidayDocument/HolidayApprovalDocumentService.php',
    'Retain original Romanian month filenames, inclusive monthly segmentation, document fields, signatures fallback, formatting and balance arithmetic. Capture values and monthly totals from reserved calculationSnapshot at decision, not current calendar/config during retry. Move tracked Attachment/FileStorage coordination to the native Job service. Renderer/template unchanged; obsolete count/current-calendar dependency removed for P8.')
add('pending/Holiday/'+b+'Tools/HolidayBalance/HolidayBalanceService.php',b+'Tools/HolidayBalance/HolidayRequestService.php',
    'Continue reused request/accounting implementation. Native grouped Job creation and immutable decision snapshot replace the old synchronous generate call; native retry command reuses the same reservation/receipts. Existing cancellation/refund/revision logic retained. Explicit locking projection excludes foreign author/decider joins after a real MariaDB deadlock; result exposes only natively authorized published documents/state.')
add('pending/Holiday/'+b+'Tools/HolidayBalance/HolidayBalanceService.php',b+'Tools/HolidayBalance/HolidayProfileService.php',
    'No financial behavior changes. Targeted explicit forUpdate projections omit author joins that otherwise lock unrelated Users in MariaDB; owner is already locked first. Existing original helpers, fields, ledger and idempotency retained.')
add('pending/Attendance/'+b+'Tools/Attendance/AttendanceOverviewService.php',b+'Tools/Attendance/AttendanceOverviewService.php',
    'Attendance behavior/UI/exports/CIC/freeze unchanged. Restrict locking User, metadata and daily-record reads to required attributes, avoiding foreign author User joins during concurrent approval jobs. Non-locking aggregate remains unchanged.')
add('pending/Holiday/'+j+'src/views/dashlets/holiday-approvals.js',j+'src/views/holiday-request/index.js',
    'Continue private list/queue adapter derived in Stage3 from the original approval dashlet and existing native modals. Activate approve and retry through native confirmation; show protected errors, use visibility-aware adaptive polling for all current list modes with faster checks only while Processing, serialize refreshes, clean up on view removal, and link published attachments through the native download entrypoint. No Calendar/Admin override.')
add('../HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/entityDefs/HolidayRequest.json',b+'Resources/metadata/entityDefs/EmployeeHolidayRequest.json',
    'Extend existing request metadata with read-only decision snapshot/manifest/job correlation, attempts/state/error and staging attachment field. Native entityAcl forbids internal fields and staging reads; generic CRUD remains closed. No operation or permission entity introduced.')
text=json.dumps(items,ensure_ascii=False,indent=2)+'\n'
print('*** Begin Patch\n*** Add File: '+str(root/'STAGE-6-REUSE.json')+'\n'+'\n'.join('+'+l for l in text.splitlines())+'\n*** End Patch')
