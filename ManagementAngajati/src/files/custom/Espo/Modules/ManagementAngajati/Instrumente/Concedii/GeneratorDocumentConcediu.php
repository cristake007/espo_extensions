<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/** Completeaza documentul oficial fara a reconstrui sau modifica layout-ul acestuia. */
class GeneratorDocumentConcediu
{
    private const string NUME_SABLON = 'cerere-concediu.docx';

    /**
     * @param array<string, mixed> $instantaneu
     * @param array<string, mixed> $segment
     */
    public function genereaza(array $instantaneu, array $segment): string
    {
        $this->verificaMediu();
        $caleSablon = dirname(__DIR__, 2) . '/Resources/sabloane/' . self::NUME_SABLON;

        if (!is_file($caleSablon)) {
            throw new RuntimeException('Șablonul DOCX pentru cererea de concediu nu este disponibil.');
        }

        $document = new TemplateProcessor($caleSablon);
        $angajat = (array) ($instantaneu['angajat'] ?? []);
        $aprobatori = array_values(array_filter(
            (array) ($instantaneu['aprobatori'] ?? []),
            'is_string'
        ));
        $an = (int) ($segment['an'] ?? 0);
        $zileAnuale = (int) ($segment['zileAnuale'] ?? 0);
        $zileReportate = (int) ($segment['zileReportate'] ?? 0);
        $linieAnuala = $zileAnuale > 0 ? $zileAnuale . ' - ' . $an : '';
        $linieReport = $zileReportate > 0 ?
            $zileReportate . ' - report ' . ($an - 1) : '';

        $document->setValues([
            'dataInceput' => $this->data((string) ($segment['dataInceput'] ?? '')),
            'dataSfarsit' => $this->data((string) ($segment['dataSfarsit'] ?? '')),
            'an' => (string) $an,
            'luna' => (string) ((int) ($segment['numarLuna'] ?? 0)),
            'zileSolicitate' => (string) ((int) ($segment['zileSolicitate'] ?? 0)),
            'nume' => (string) ($angajat['nume'] ?? ''),
            'prenume' => (string) ($angajat['prenume'] ?? ''),
            'aprobatorUnu' => $aprobatori[0] ?? '',
            'aprobatorDoi' => $aprobatori[1] ?? '',
            'liniePrincipalaTotal' => $linieAnuala !== '' ? $linieAnuala : $linieReport,
            'linieSecundaraTotal' => $linieAnuala !== '' ? $linieReport : '',
            'zileConsumate' => (string) ((int) ($segment['zileConsumate'] ?? 0)),
            'soldInainte' => (string) ((int) ($segment['soldInainte'] ?? 0)),
            'soldDupa' => (string) ((int) ($segment['soldDupa'] ?? 0)),
            'dataEmitere' => $this->data((string) ($instantaneu['emisLa'] ?? '')),
            'codVerificare' => (string) ($instantaneu['codVerificare'] ?? ''),
        ]);

        $cale = tempnam(sys_get_temp_dir(), 'concediu-docx-');
        if ($cale === false) {
            throw new RuntimeException('Nu a putut fi creat fișierul temporar DOCX.');
        }

        try {
            $document->saveAs($cale);
            $continut = file_get_contents($cale);
            if ($continut === false || $continut === '') {
                throw new RuntimeException('Documentul DOCX nu a putut fi citit după generare.');
            }
            return $continut;
        } finally {
            @unlink($cale);
        }
    }

    private function verificaMediu(): void
    {
        foreach (['dom', 'gd', 'xml', 'zip'] as $extensie) {
            if (!extension_loaded($extensie)) {
                throw new RuntimeException('Lipsește extensia PHP necesară: ' . $extensie . '.');
            }
        }
        if (!class_exists(TemplateProcessor::class)) {
            throw new RuntimeException('Biblioteca PHPWord nu este disponibilă.');
        }
    }

    private function data(string $valoare): string
    {
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valoare);
        return $data ? $data->format('d.m.Y') : $valoare;
    }
}
