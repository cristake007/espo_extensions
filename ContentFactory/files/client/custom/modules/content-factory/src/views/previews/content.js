define([
    'content-factory:views/previews/social-post',
    'content-factory:views/previews/vertical-video',
    'content-factory:views/previews/video',
    'content-factory:views/previews/carousel',
], (SocialPost, VerticalVideo, Video, Carousel) => {
    const hashtags = value => Array.isArray(value) ? value.join(' ') : '';

    const remoteImageSource = value => {
        if (typeof value !== 'string' || /\s/.test(value)) {
            return '';
        }

        try {
            const url = new URL(value);

            return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password
                ? value
                : '';
        } catch (error) {
            return '';
        }
    };

    const imageSource = image => {
        if (!image || typeof image !== 'object') {
            return '';
        }

        if (
            typeof image.imageDataUrl === 'string' &&
            /^data:image\/(?:png|jpeg|webp|gif);base64,[A-Za-z0-9+/]+={0,2}$/i.test(
                image.imageDataUrl
            )
        ) {
            return image.imageDataUrl;
        }

        const remoteSource = remoteImageSource(image.imageUrl);

        if (remoteSource) {
            return remoteSource;
        }

        return '';
    };

    const postModel = asset => {
        const image = asset.image && typeof asset.image === 'object' ? asset.image : null;

        return {
            hook: asset.headline || asset.hook || '',
            body: asset.text || asset.body || '',
            caption: asset.text || asset.caption || asset.body || '',
            callToAction: asset.cta || asset.callToAction || '',
            hashtags: Array.isArray(asset.hashtags) ? hashtags(asset.hashtags) : '',
            mediaHook: image && image.headline ? image.headline : (asset.headline || ''),
            imageSource: imageSource(image),
        };
    };

    const videoModel = asset => {
        const firstScene = Array.isArray(asset.scenes) ? asset.scenes[0] : null;

        return {
            hook: asset.hook || '',
            caption: asset.caption || asset.body || '',
            callToAction: asset.cta || asset.callToAction || '',
            hashtags: Array.isArray(asset.hashtags) ? hashtags(asset.hashtags) : (asset.hashtags || ''),
            mediaHook: firstScene && firstScene.onScreenText
                ? firstScene.onScreenText
                : (asset.hook || ''),
            videoTitle: asset.hook || asset.videoTitle || '',
            videoDescription: asset.caption || asset.videoDescription || '',
            durationSeconds: asset.durationSeconds || null,
        };
    };

    const carouselModel = asset => {
        if (Array.isArray(asset.slides) && asset.slides.length) {
            return asset;
        }

        return {
            caption: asset.caption || asset.body || '',
            slides: [{
                title: asset.hook || '',
                text: asset.body || asset.caption || '',
            }],
        };
    };

    const legacyAsset = content => {
        const currentKeys = ['post', 'reel', 'video', 'short', 'carousel'];
        const hasCurrentAsset = currentKeys.some(key => Object.hasOwn(content, key));

        return !hasCurrentAsset && (content.hook || content.body || content.caption)
            ? content
            : null;
    };

    const renderAtomic = (view, platform, format, content) => {
        const asset = content[format] || legacyAsset(content);

        if (!asset) {
            return $('<p>').addClass('text-danger').text(
                view.translate('Generated Content Load Failed', 'messages')
            );
        }

        if (format === 'post') {
            return SocialPost(view, platform, postModel(asset));
        }

        if (format === 'reel' || format === 'short') {
            return VerticalVideo(view, platform, format, videoModel(asset));
        }

        if (format === 'video') {
            return Video(view, platform, videoModel(asset));
        }

        return Carousel(view, carouselModel(asset));
    };

    return (view, target, unusedBrief, generation) => {
        const content = generation && generation.content ? generation.content : {};
        const formats = view.generatorConfig.formatsForPreview(target.format);
        const grid = $('<div>').addClass('content-factory-preview-grid')
            .toggleClass('has-multiple', formats.length > 1);

        formats.forEach(format => {
            grid.append(
                $('<section>').addClass('content-factory-preview-item').attr({
                    'data-preview-platform': target.platform,
                    'data-preview-format': format,
                }).append(
                    $('<div>').addClass('content-factory-preview-meta').append(
                        $('<span>').addClass('label label-default').text(
                            view.translate(
                                view.generatorConfig.findPlatform(target.platform).label,
                                'labels'
                            )
                        ),
                        $('<span>').addClass('text-muted').text(
                            view.translate(view.generatorConfig.formatLabels[format], 'labels')
                        ),
                        $('<small>').addClass('text-muted').text(
                            view.translate('Generated Result', 'labels')
                        )
                    ),
                    renderAtomic(view, target.platform, format, content)
                )
            );
        });

        return grid;
    };
});
