<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use ValueError;

class MecXmlBuilder
{
    private const START_DAY_SECONDS = 28800;
    private const END_DAY_SECONDS = 64800;

    private DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new DateTimeZone('Europe/Bucharest');
    }

    /**
     * @param array<int, array{courseTitle: string, dateRange: string, permalink: string, sourceRow?: int, sourceColumn?: int}> $events
     */
    public function build(array $events, int $startPostId = 20000): string
    {
        $document = new DOMDocument('1.0');
        $document->formatOutput = true;
        $root = $document->appendChild($document->createElement('events'));
        $courses = [];

        foreach ($events as $event) {
            $courses[$event['courseTitle']][] = $event;
        }

        $eventId = ($startPostId ?: 20000) - 1;

        foreach ($courses as $courseTitle => $courseEvents) {
            foreach ($courseEvents as $periodOffset => $event) {
                $eventId++;
                [$startDate, $endDate] = $this->parseDateRange($event['dateRange']);
                $startTimestamp = $this->timestamp($startDate) + self::START_DAY_SECONDS;
                $endTimestamp = $this->timestamp($endDate) + self::END_DAY_SECONDS;
                $item = $this->element($document, $root, 'item');

                $this->textElement($document, $item, 'ID', (string) $eventId);
                $this->textElement($document, $item, 'title', $courseTitle);
                $this->element($document, $item, 'content');

                $post = $this->element($document, $item, 'post');
                $this->textElement($document, $post, 'ID', (string) $eventId);
                $this->textElement($document, $post, 'post_author', '5');
                $this->textElement($document, $post, 'post_date', $startDate . ' 00:00:00');
                $this->textElement($document, $post, 'post_date_gmt', $startDate . ' 00:00:00');
                $this->textElement($document, $post, 'post_title', $courseTitle);
                $this->textElement($document, $post, 'post_status', 'draft');

                $meta = $this->element($document, $item, 'meta');
                $this->textElement($document, $meta, 'mec_more_info_title', 'perioada ' . ($periodOffset + 1));
                $this->textElement($document, $meta, 'mec_read_more', $event['permalink']);
                $this->element($document, $meta, 'mec_color');
                $this->textElement($document, $meta, 'mec_location_id', '1');
                $this->textElement($document, $meta, 'mec_organizer_id', '1');
                $this->textElement($document, $meta, 'mec_allday', '1');
                $this->textElement($document, $meta, 'mec_start_date', $startDate);
                $this->textElement($document, $meta, 'mec_start_time_hour', '8');
                $this->textElement($document, $meta, 'mec_start_time_minutes', '00');
                $this->textElement($document, $meta, 'mec_start_time_ampm', 'AM');
                $this->textElement($document, $meta, 'mec_start_day_seconds', (string) self::START_DAY_SECONDS);
                $this->textElement($document, $meta, 'mec_start_datetime', $startDate . ' 08:00 AM');
                $this->textElement($document, $meta, 'mec_end_date', $endDate);
                $this->textElement($document, $meta, 'mec_end_time_hour', '6');
                $this->textElement($document, $meta, 'mec_end_time_minutes', '00');
                $this->textElement($document, $meta, 'mec_end_time_ampm', 'PM');
                $this->textElement($document, $meta, 'mec_end_day_seconds', (string) self::END_DAY_SECONDS);
                $this->textElement($document, $meta, 'mec_end_datetime', $endDate . ' 06:00 PM');
                $this->textElement($document, $meta, 'mec_repeat_status', '0');

                $mecDate = $this->element($document, $meta, 'mec_date');
                $start = $this->element($document, $mecDate, 'start');
                $this->textElement($document, $start, 'date', $startDate);
                $this->textElement($document, $start, 'hour', '8');
                $this->textElement($document, $start, 'minutes', '00');
                $this->textElement($document, $start, 'ampm', 'AM');
                $end = $this->element($document, $mecDate, 'end');
                $this->textElement($document, $end, 'date', $endDate);
                $this->textElement($document, $end, 'hour', '6');
                $this->textElement($document, $end, 'minutes', '00');
                $this->textElement($document, $end, 'ampm', 'PM');
                $this->textElement($document, $mecDate, 'allday', '1');

                $mec = $this->element($document, $item, 'mec');
                $this->element($document, $mec, 'id');
                $this->textElement($document, $mec, 'post_id', (string) $eventId);
                $this->textElement($document, $mec, 'start', $startDate);
                $this->textElement($document, $mec, 'end', $endDate);
                $this->textElement($document, $mec, 'repeat', '0');
                $this->textElement($document, $mec, 'time_start', (string) self::START_DAY_SECONDS);
                $this->textElement($document, $mec, 'time_end', (string) self::END_DAY_SECONDS);

                $time = $this->element($document, $item, 'time');
                $this->textElement($document, $time, 'start', 'All Day');
                $this->element($document, $time, 'end');
                $this->textElement($document, $time, 'start_raw', '8:00 am');
                $this->textElement($document, $time, 'end_raw', '6:00 pm');
                $this->textElement($document, $time, 'start_timestamp', (string) $startTimestamp);
                $this->textElement($document, $time, 'end_timestamp', (string) $endTimestamp);
            }
        }

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new ValueError('Unable to serialize MEC XML.');
        }

        $xml = preg_replace('/^<\?xml version="1\.0"\?>/', '<?xml version="1.0" ?>', $xml) ?? $xml;

        return preg_replace_callback(
            '/^( +)/m',
            static fn (array $matches): string => str_repeat(' ', intdiv(strlen($matches[1]), 2)),
            $xml
        ) ?? $xml;
    }

    /**
     * @return array{string, string}
     */
    private function parseDateRange(string $dateRange): array
    {
        $normalized = trim($dateRange);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $normalized, $matches)) {
            $date = sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);

            return [$date, $date];
        }

        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $normalized, $matches)) {
            return [
                sprintf('%04d-%02d-%02d', (int) $matches[4], (int) $matches[3], (int) $matches[1]),
                sprintf('%04d-%02d-%02d', (int) $matches[4], (int) $matches[3], (int) $matches[2]),
            ];
        }

        if (preg_match(
            '/^(\d{1,2})\.(\d{1,2})\s*-\s*(\d{1,2})\.(\d{1,2})\.(\d{4})$/',
            $normalized,
            $matches
        )) {
            return [
                sprintf('%04d-%02d-%02d', (int) $matches[5], (int) $matches[2], (int) $matches[1]),
                sprintf('%04d-%02d-%02d', (int) $matches[5], (int) $matches[4], (int) $matches[3]),
            ];
        }

        throw new ValueError('Unsupported date format: ' . $dateRange);
    }

    private function timestamp(string $date): int
    {
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $this->timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($dateTime === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ValueError('Invalid calendar date: ' . $date);
        }

        return $dateTime->getTimestamp();
    }

    private function element(DOMDocument $document, DOMElement $parent, string $name): DOMElement
    {
        $element = $document->createElement($name);
        $parent->appendChild($element);

        return $element;
    }

    private function textElement(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        string $value
    ): DOMElement {
        $element = $this->element($document, $parent, $name);

        if ($value !== '') {
            $element->appendChild($document->createTextNode($value));
        }

        return $element;
    }
}
