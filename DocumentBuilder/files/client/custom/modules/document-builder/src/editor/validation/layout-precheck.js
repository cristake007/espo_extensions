define([
    'document-builder:editor/state/json',
    'document-builder:editor/state/node-tree',
    'document-builder:editor/flow/flow-structure',
], (Json, NodeTree, FlowStructure) => {
    const ROOT_KEYS = Object.freeze([
        'schemaVersion',
        'capabilities',
        'document',
        'dataSource',
        'header',
        'sections',
        'footer',
    ]);
    const PAGE_DIMENSIONS_MM = Object.freeze({
        A4: Object.freeze({width: 210, height: 297}),
        A3: Object.freeze({width: 297, height: 420}),
    });
    const ORIENTATION_LIST = Object.freeze(['portrait', 'landscape']);
    const EDGE_LIST = Object.freeze(['top', 'right', 'bottom', 'left']);

    const hasOnlyKeys = (value, requiredKeys, optionalKeys = []) => {
        if (!Json.isPlainObject(value)) {
            return false;
        }

        const allowedKeys = [...requiredKeys, ...optionalKeys];

        return requiredKeys.every(key => key in value) &&
            Object.keys(value).every(key => allowedKeys.includes(key));
    };

    const isMeasurement = (value, unit, minimum, maximum) =>
        hasOnlyKeys(value, ['value', 'unit']) &&
        typeof value.value === 'number' &&
        value.value >= minimum &&
        value.value <= maximum &&
        value.unit === unit;

    const validatePage = (page, errors, pageDimensions) => {
        if (!hasOnlyKeys(page, ['size', 'orientation', 'margins'])) {
            errors.push('document.page.structure');

            return;
        }

        if (!(page.size in pageDimensions)) {
            errors.push('document.page.size');
        }

        if (!ORIENTATION_LIST.includes(page.orientation)) {
            errors.push('document.page.orientation');
        }

        if (!hasOnlyKeys(page.margins, EDGE_LIST)) {
            errors.push('document.page.margins.structure');

            return;
        }

        const validMargins = EDGE_LIST.every(edge => {
            const valid = isMeasurement(page.margins[edge], 'mm', 0, 2000);

            if (!valid) {
                errors.push(`document.page.margins.${edge}`);
            }

            return valid;
        });

        if (!validMargins || !(page.size in pageDimensions) ||
            !ORIENTATION_LIST.includes(page.orientation)) {
            return;
        }

        const dimensions = pageDimensions[page.size];
        const width = page.orientation === 'portrait' ? dimensions.width : dimensions.height;
        const height = page.orientation === 'portrait' ? dimensions.height : dimensions.width;

        if (page.margins.left.value + page.margins.right.value >= width) {
            errors.push('document.page.printableWidth');
        }

        if (page.margins.top.value + page.margins.bottom.value >= height) {
            errors.push('document.page.printableHeight');
        }
    };

    const validateDefaults = (defaults, errors) => {
        if (!hasOnlyKeys(defaults, [
            'fontFamily',
            'fontSize',
            'color',
            'lineHeight',
            'locale',
            'timezone',
        ])) {
            errors.push('document.defaults.structure');

            return;
        }

        if (typeof defaults.fontFamily !== 'string' ||
            defaults.fontFamily.length > 100 ||
            !/^[A-Za-z][A-Za-z0-9 ._-]*$/.test(defaults.fontFamily)) {
            errors.push('document.defaults.fontFamily');
        }

        if (!isMeasurement(defaults.fontSize, 'pt', 0, 512)) {
            errors.push('document.defaults.fontSize');
        }

        if (typeof defaults.color !== 'string' || !/^#[0-9A-Fa-f]{6}$/.test(defaults.color)) {
            errors.push('document.defaults.color');
        }

        if (typeof defaults.lineHeight !== 'number' ||
            defaults.lineHeight < 0.5 || defaults.lineHeight > 5) {
            errors.push('document.defaults.lineHeight');
        }

        if (typeof defaults.locale !== 'string' || !/^[a-z]{2}_[A-Z]{2}$/.test(defaults.locale)) {
            errors.push('document.defaults.locale');
        }

        if (typeof defaults.timezone !== 'string' ||
            !/^(UTC|[A-Za-z_]+(?:\/[A-Za-z0-9_+.-]+)+)$/.test(defaults.timezone)) {
            errors.push('document.defaults.timezone');
        }
    };

    const validateChrome = (chrome, page, layout, errors) => {
        if (!hasOnlyKeys(chrome, ['header', 'footer'])) {
            errors.push('document.chrome.structure');

            return;
        }

        [['header', 'top'], ['footer', 'bottom']].forEach(([region, edge]) => {
            const settings = chrome[region];

            if (!hasOnlyKeys(settings, ['height', 'showOnFirstPage', 'disableOnFullPage']) ||
                !isMeasurement(settings.height, 'mm', 0, 100) ||
                typeof settings.showOnFirstPage !== 'boolean' ||
                typeof settings.disableOnFullPage !== 'boolean') {
                errors.push(`document.chrome.${region}`);

                return;
            }

            const nodes = layout[region];
            if ((nodes.length === 0) !== (settings.height.value === 0)) {
                errors.push(`document.chrome.${region}.enabledHeight`);
            }
            const reservedMargin = page?.margins?.[edge]?.value;
            if (typeof reservedMargin === 'number' && settings.height.value > reservedMargin) {
                errors.push(`document.chrome.${region}.marginReserved`);
            }

            nodes.forEach((node, index) => {
                if (!Json.isPlainObject(node) || !['paragraph', 'static-text', 'divider'].includes(node.type)) {
                    errors.push(`document.chrome.${region}.nodes.${index}`);
                }
            });
        });
    };

    const validateDataSource = (dataSource, errors) => {
        if (!Json.isPlainObject(dataSource) ||
            !['none', 'entity', 'spreadsheet'].includes(dataSource.type)) {
            errors.push('dataSource.type');

            return;
        }

        if (dataSource.type === 'none') {
            if (!hasOnlyKeys(dataSource, ['type'])) {
                errors.push('dataSource.none.structure');
            }

            return;
        }

        if (dataSource.type === 'entity') {
            if (!hasOnlyKeys(dataSource, ['type', 'entityType', 'relationshipDepth'])) {
                errors.push('dataSource.entity.structure');

                return;
            }

            if (typeof dataSource.entityType !== 'string' ||
                !/^[A-Za-z][A-Za-z0-9]{0,99}$/.test(dataSource.entityType)) {
                errors.push('dataSource.entity.entityType');
            }

            if (!Number.isInteger(dataSource.relationshipDepth) ||
                dataSource.relationshipDepth < 1 || dataSource.relationshipDepth > 3) {
                errors.push('dataSource.entity.relationshipDepth');
            }

            return;
        }

        if (!hasOnlyKeys(dataSource, ['type', 'format'], ['worksheet'])) {
            errors.push('dataSource.spreadsheet.structure');

            return;
        }

        if (!['csv', 'xlsx'].includes(dataSource.format)) {
            errors.push('dataSource.spreadsheet.format');
        }

        if ('worksheet' in dataSource && (
            dataSource.format === 'csv' ||
            typeof dataSource.worksheet !== 'string' ||
            dataSource.worksheet.length < 1 ||
            dataSource.worksheet.length > 100
        )) {
            errors.push('dataSource.spreadsheet.worksheet');
        }
    };

    return class LayoutPrecheck {
        constructor(customPageSizes = [], flowLimits = {}) {
            this.pageDimensions = {...PAGE_DIMENSIONS_MM};
            this.flowStructure = new FlowStructure(flowLimits);

            customPageSizes.forEach(definition => {
                if (definition && typeof definition.id === 'string' &&
                    typeof definition.widthMm === 'number' &&
                    typeof definition.heightMm === 'number') {
                    this.pageDimensions[definition.id] = {
                        width: definition.widthMm,
                        height: definition.heightMm,
                    };
                }
            });
        }

        check(layout) {
            const errors = [];
            let normalized;

            try {
                normalized = Json.canonicalize(layout);
            } catch (error) {
                return {valid: false, errors: ['layout.json']};
            }

            if (!Json.isPlainObject(normalized)) {
                return {valid: false, errors: ['layout.type']};
            }

            ROOT_KEYS.forEach(key => {
                if (!(key in normalized)) {
                    errors.push(`layout.required.${key}`);
                }
            });

            Object.keys(normalized).forEach(key => {
                if (!ROOT_KEYS.includes(key)) {
                    errors.push(`layout.unknown.${key}`);
                }
            });

            if (normalized.schemaVersion !== 1) {
                errors.push('schemaVersion.unsupported');
            }

            if (!Array.isArray(normalized.capabilities) ||
                normalized.capabilities.some(marker => marker !== 'layout.flow') ||
                new Set(normalized.capabilities).size !== normalized.capabilities.length) {
                errors.push('capabilities.unsupported');
            }

            if (!hasOnlyKeys(normalized.document, [
                'page',
                'defaults',
                'chrome',
                'titlePattern',
                'filenamePattern',
            ], ['style'])) {
                errors.push('document.structure');
            } else {
                validatePage(normalized.document.page, errors, this.pageDimensions);
                validateDefaults(normalized.document.defaults, errors);
                validateChrome(normalized.document.chrome, normalized.document.page, normalized, errors);

                if (typeof normalized.document.titlePattern !== 'string' ||
                    normalized.document.titlePattern.length > 255) {
                    errors.push('document.titlePattern');
                }

                if (typeof normalized.document.filenamePattern !== 'string' ||
                    normalized.document.filenamePattern.length < 1 ||
                    normalized.document.filenamePattern.length > 255 ||
                    /[\\/\u0000-\u001F]/.test(normalized.document.filenamePattern)) {
                    errors.push('document.filenamePattern');
                }
            }

            validateDataSource(normalized.dataSource, errors);

            try {
                NodeTree.index(normalized);

                errors.push(...this.flowStructure.validateLayout(normalized));
            } catch (error) {
                errors.push('nodes.structure');
            }

            return {
                valid: errors.length === 0,
                errors,
            };
        }
    };
});
