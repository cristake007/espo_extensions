define(['content-factory:views/previews/shared'], Shared => {
    return (view, platform, format, content) => {
        const label = format === 'short' ? 'Short' : 'Reel';

        return $('<div>').addClass('content-factory-phone-wrap').append(
            $('<div>').addClass('content-factory-phone-preview').append(
                Shared.media(content.mediaHook || content.hook, {vertical: true}),
                $('<span>').addClass('label label-default content-factory-video-badge').text(
                    view.translate(label, 'labels')
                ),
                $('<div>').addClass('content-factory-vertical-actions').append(
                    $('<span>').addClass('far fa-heart'),
                    $('<small>').text('128'),
                    $('<span>').addClass('far fa-comment'),
                    $('<small>').text('12'),
                    $('<span>').addClass('far fa-paper-plane')
                ),
                $('<div>').addClass('content-factory-vertical-caption').append(
                    Shared.brand(view, `${view.translate(label, 'labels')} · ${view.translate(
                        platform.charAt(0).toUpperCase() + platform.slice(1), 'labels'
                    )}`, true),
                    $('<p>').text(content.caption),
                    $('<p>').addClass('text-primary').text(content.callToAction)
                )
            )
        );
    };
});
