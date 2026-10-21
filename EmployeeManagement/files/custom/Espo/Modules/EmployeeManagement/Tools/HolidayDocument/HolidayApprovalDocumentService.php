<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\HolidayDocument;

use DateTimeImmutable;
use Espo\Core\Utils\{Config, DateTime as DateTimeUtil};
use Espo\Entities\User;
use Espo\ORM\Entity;
use RuntimeException;

/** Holiday 1.9.4 monthly documents, adapted to an immutable decision snapshot (P8).
 * Renderer, template, Romanian names, totals, signatures and formatting are retained.
 */
final class HolidayApprovalDocumentService
{
    public const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const MONTH_NAMES = [1=>'ianuarie',2=>'februarie',3=>'martie',4=>'aprilie',5=>'mai',6=>'iunie',
        7=>'iulie',8=>'august',9=>'septembrie',10=>'octombrie',11=>'noiembrie',12=>'decembrie'];

    public function __construct(private Config $config, private DateTimeUtil $dateTime,
        private HolidayDocumentGenerator $generator, private HolidayDocumentProjection $projection) {}

    public function capture(Entity $request, User $user, Entity $profile, User $approver, float $pendingDays): array
    {
        $calculation = json_decode(json_encode($request->get('calculationSnapshot'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $monthlyDays = $calculation['monthlyDays'] ?? [];
        $segments = $this->buildMonthlySegments((string)$request->get('dateStartDate'), (string)$request->get('dateEndDate'), $monthlyDays);
        if (array_sum(array_column($segments,'days')) !== (int)$request->get('days') || (int)$request->get('days') < 1) {
            throw new RuntimeException('The monthly holiday document totals do not match the reserved snapshot.');
        }
        $annualEntitlement = (float)$profile->get('annualEntitlement');
        // The profile balance already contains all reservations. Add only this
        // request back to reconstruct its balance-before value; adding every
        // Pending request made unrelated future reservations disappear.
        $balanceBefore = (float)$profile->get('balance') + $pendingDays;
        $nextResetDate = $profile->get('nextResetDate');
        $automaticAnnualGrantEnabled = $this->config->get('employeeHolidayAutomaticAnnualGrantEnabled') === true;
        $projected = $this->projection->build(
            $segments,
            $annualEntitlement,
            $balanceBefore,
            is_string($nextResetDate) ? $nextResetDate : null,
            $automaticAnnualGrantEnabled,
        );
        $documentDate = $this->formatDate($this->dateTime->getToday()->toString());
        $actualApproverName = (string)$approver->get('name');
        $block1Title = trim((string)$this->config->get('employeeHolidayApprovalBlock1Title'));
        $block2Title = trim((string)$this->config->get('employeeHolidayApprovalBlock2Title'));
        $block1Name = $this->approvalName('employeeHolidayApprovalBlock1Name', $block1Title, $actualApproverName);
        $block2Name = $this->approvalName('employeeHolidayApprovalBlock2Name', $block2Title, $actualApproverName);
        $documents = [];
        foreach ($projected as $segment) {
            $verificationCode = strtoupper(bin2hex(random_bytes(16)));
            $values = [
                'dateStart'=>$this->formatDate($segment['dateStart']), 'dateEnd'=>$this->formatDate($segment['dateEnd']),
                'year'=>(string)$segment['year'], 'month'=>(string)$segment['month'],
                'requestedDays'=>$this->formatDays($segment['days']), 'lastName'=>trim((string)$user->get('lastName')),
                'firstName'=>trim((string)$user->get('firstName')), 'approvalBlock1Title'=>$block1Title,
                'approvalBlock1Name'=>$block1Name, 'approvalBlock2Title'=>$block2Title, 'approvalBlock2Name'=>$block2Name,
                'totalDays'=>$this->formatDays($segment['annualEntitlement']),
                'daysAlreadyUsed'=>$this->formatDays($segment['daysAlreadyUsed']),
                'carryover'=>$this->formatDays($segment['carryover']),
                'carryoverYear'=>(string)($segment['year'] - 1),
                'balanceBefore'=>$this->formatDays($segment['balanceBefore']),
                'balanceAfter'=>$this->formatDays($segment['balanceAfter']),
                'documentDate'=>$documentDate, 'verificationCode'=>$verificationCode,
            ];
            $documents[] = ['month'=>sprintf('%04d-%02d',$segment['year'],$segment['month']),
                'filename'=>sprintf('Cerere concediu - %s %d.docx',self::MONTH_NAMES[$segment['month']],$segment['year']),
                'verificationCode'=>$verificationCode, 'values'=>$values];
        }
        return ['version'=>2,'documents'=>$documents,
            'projectionInputs'=>[
                'annualEntitlement'=>$annualEntitlement,
                'balanceBeforeRequest'=>$balanceBefore,
                'nextResetDate'=>$nextResetDate,
                'automaticAnnualGrantEnabled'=>$automaticAnnualGrantEnabled,
            ],
            'calculationHash'=>hash('sha256',json_encode($calculation,JSON_THROW_ON_ERROR)),
            'templateHash'=>$this->templateHash(),'decidedById'=>$approver->getId(),'decidedByName'=>$actualApproverName,
            'decidedAt'=>DateTimeUtil::getSystemNowString()];
    }

    public function render(array $document, string $templateHash): string
    {
        if (!hash_equals($templateHash,$this->templateHash())) { throw new RuntimeException('The approval template version changed; restore the recorded version before retry.'); }
        return $this->generator->render($document['values']);
    }

    /**
     * Rebuild one immutable snapshot document with corrected projection values.
     * Legacy version-1 snapshots require independently established inputs.
     *
     * @param null|array{annualEntitlement: float, balanceBeforeRequest: float, nextResetDate: string, automaticAnnualGrantEnabled: bool} $legacyInputs
     * @return array{document: array<string, mixed>, templateHash: string, projection: array<string, mixed>}
     */
    public function prepareReissue(Entity $request, string $month, ?array $legacyInputs = null): array
    {
        $snapshot = json_decode(json_encode($request->get('approvalSnapshot'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $calculation = json_decode(json_encode($request->get('calculationSnapshot'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $segments = $this->buildMonthlySegments(
            (string) $request->get('dateStartDate'),
            (string) $request->get('dateEndDate'),
            (array) ($calculation['monthlyDays'] ?? []),
        );
        $inputs = $snapshot['projectionInputs'] ?? $legacyInputs;

        if (!is_array($inputs)) {
            throw new RuntimeException('The legacy approval snapshot has no safe annual-grant evidence.');
        }

        $projections = $this->projection->build(
            $segments,
            (float) $inputs['annualEntitlement'],
            (float) $inputs['balanceBeforeRequest'],
            isset($inputs['nextResetDate']) ? (string) $inputs['nextResetDate'] : null,
            ($inputs['automaticAnnualGrantEnabled'] ?? false) === true,
        );
        $document = null;
        $projection = null;

        foreach ((array) ($snapshot['documents'] ?? []) as $candidate) {
            if (($candidate['month'] ?? null) === $month) {
                $document = $candidate;
                break;
            }
        }
        foreach ($projections as $candidate) {
            if (sprintf('%04d-%02d', $candidate['year'], $candidate['month']) === $month) {
                $projection = $candidate;
                break;
            }
        }

        if (!is_array($document) || !is_array($projection)) {
            throw new RuntimeException('The requested month is not part of the immutable approval snapshot.');
        }

        $code = strtoupper(bin2hex(random_bytes(16)));
        $document['verificationCode'] = $code;
        $document['values']['totalDays'] = $this->formatDays($projection['annualEntitlement']);
        $document['values']['daysAlreadyUsed'] = $this->formatDays($projection['daysAlreadyUsed']);
        $document['values']['carryover'] = $this->formatDays($projection['carryover']);
        $document['values']['carryoverYear'] = (string) ($projection['year'] - 1);
        $document['values']['balanceBefore'] = $this->formatDays($projection['balanceBefore']);
        $document['values']['balanceAfter'] = $this->formatDays($projection['balanceAfter']);
        $document['values']['verificationCode'] = $code;

        return [
            'document' => $document,
            'templateHash' => (string) $snapshot['templateHash'],
            'projection' => $projection,
        ];
    }

    private function templateHash(): string
    {
        $hash = hash_file('sha256',dirname(__DIR__,2).'/Resources/templates/holiday-request.docx');
        if ($hash === false) { throw new RuntimeException('The holiday request DOCX template is missing.'); }
        return $hash;
    }

    /** Original month segmentation, with reserved totals instead of a fresh calendar calculation. */
    private function buildMonthlySegments(string $dateStart, string $dateEnd, array $monthlyDays): array
    {
        $requestStart = new DateTimeImmutable($dateStart); $requestEnd = new DateTimeImmutable($dateEnd);
        $month = $requestStart->modify('first day of this month'); $segments = [];
        while ($month <= $requestEnd) {
            $monthEnd = $month->modify('last day of this month');
            $segmentStart = $requestStart > $month ? $requestStart : $month;
            $segmentEnd = $requestEnd < $monthEnd ? $requestEnd : $monthEnd;
            $segments[] = ['dateStart'=>$segmentStart->format('Y-m-d'),'dateEnd'=>$segmentEnd->format('Y-m-d'),
                'year'=>(int)$month->format('Y'),'month'=>(int)$month->format('n'),'days'=>(int)($monthlyDays[$month->format('Y-m')]??0)];
            $month = $month->modify('first day of next month');
        }
        return $segments;
    }

    private function approvalName(string $setting,string $title,string $actualApproverName): string
    {
        $configuredName=trim((string)$this->config->get($setting));
        return $configuredName!==''?$configuredName:($title!==''?$actualApproverName:'');
    }
    private function formatDate(string $date): string { return (new DateTimeImmutable($date))->format('d.m.Y'); }
    private function formatDays(float|int $days): string { return rtrim(rtrim(number_format((float)$days,2,'.',''),'0'),'.'); }
}
