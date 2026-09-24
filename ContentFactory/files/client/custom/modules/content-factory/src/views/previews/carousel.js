define(['content-factory:views/previews/shared'], Shared => {
    return (view, content) => {
        const slides = Array.isArray(content.slides) ? content.slides : [];
        const firstSlide = slides[0] || {title: '', text: ''};

        return $('<div>')
        .addClass('content-factory-social-card content-factory-carousel-card').append(
            $('<div>').addClass('content-factory-post-header').append(
                Shared.brand(view, view.translate('Sponsored', 'labels'), true)
            ),
            $('<div>').addClass('content-factory-carousel-stack').append(
                $('<span>').addClass('content-factory-carousel-layer layer-two'),
                $('<span>').addClass('content-factory-carousel-layer layer-one'),
                $('<div>').addClass('content-factory-carousel-slide').append(
                    $('<span>').addClass('label label-default content-factory-slide-count').text(
                        `1 / ${slides.length || 1}`
                    ),
                    $('<small>').addClass('text-primary').text(Shared.companyName(view)),
                    $('<div>').append(
                        $('<h3>').text(firstSlide.title),
                        $('<p>').addClass('text-muted').text(firstSlide.text)
                    ),
                    $('<strong>').addClass('text-primary').text(
                        view.translate('Slide Continue', 'labels')
                    )
                )
            ),
            $('<div>').addClass('content-factory-instagram-actions').append(
                $('<span>').append(
                    $('<span>').addClass('far fa-heart'),
                    $('<span>').addClass('far fa-comment'),
                    $('<span>').addClass('far fa-paper-plane')
                ),
                $('<span>').addClass('far fa-bookmark')
            ),
            $('<div>').addClass('content-factory-carousel-dots').append(
                ...slides.map((unused, index) => $('<span>').toggleClass('is-active', index === 0))
            ),
            $('<p>').addClass('content-factory-carousel-caption').append(
                $('<strong>').text(`${Shared.companyName(view)} `),
                document.createTextNode(content.caption || '')
            )
        );
    };
});
