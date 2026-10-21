"""Read-only provenance emitter for the shared Calendar and original annual processing."""
from pathlib import Path
import hashlib, json
root=Path(__file__).resolve().parents[1]
b='files/custom/Espo/Modules/EmployeeManagement/'
j='files/client/custom/modules/employee-management/'
items=[]
def add(source,destination,adaptations):
    items.append({'source':source,'sourceSha256':hashlib.sha256((root/source).read_bytes()).hexdigest(),
                  'destination':destination,'adaptations':adaptations})
add('pending/Holiday/'+b+'Tools/HolidayBalance/HolidayBalanceService.php',b+'Tools/HolidayBalance/HolidayProfileService.php',
    'Integrate original reset, processDueResets (100-year catch-up bound), applyResetGrant, nextYear, formatDays, warning threshold and Notification methods. BalanceMath unchanged: balance + entitlement, never expiry/cap. Native system-only scheduled entry, regular active targets, stable profile/due-date keys, current due-date/config recheck and operation hash under existing User/Profile locks. Resolve owner before the transaction to avoid a demonstrated MariaDB 12 RR stale-read/1020 failure; omit unnecessary joined name projection. Notification recipients require current native profile/FLS access. Existing initialization/correction logic retained.')
add('pending/Holiday/'+b+'Jobs/ProcessHolidayResets.php',b+'Jobs/ProcessEmployeeHolidayResets.php',
    'Same JobDataLess/run entry. Rename job to avoid collision with the installed legacy ProcessHolidayResets; inject the already integrated profile subset, no new runner/queue.')
add('pending/Holiday/'+b+'Resources/metadata/app/scheduledJobs.json',b+'Resources/metadata/app/scheduledJobs.json',
    'Keep original 5 0 * * * and isDefault native ScheduledJob registration; unique job/class name. The business flag remains explicitly opt-in, independent of technical scheduling.')
add('pending/Holiday/'+b+'Services/EmployeeHolidayRequest.php',b+'Services/EmployeeHolidayRequest.php',
    'Reuse native getCalenderQuery signature, SelectBuilder, period predicate, union-compatible columns and original profile color. Restrict to selected-user Pending with native strict read/FLS, eligible actor/target and approver scope for others. Ignore skipAcl, return null for non-allowlisted global additional columns; no Approved duplicate or public read-all grant. Convert UTC bounds to native viewer Preferences/config timezone after a real single-day Day-view omission was demonstrated; preserve exclusive upper bounds.')
add('pending/Holiday/'+b+'Services/EmployeeHolidayRequest.php',b+'Services/EmployeeAbsence.php',
    'Adapt the same native Calendar query to the approved minimal Event. Select only public name/dates, opaque projection ID and null protocol columns; no private request relationship/payload. Keep original live calendarColor through an allowlisted native ORM join, expose it only as a non-stored presentation field subject to FLS; no public profile link. Use viewer-local all-day bounds. Native strict ACL/FLS and mandatory eligible-employee Select filter. Shared feed is global minimal information like ZileLibere, not a personal Working Time range.')
add('pending/Holiday/'+b+'Tools/HolidayAdministration/HolidaySettingsService.php',b+'Tools/HolidayAdministration/HolidaySettingsService.php',
    'Retain all settings, validation and delegated ConfigWriter/ACL. Remove only the Stage2 safety gate prohibiting automaticGrant=true now that the native annual job is integrated; no automatic opt-in.')
add('pending/Holiday/'+j+'src/views/admin/settings.js',j+'src/views/administration/settings.js',
    'Continue the existing native-field delegated settings adapter. Original annual bool becomes editable; remove the temporary Stage2 forced read-only payload. No global Settings/Admin/WTC editor.')
add('pending/Holiday/'+b+'Tools/HolidayBalance/HolidayBalanceService.php',b+'Tools/HolidayBalance/HolidayRequestService.php',
    'No request/approval/accounting rule change. Shared profile locking projection omits the redundant assignedUserName join; beneficiary is already locked. Existing Stage6 native Jobs/snapshots/receipts and Stage5 conflict guards retained.')
add('../HolidayManagement/scripts/AfterInstall.php','scripts/AfterInstall.php',
    'Reuse native Populator and ConfigWriter, add only own Calendar sources without replacing existing list or tabs, set annual flag false only when absent. Idempotent transactional projection backfill; no Roles, WTC or legacy data migration.')
add('../HolidayManagement/scripts/BeforeUninstall.php','scripts/AfterUninstall.php',
    'Adapt own-registration cleanup to native AfterUninstall (10.0.1 skips this script on upgrade). Remove only own Calendar list entries; deactivate own ScheduledJob, retain business data/files/settings/history. Do not remove legacy jobs or reset activation on upgrade. Full uninstall certification remains Stage8.')
text=json.dumps(items,ensure_ascii=False,indent=2)+'\n'
print('*** Begin Patch\n*** Add File: '+str(root/'STAGE-7-REUSE.json')+'\n'+'\n'.join('+'+l for l in text.splitlines())+'\n*** End Patch')
