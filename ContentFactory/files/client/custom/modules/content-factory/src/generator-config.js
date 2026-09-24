define(() => {
    const create = definition => {
        if (!definition || definition.schemaVersion !== 1) {
            throw new Error('Content Factory generator metadata is missing or unsupported.');
        }

        const platforms = Array.isArray(definition.platforms) ? definition.platforms : [];
        const styles = Array.isArray(definition.styles) ? definition.styles : [];
        const formats = definition.formats || {};
        const formatLabels = Object.fromEntries(
            Object.entries(formats).map(([id, item]) => [id, item.label])
        );
        const findPlatform = id => platforms.find(platform => platform.id === id);

        const defaultTarget = () => ({
            platform: platforms[0].id,
            format: platforms[0].defaultFormat,
        });

        const selectPlatform = (target, platformId) => {
            const platform = findPlatform(platformId);

            if (!platform) {
                return {...target};
            }

            return {
                platform: platform.id,
                format: platform.formats.includes(target.format)
                    ? target.format
                    : platform.defaultFormat,
            };
        };

        const selectFormat = (target, format) => {
            const platform = findPlatform(target.platform);

            if (!platform || !platform.formats.includes(format)) {
                return {...target};
            }

            return {...target, format};
        };

        const formatsForPreview = format => {
            if (format === 'post-reel') {
                return ['post', 'reel'];
            }

            if (format === 'video-short') {
                return ['video', 'short'];
            }

            return [format];
        };

        return {
            platforms,
            styles,
            formats,
            formatLabels,
            findPlatform,
            defaultTarget,
            selectPlatform,
            selectFormat,
            formatsForPreview,
        };
    };

    return {create};
});
