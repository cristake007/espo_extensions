<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config;

final readonly class Settings
{
    public const KEY_LIST = [
        'enabledSourceEntityTypeList',
        'disabledSourceEntityTypeList',
        'maxRelationshipDepth',
        'maxLayoutBytes',
        'maxElements',
        'maxNestingDepth',
        'maxFreeformElementsPerSection',
        'maxSections',
        'maxConditions',
        'maxRelatedTableColumns',
        'maxMediaBytes',
        'maxImageWidthPx',
        'maxImageHeightPx',
        'maxImagePixels',
        'maxImportBytes',
        'maxSpreadsheetRows',
        'maxSpreadsheetWorksheets',
        'maxSpreadsheetColumns',
        'maxSpreadsheetCells',
        'maxSpreadsheetCellCharacters',
        'maxCollectionRows',
        'maxBatchRecords',
        'previewRequestsPerMinute',
        'maxConcurrentPreviews',
        'renderTimeoutSeconds',
        'renderMemoryMegabytes',
        'maxRenderedPages',
        'temporaryImportRetentionHours',
        'generatedDocumentRetentionDays',
        'allowedFontList',
        'defaultFont',
        'allowSvg',
        'allowWebp',
        'allowRemoteResources',
        'enableListViewMassGeneration',
        'allowDraftGeneration',
        'defaultPdfEngine',
        'defaultLocale',
        'defaultPageSize',
        'storeTemplateSnapshot',
        'storeResolvedDataSnapshot',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {}

    /** @return list<string> */
    public function enabledSourceEntityTypeList(): array
    {
        return $this->values['enabledSourceEntityTypeList'];
    }

    /** @return list<string> */
    public function disabledSourceEntityTypeList(): array
    {
        return $this->values['disabledSourceEntityTypeList'];
    }

    public function maxRelationshipDepth(): int { return $this->values['maxRelationshipDepth']; }
    public function maxLayoutBytes(): int { return $this->values['maxLayoutBytes']; }
    public function maxElements(): int { return $this->values['maxElements']; }
    public function maxNestingDepth(): int { return $this->values['maxNestingDepth']; }
    public function maxFreeformElementsPerSection(): int { return $this->values['maxFreeformElementsPerSection']; }
    public function maxSections(): int { return $this->values['maxSections']; }
    public function maxConditions(): int { return $this->values['maxConditions']; }
    public function maxRelatedTableColumns(): int { return $this->values['maxRelatedTableColumns']; }
    public function maxMediaBytes(): int { return $this->values['maxMediaBytes']; }
    public function maxImageWidthPx(): int { return $this->values['maxImageWidthPx']; }
    public function maxImageHeightPx(): int { return $this->values['maxImageHeightPx']; }
    public function maxImagePixels(): int { return $this->values['maxImagePixels']; }
    public function maxImportBytes(): int { return $this->values['maxImportBytes']; }
    public function maxSpreadsheetRows(): int { return $this->values['maxSpreadsheetRows']; }
    public function maxSpreadsheetWorksheets(): int { return $this->values['maxSpreadsheetWorksheets']; }
    public function maxSpreadsheetColumns(): int { return $this->values['maxSpreadsheetColumns']; }
    public function maxSpreadsheetCells(): int { return $this->values['maxSpreadsheetCells']; }
    public function maxSpreadsheetCellCharacters(): int { return $this->values['maxSpreadsheetCellCharacters']; }
    public function maxCollectionRows(): int { return $this->values['maxCollectionRows']; }
    public function maxBatchRecords(): int { return $this->values['maxBatchRecords']; }
    public function previewRequestsPerMinute(): int { return $this->values['previewRequestsPerMinute']; }
    public function maxConcurrentPreviews(): int { return $this->values['maxConcurrentPreviews']; }
    public function renderTimeoutSeconds(): int { return $this->values['renderTimeoutSeconds']; }
    public function renderMemoryMegabytes(): int { return $this->values['renderMemoryMegabytes']; }
    public function maxRenderedPages(): int { return $this->values['maxRenderedPages']; }
    public function temporaryImportRetentionHours(): int { return $this->values['temporaryImportRetentionHours']; }
    public function generatedDocumentRetentionDays(): int { return $this->values['generatedDocumentRetentionDays']; }

    /** @return list<string> */
    public function allowedFontList(): array
    {
        return $this->values['allowedFontList'];
    }

    public function defaultFont(): string { return $this->values['defaultFont']; }
    public function allowSvg(): bool { return $this->values['allowSvg']; }
    public function allowWebp(): bool { return $this->values['allowWebp']; }
    public function allowRemoteResources(): bool { return $this->values['allowRemoteResources']; }
    public function enableListViewMassGeneration(): bool { return $this->values['enableListViewMassGeneration']; }
    public function allowDraftGeneration(): bool { return $this->values['allowDraftGeneration']; }
    public function defaultPdfEngine(): string { return $this->values['defaultPdfEngine']; }
    public function defaultLocale(): string { return $this->values['defaultLocale']; }
    public function defaultPageSize(): string { return $this->values['defaultPageSize']; }
    public function storeTemplateSnapshot(): bool { return $this->values['storeTemplateSnapshot']; }
    public function storeResolvedDataSnapshot(): bool { return $this->values['storeResolvedDataSnapshot']; }
}
