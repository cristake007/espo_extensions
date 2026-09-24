define(() => {
    const companyName = view => String(view.getConfig().get('applicationName') || 'Content Factory');

    const initials = name => name.trim().split(/\s+/).slice(0, 2)
        .map(part => part.charAt(0)).join('').toUpperCase() || 'CF';

    const brand = (view, secondary, compact = false) => {
        const name = companyName(view);

        return $('<div>').addClass('content-factory-preview-brand').append(
            $('<span>').addClass(
                `content-factory-preview-avatar${compact ? ' content-factory-preview-avatar-small' : ''}`
            ).text(initials(name)),
            $('<span>').addClass('content-factory-preview-brand-copy').append(
                $('<strong>').text(name),
                $('<small>').addClass('text-muted').text(secondary)
            )
        );
    };

    const media = (hook, options = {}) => {
        const aspectClass = options.vertical
            ? 'is-vertical'
            : (options.portrait ? 'is-portrait' : 'is-landscape');
        const element = $('<div>').addClass(
            `content-factory-preview-media ${aspectClass}`
        ).append(
            $('<span>').addClass('content-factory-media-shape content-factory-media-shape-one'),
            $('<span>').addClass('content-factory-media-shape content-factory-media-shape-two'),
            $('<strong>').addClass('content-factory-media-hook').text(hook)
        );

        if (options.imageSource) {
            const image = $('<img>', {
                src: options.imageSource,
                alt: hook || '',
                loading: 'lazy',
            }).addClass('content-factory-preview-media-image');

            image.on('error', () => {
                image.remove();
                element.removeClass('has-image');
            });
            element.addClass('has-image').prepend(image);
        }

        if (options.play) {
            element.append(
                $('<span>').addClass('content-factory-play').append(
                    $('<span>').addClass('fas fa-play').attr('aria-hidden', 'true')
                )
            );
        }

        if (options.duration) {
            element.append(
                $('<span>').addClass('content-factory-duration').text(options.duration)
            );
        }

        return element;
    };

    const iconAction = (view, icon, label) => $('<button>', {type: 'button'})
        .addClass('btn btn-link content-factory-social-action')
        .append(
            $('<span>').addClass(`fas ${icon}`).attr('aria-hidden', 'true'),
            $('<span>').text(view.translate(label, 'labels'))
        );

    return {brand, companyName, iconAction, media};
});
