define(['content-factory:views/previews/shared'], Shared => {
    const viewportButton = (view, viewport, icon, active) => $('<button>', {
        type: 'button',
        'aria-pressed': active ? 'true' : 'false',
        title: view.translate(viewport === 'desktop' ? 'Desktop Preview' : 'Mobile Preview', 'labels'),
    }).addClass(`btn btn-default btn-xs${active ? ' active' : ''}`)
        .attr('data-post-viewport', viewport)
        .append(
            $('<span>').addClass(`fas ${icon}`).attr('aria-hidden', 'true'),
            document.createTextNode(` ${view.translate(
                viewport === 'desktop' ? 'Desktop' : 'Mobile',
                'labels'
            )}`)
        );

    const viewportPreview = (view, card) => {
        const stage = $('<div>')
            .addClass('content-factory-post-viewport-stage is-desktop')
            .append(card);
        const buttons = $('<div>')
            .addClass('btn-group content-factory-post-viewport-buttons')
            .attr({
                role: 'group',
                'aria-label': view.translate('Post Preview Viewport', 'labels'),
            })
            .append(
                viewportButton(view, 'desktop', 'fa-desktop', true),
                viewportButton(view, 'mobile', 'fa-mobile-alt', false)
            );
        const preview = $('<div>').addClass('content-factory-post-viewport').append(
            $('<div>').addClass('content-factory-post-viewport-toolbar').append(buttons),
            stage
        );

        buttons.on('click', '[data-post-viewport]', event => {
            event.preventDefault();

            const selected = String($(event.currentTarget).attr('data-post-viewport'));

            if (!['desktop', 'mobile'].includes(selected)) {
                return;
            }

            stage.toggleClass('is-desktop', selected === 'desktop')
                .toggleClass('is-mobile', selected === 'mobile');
            buttons.find('[data-post-viewport]').each((unusedIndex, element) => {
                const button = $(element);
                const active = button.attr('data-post-viewport') === selected;

                button.toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
            });
        });

        return preview;
    };

    const feedPost = (view, platform, content) => {
        const linkedIn = platform === 'linkedin';
        const actions = linkedIn
            ? [['fa-thumbs-up', 'Like'], ['fa-comment', 'Comment'], ['fa-repeat', 'Repost'], ['fa-paper-plane', 'Send']]
            : [['fa-thumbs-up', 'Like'], ['fa-comment', 'Comment'], ['fa-share', 'Share']];

        const copy = $('<div>').addClass('content-factory-post-copy').append(
            $('<strong>').text(content.hook),
            $('<p>').text(content.body),
            $('<p>').addClass('text-primary').text(content.callToAction)
        );

        if (content.hashtags) {
            copy.append(
                $('<p>').addClass('content-factory-post-hashtags text-primary')
                    .text(content.hashtags)
            );
        }

        return $('<div>').addClass(`content-factory-social-card is-${platform}`).append(
            $('<div>').addClass('content-factory-post-header').append(
                Shared.brand(view, view.translate(linkedIn ? 'Followers' : 'Now', 'labels')),
                $('<span>').addClass('fas fa-ellipsis-h text-muted').attr('aria-hidden', 'true')
            ),
            copy,
            Shared.media(content.mediaHook || content.hook, {
                portrait: Boolean(content.imageSource),
                imageSource: content.imageSource,
            }),
            $('<div>').addClass('content-factory-reactions text-muted').append(
                $('<span>').text(view.translate('Reactions', 'labels')),
                $('<span>').text(view.translate('Comments', 'labels'))
            ),
            $('<div>').addClass('content-factory-feed-actions').append(
                ...actions.map(action => Shared.iconAction(view, action[0], action[1]))
            )
        );
    };

    const instagramPost = (view, content) => {
        const copy = $('<div>').addClass('content-factory-instagram-copy').append(
            $('<strong>').text(view.translate('Liked By', 'labels')),
            $('<p>').append($('<strong>').text(content.hook)),
            $('<p>').append(
                $('<strong>').text(`${Shared.companyName(view)} `),
                document.createTextNode(content.caption)
            )
        );

        if (content.callToAction) {
            copy.append($('<p>').addClass('text-primary').text(content.callToAction));
        }

        if (content.hashtags) {
            copy.append(
                $('<p>').addClass('content-factory-post-hashtags text-primary')
                    .text(content.hashtags)
            );
        }

        return $('<div>')
        .addClass('content-factory-social-card content-factory-instagram-card').append(
            $('<div>').addClass('content-factory-post-header').append(
                Shared.brand(view, view.translate('Sponsored', 'labels'), true),
                $('<span>').addClass('fas fa-ellipsis-h text-muted').attr('aria-hidden', 'true')
            ),
            Shared.media(content.mediaHook || content.hook, {
                portrait: true,
                imageSource: content.imageSource,
            }),
            $('<div>').addClass('content-factory-instagram-actions').append(
                $('<span>').append(
                    $('<span>').addClass('far fa-heart'),
                    $('<span>').addClass('far fa-comment'),
                    $('<span>').addClass('far fa-paper-plane')
                ),
                $('<span>').addClass('far fa-bookmark')
            ),
            copy
        );
    };

    return (view, platform, content) => viewportPreview(
        view,
        platform === 'instagram'
            ? instagramPost(view, content)
            : feedPost(view, platform, content)
    );
});
