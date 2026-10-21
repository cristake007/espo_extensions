<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\Attendance;

use Espo\ORM\{Entity, EntityManager};
use Espo\Entities\User;
use Espo\Modules\EmployeeManagement\Tools\EmployeeHolidayRequest\{WorkingDayCalculator, NonWorkingDayProvider};
use Espo\Core\Exceptions\BadRequest;

/** Persisted derived monthly norm, never an editable work schedule. Caller owns the native transaction. */
final class AttendanceMonthService
{
    public function __construct(private EntityManager $em, private WorkingDayCalculator $calculator,
        private NonWorkingDayProvider $publicDays) {}

    public function get(string $month): Entity
    {
        $repository = $this->em->getRDBRepository('EmployeeAttendanceMonth');
        $state = $repository->where(['month'=>$month])->select(['id','name','month','norms','policies','frozen','frozenAt','frozenById','revision','createdAt','modifiedAt'])->forUpdate()->findOne();
        if (!$state) {
            try {
                $this->em->getTransactionManager()->run(function () use ($month): void {
                    $state=$this->em->getNewEntity('EmployeeAttendanceMonth');
                    $state->set(['name'=>$month,'month'=>$month,'norms'=>(object)[],'frozen'=>false,'revision'=>0]);
                    $this->em->saveEntity($state);
                });
            } catch (\PDOException $e) { if (($e->errorInfo[1]??null)!==1062) { throw $e; } }
            $state=$repository->where(['month'=>$month])->select(['id','name','month','norms','policies','frozen','frozenAt','frozenById','revision','createdAt','modifiedAt'])->forUpdate()->findOne();
        }
        $norms=(array)($state->get('norms')??(object)[]);
        // Once frozen, population/norms remain historical. New regular employees do not rewrite it.
        if (!$state->get('frozen')) {
            $start=$month.'-01';$end=(new \DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
            $public=$this->publicDays->getDates($start,$end);$changed=false;
            foreach ($this->em->getRDBRepositoryByClass(User::class)->where(['type'=>User::TYPE_REGULAR,'isActive'=>true])->find() as $user) {
                $id=(string)$user->getId();if(isset($norms[$id])){continue;}
                try { $norms[$id]=(object)$this->calculator->snapshot($user,$start,$end,$public,true); }
                catch(BadRequest $e) { continue; } // Missing explicit WTC is reported as unconfigured, never defaulted or frozen.
                $changed=true;
            }
            if($changed){$state->set('norms',(object)$norms);$this->em->saveEntity($state);}
        }
        return $state;
    }

    public function norm(Entity $state,string $userId): ?array
    {
        $norms=(array)$state->get('norms');
        return isset($norms[$userId])?json_decode(json_encode($norms[$userId],JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR):null;
    }

    /** Sorted month locks shared by Holiday commands and the Attendance aggregate.
     * Caller already holds the beneficiary User lock; never locks other Users.
     * Existing norms/frozen state are never reset by a Holiday transition.
     * @return array<string, Entity>
     */
    public function lockPeriods(array $periods): array
    {
        $keys = [];
        foreach ($periods as [$start, $end]) {
            [$first, $last] = $this->calculator->period($start, $end);
            for ($point = $first->modify('first day of this month'); $point <= $last; $point = $point->modify('+1 month')) {
                $keys[$point->format('Y-m')] = true;
            }
        }
        ksort($keys);
        $states = [];
        foreach (array_keys($keys) as $month) { $states[$month] = $this->get($month); }
        return $states;
    }

    /** Book from the same immutable normative result used by Attendance.
     * A first booking captures the whole month via the existing WTC calculator.
     * This is a derived result, not a second configuration or availability calendar.
     */
    public function snapshot(User $user, string $start, string $end): array
    {
        $states = $this->lockPeriods([[$start, $end]]);
        $days = []; $ranges = []; $counts = []; $sources = []; $excluded = [];
        foreach ($states as $month => $state) {
            $norm = $this->norm($state, (string) $user->getId());
            if ($norm === null) { throw new BadRequest('An explicit native WTC result is required for this employee and month.'); }
            $sources[$month] = ['monthId' => $state->getId(), 'calendarId' => $norm['calendarId'], 'timezone' => $norm['timezone']];
            foreach ($norm['days'] as $date => $hours) {
                if ($date < $start || $date > $end) { continue; }
                $days[$date] = $hours;
                $ranges[$date] = $norm['ranges'][$date] ?? [];
                $counts[$month] = ($counts[$month] ?? 0) + 1;
            }
            foreach ($norm['excludedPublicDates'] as $date) {
                if ($date >= $start && $date <= $end) { $excluded[$date] = true; }
            }
        }
        if (!$days) { throw new \InvalidArgumentException('The selected period contains no working days.'); }
        $first = reset($sources);
        return ['version' => 2, 'calendarId' => $first['calendarId'], 'timezone' => $first['timezone'],
            'dateStart' => $start, 'dateEnd' => $end, 'days' => (object) $days,
            'monthlyDays' => (object) $counts, 'totalDays' => count($days),
            'excludedPublicDates' => array_keys($excluded), 'ranges' => (object) $ranges,
            'normSources' => (object) $sources];
    }

    /** Invalidate observable month revisions, never unfreeze or alter normative history. */
    public function touch(array $states): void
    {
        foreach ($states as $state) {
            $state->set('revision', (int) $state->get('revision') + 1);
            $this->em->saveEntity($state);
        }
    }
}
