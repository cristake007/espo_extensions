<?php

namespace Espo\Modules\Planificari\Tools\Planificari;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;

class GenerationService
{
    private const MONTH_NAMES = [
        1 => 'Ianuarie',
        2 => 'Februarie',
        3 => 'Martie',
        4 => 'Aprilie',
        5 => 'Mai',
        6 => 'Iunie',
        7 => 'Iulie',
        8 => 'August',
        9 => 'Septembrie',
        10 => 'Octombrie',
        11 => 'Noiembrie',
        12 => 'Decembrie',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager,
        private CourseInputParser $parser,
        private XlsxExportService $xlsxExportService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(string $id, ?object $input = null): array
    {
        $record = $this->entityManager->getEntityById('Planificari', $id);

        if (!$record) {
            throw new BadRequest('Inregistrarea Planificari nu a fost gasita.');
        }

        if ($record->get('generatedAt')) {
            throw new BadRequest(
                'Aceasta planificare a fost deja generata. Creeaza o planificare noua pentru a genera din nou.'
            );
        }

        $year = $this->parseYear($this->getInputValue($record, $input, 'year'));
        $months = $this->parseMonths($this->getInputValue($record, $input, 'selectedMonths'));
        $randomness = $this->parseRandomness($this->getInputValue($record, $input, 'randomness'));
        $holidayValue = $this->getInputValue($record, $input, 'holidays');
        $holidays = $this->parseHolidayDates($holidayValue);
        $sourceFileId = $this->getInputValue($record, $input, 'sourceFileId');

        if (!$sourceFileId || !is_string($sourceFileId)) {
            throw new BadRequest('Incarca un fisier sursa inainte de generare.');
        }

        /** @var ?Attachment $attachment */
        $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $sourceFileId);

        if (!$attachment) {
            throw new BadRequest('Fisierul sursa nu a fost gasit.');
        }

        $sourceFileName = $attachment->getName() ?? $record->get('sourceFileName') ?? 'source-file';
        $contents = $this->fileStorageManager->getContents($attachment);
        $courses = $this->parser->parse($contents, $sourceFileName);
        $scheduler = new CourseScheduler($year, $holidays);
        $generationResult = $this->generateRows($courses, $months, $randomness, $scheduler);
        $rows = $generationResult['rows'];

        $this->validateCompleteness($rows, $courses, $months);

        $generatedAt = gmdate('Y-m-d H:i:s');
        $exportAttachment = $this->xlsxExportService->createAttachment(
            $rows,
            $months,
            $holidays,
            (string) ($record->get('name') ?? 'planificari'),
            $year
        );

        $record->set('generatedAt', $generatedAt);
        $record->set('exportFileId', $exportAttachment->getId());
        $this->entityManager->saveEntity($record);

        return [
            'success' => true,
            'message' => 'Programul a fost generat.',
            'id' => $record->getId(),
            'rowCount' => count($rows),
            'sourceCourseCount' => count($courses),
            'calendarCalculations' => $scheduler->getAvailableDateCalculations(),
            'rows' => $rows,
            'warningMessage' => $generationResult['warningMessage'],
            'record' => [
                'id' => $record->getId(),
                'name' => $record->get('name'),
                'sourceFileId' => $sourceFileId,
                'sourceFileName' => $sourceFileName,
                'year' => (string) $year,
                'selectedMonths' => array_map('strval', $months),
                'randomness' => (string) $randomness,
                'holidays' => $holidayValue,
                'holidayDates' => $holidays,
                'generatedAt' => $generatedAt,
                'exportFileId' => $exportAttachment->getId(),
            ],
            'implemented' => [
                'usesSavedRecordInput' => true,
                'fileParsing' => true,
                'scheduleGeneration' => true,
                'rowPersistence' => false,
                'xlsxExport' => true,
            ],
            'downloadUrl' => '?entryPoint=download&id=' . $exportAttachment->getId(),
        ];
    }

    private function getInputValue($record, ?object $input, string $name): mixed
    {
        if ($input && property_exists($input, $name)) {
            return $input->$name;
        }

        return $record->get($name);
    }

    /**
     * @param array<int, array<string, int|string>> $courses
     * @param int[] $months
     * @return array{rows: array<int, array<string, int|string|bool>>, warningMessage: ?string}
     */
    private function generateRows(array $courses, array $months, int $randomness, CourseScheduler $scheduler): array
    {
        $rows = [];
        $warningMessages = [];

        foreach ($months as $month) {
            $scheduledDates = [];

            foreach ($courses as $course) {
                $availableDates = $scheduler->getAvailableStartDays($month, (int) $course['duration']);

                if ($availableDates === []) {
                    $availableBusinessDays = $scheduler->countBusinessDaysAvailableFromMonth($month);
                    $warningMessages[] = sprintf(
                        'Luna %s are doar %d zile lucratoare disponibile pana la finalul anului, dar cursul de la randul %d necesita %d zile.',
                        self::MONTH_NAMES[$month],
                        $availableBusinessDays,
                        (int) $course['sourceRow'],
                        (int) $course['duration']
                    );

                    $rows[] = $this->buildRow($course, $month, 'not enough working days in month', true);

                    continue;
                }

                $startDate = $this->chooseStartDate(
                    $availableDates,
                    $scheduledDates,
                    $randomness,
                    (int) $course['duration']
                );

                $rows[] = $this->buildRow(
                    $course,
                    $month,
                    $scheduler->formatDateRange($startDate, (int) $course['duration']),
                    false
                );

                $scheduledDates[] = $startDate;
            }
        }

        usort(
            $rows,
            fn (array $a, array $b): int => [$a['originalOrder'], $a['month']] <=> [$b['originalOrder'], $b['month']]
        );

        return [
            'rows' => $rows,
            'warningMessage' => $warningMessages !== [] ?
                'Programul a fost generat partial. ' . $warningMessages[0] :
                null,
        ];
    }

    /**
     * @param array<string, int|string> $course
     * @return array<string, int|string|bool>
     */
    private function buildRow(array $course, int $month, string $dateRange, bool $isIncomplete): array
    {
        return [
            'courseTitle' => (string) $course['title'],
            'permalink' => (string) $course['permalink'],
            'durationLabel' => (string) $course['durationLabel'],
            'investment' => (string) $course['investment'],
            'month' => $month,
            'monthName' => self::MONTH_NAMES[$month],
            'dateRange' => $dateRange,
            'isIncomplete' => $isIncomplete,
            'sourceRow' => (int) $course['sourceRow'],
            'originalOrder' => (int) $course['originalOrder'],
        ];
    }

    /**
     * @param DateTimeImmutable[] $availableDates
     * @param DateTimeImmutable[] $scheduledDates
     */
    private function chooseStartDate(
        array $availableDates,
        array $scheduledDates,
        int $randomness,
        int $duration
    ): DateTimeImmutable
    {
        $minGap = max(1, 11 - $randomness);
        $filteredDates = [];

        foreach ($availableDates as $date) {
            $hasConflict = false;

            foreach ($scheduledDates as $scheduledDate) {
                $gap = abs((int) (($date->getTimestamp() - $scheduledDate->getTimestamp()) / 86400));

                if ($gap < $minGap) {
                    $hasConflict = true;
                    break;
                }
            }

            if (!$hasConflict) {
                $filteredDates[] = $date;
            }
        }

        $datesToUse = $filteredDates !== [] ? $filteredDates : $availableDates;

        if ($duration > 5) {
            return $datesToUse[0];
        }

        if ($randomness > 7) {
            return $datesToUse[random_int(0, count($datesToUse) - 1)];
        }

        $weights = [];
        $numberOfDates = count($datesToUse);

        foreach ($datesToUse as $index => $date) {
            if ($randomness <= 3) {
                $weights[] = $index < 5 ? 0.5 : 1.0;

                continue;
            }

            if ($randomness <= 6) {
                $midpoint = intdiv($numberOfDates, 2);
                $weights[] = 1.0 - (abs($index - $midpoint) / $numberOfDates) * 0.5;

                continue;
            }

            $weights[] = 0.8 + random_int(0, 400000) / 1000000;
        }

        return $this->chooseWeightedDate($datesToUse, $weights);
    }

    private function parseYear(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new BadRequest('Anul este obligatoriu.');
        }

        $year = (int) $value;

        if ((string) $year !== trim((string) $value) || $year < 2025 || $year > 2031) {
            throw new BadRequest('Anul trebuie sa fie intre 2025 si 2031.');
        }

        return $year;
    }

    private function parseRandomness(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 5;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new BadRequest('Nivelul de variatie nu este valid.');
        }

        $randomness = (int) $value;

        if ((string) $randomness !== trim((string) $value) || $randomness < 1 || $randomness > 10) {
            throw new BadRequest('Nivelul de variatie trebuie sa fie intre 1 si 10.');
        }

        return $randomness;
    }

    /**
     * @return int[]
     */
    private function parseMonths(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            throw new BadRequest('Selecteaza cel putin o luna.');
        }

        $months = [];

        foreach ($value as $month) {
            $monthNumber = (int) $month;

            if ((string) $monthNumber !== trim((string) $month) || $monthNumber < 1 || $monthNumber > 12) {
                throw new BadRequest('Lunile selectate nu sunt valide.');
            }

            if (!in_array($monthNumber, $months, true)) {
                $months[] = $monthNumber;
            }
        }

        sort($months);

        return $months;
    }

    /**
     * @return string[]
     */
    private function parseHolidayDates(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            throw new BadRequest('Zilele nelucratoare trebuie separate prin virgula.');
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            fn (string $item): bool => $item !== ''
        ));

        if (count($parts) > 366) {
            throw new BadRequest('Sunt permise cel mult 366 zile nelucratoare.');
        }

        $seen = [];
        $dates = [];

        foreach ($parts as $date) {
            if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
                throw new BadRequest('Foloseste zile nelucratoare de forma 15.09.2026, 16.09.2026.');
            }

            $parsed = DateTimeImmutable::createFromFormat('!d.m.Y', $date);

            if (!$parsed || $parsed->format('d.m.Y') !== $date) {
                throw new BadRequest("Data {$date} nu este valida.");
            }

            if (isset($seen[$date])) {
                throw new BadRequest("Data {$date} este repetata. Sterge duplicatele inainte de generare.");
            }

            $seen[$date] = true;
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * @param array<int, array<string, int|string>> $rows
     * @param array<int, array<string, int|string>> $courses
     * @param int[] $months
     */
    private function validateCompleteness(array $rows, array $courses, array $months): void
    {
        $expected = count($courses) * count($months);

        if (count($rows) !== $expected) {
            throw new BadRequest('Programul generat este incomplet.');
        }

        foreach ($rows as $row) {
            if (!isset($row['dateRange']) || trim((string) $row['dateRange']) === '') {
                throw new BadRequest('Programul generat contine perioade goale.');
            }
        }
    }

    /**
     * @param DateTimeImmutable[] $dates
     * @param float[] $weights
     */
    private function chooseWeightedDate(array $dates, array $weights): DateTimeImmutable
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            return $dates[0];
        }

        $target = random_int(0, 1000000) / 1000000 * $total;
        $current = 0.0;

        foreach ($dates as $index => $date) {
            $current += $weights[$index] ?? 0.0;

            if ($target <= $current) {
                return $date;
            }
        }

        return $dates[array_key_last($dates)];
    }
}
