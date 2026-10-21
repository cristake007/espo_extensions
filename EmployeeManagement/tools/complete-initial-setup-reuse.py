"""Preserve the original zero-balance initial-setup workflow with stage-2 guards."""
from pathlib import Path
import re,json,hashlib
root=Path(__file__).resolve().parents[1]
prefix='files/custom/Espo/Modules/EmployeeManagement/'
src='pending/Holiday/'+prefix+'Tools/HolidayBalance/HolidayBalanceService.php'
original=(root/src).read_text()
def method(name):
    m=re.search(r'^    (?:public|private) function '+name+r'\(',original,re.M)
    nxt=re.search(r'^    (?:public|private) function ',original[m.end():],re.M)
    chunk=original[m.start():m.end()+nxt.start() if nxt else len(original)]
    return chunk[:chunk.rfind('\n    }')+6]
t=method('completeInitialSetup')
t=t.replace('        $this->validateIdempotencyKey',"        $this->accessChecker->assertProfiles();\n        $operationHash = hash('sha256', json_encode(['openingBalanceSetup', $profileId, $openingBalance], JSON_THROW_ON_ERROR));\n        $this->validateIdempotencyKey",1)
t=t.replace('            $profileId,','            $operationHash,\n            $profileId,',1)
t=t.replace('            $existing =', '            $profile = $this->lockProfile($profileId);\n            $existing =',1)
t=t.replace('return $this->duplicateResult($existing);','return $this->duplicateResult($existing, $operationHash);')
t=t.replace('return $this->mutationResult($profile, $existingAfterLock, true);','return $this->duplicateResult($existingAfterLock, $operationHash);')
t=t.replace('                $idempotencyKey,\n            );','                $idempotencyKey, null, $operationHash,\n            );')
lock=method('lockProfile')
lock=lock.replace('        $profile = $this->entityManager',"        $owner = $this->entityManager->getEntityById(self::PROFILE, $profileId);\n        if (!$owner) { throw new NotFound($this->message('profileNotFound')); }\n        $this->findEligibleUser((string) $owner->get('assignedUserId'));\n        $profile = $this->entityManager",1)
lock=lock.replace('$this->assertProfileForActiveRegularUser($profile);',"$this->findEligibleUser((string) $profile->get('assignedUserId'));")
out={}
path=root/prefix/'Tools/HolidayBalance/HolidayProfileService.php'
text=path.read_text()
text=text.replace("'canCompleteInitialSetup' => false", "'canCompleteInitialSetup' => $profile ? $this->canCompleteInitialSetup($profile) : false")
out[str(path)]=text[:-2]+'\n'+t+'\n\n'+method('canCompleteInitialSetup')+'\n\n'+lock+'\n}\n'
src='pending/Holiday/files/client/custom/modules/employee-management/src/views/modals/complete-initial-setup.js'
t=(root/src).read_text().replace("'Admin'","'EmployeeAdministration'").replace("scope='Admin'","scope='EmployeeAdministration'")
t=t.replace('EmployeeManagement/completeInitialSetup','EmployeeManagement/administration/profiles/completeInitialSetup')
t=t.replace("            this.profileId =", "            this.operationKey = `opening-setup:${Date.now()}:${this.options.profileId}`;\n            this.profileId =",1)
t=t.replace('`opening-setup:${Date.now()}:${this.profileId}`','this.operationKey')
dst='files/client/custom/modules/employee-management/src/views/modals/complete-initial-setup.js'
out[str(root/dst)]=t
path=root/'files/client/custom/modules/employee-management/src/views/administration/profiles.js'
t=path.read_text().replace("'click [data-action=\"initializeProfile\"]': 'actionInitializeProfile',", "'click [data-action=\"initializeProfile\"]': 'actionInitializeProfile',\n            'click [data-action=\"completeInitialSetup\"]': 'actionCompleteInitialSetup',")
old=(root/'pending/Holiday/files/client/custom/modules/employee-management/src/views/admin/profiles.js').read_text()
start=old.index('        actionCompleteInitialSetup(event)');end=old.index('        actionCorrectBalance(event)')
t=t.replace('    }\n});',old[start:end].replace("'Admin'","'EmployeeAdministration'")+'    }\n});')
out[str(path)]=t
records=json.loads((root/'STAGE-2-REUSE.json').read_text())
records.append({'source':src,'destination':dst,'sourceSha256':hashlib.sha256((root/src).read_bytes()).hexdigest(),'treatment':'Preserved original initial-setup modal and native float field; endpoint/translation scope adapted, retry key stable for modal lifetime.'})
out[str(root/'STAGE-2-REUSE.json')]=json.dumps(records,ensure_ascii=False,indent=2)+'\n'
print('*** Begin Patch')
for path,text in out.items():
    print('*** Add File: '+path)
    print('\n'.join('+'+line for line in text.splitlines()))
print('*** End Patch')
