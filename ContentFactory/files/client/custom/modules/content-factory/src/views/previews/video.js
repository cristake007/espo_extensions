define(['content-factory:views/previews/shared'], Shared => {
    const youtubeVideo = (view, content) => $('<div>')
        .addClass('content-factory-social-card content-factory-youtube-card').append(
            Shared.media(content.mediaHook || content.hook, {
                play: true,
                duration: content.durationSeconds ? `00:${String(content.durationSeconds).padStart(2, '0')}` : '',
            }),
            $('<div>').addClass('content-factory-video-copy').append(
                $('<h4>').text(content.videoTitle),
                $('<div>').addClass('content-factory-video-channel').append(
                    Shared.brand(view, view.translate('Subscribers', 'labels'), true),
                    $('<button>', {type: 'button'}).addClass('btn btn-primary btn-sm').text(
                        view.translate('Subscribe', 'labels')
                    )
                ),
                $('<small>').addClass('text-muted').text(view.translate('YouTube Metadata', 'labels')),
                $('<p>').addClass('content-factory-video-description').text(content.videoDescription)
            )
        );

    const feedVideo = (view, platform, content) => $('<div>')
        .addClass(`content-factory-social-card is-${platform}`).append(
            $('<div>').addClass('content-factory-post-header').append(
                Shared.brand(view, view.translate(platform === 'linkedin' ? 'Followers' : 'Now', 'labels')),
                $('<span>').addClass('fas fa-ellipsis-h text-muted').attr('aria-hidden', 'true')
            ),
            $('<div>').addClass('content-factory-post-copy').append(
                $('<strong>').text(content.videoTitle),
                $('<p>').text(content.caption)
            ),
            Shared.media(content.mediaHook || content.hook, {
                play: true,
                duration: content.durationSeconds ? `00:${String(content.durationSeconds).padStart(2, '0')}` : '',
            }),
            $('<div>').addClass('content-factory-feed-actions').append(
                Shared.iconAction(view, 'fa-thumbs-up', 'Like'),
                Shared.iconAction(view, 'fa-comment', 'Comment'),
                Shared.iconAction(view, 'fa-share', 'Share')
            )
        );

    return (view, platform, content) => platform === 'youtube'
        ? youtubeVideo(view, content)
        : feedVideo(view, platform, content);
});
