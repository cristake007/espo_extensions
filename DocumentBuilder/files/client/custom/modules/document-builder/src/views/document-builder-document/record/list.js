define(['views/record/list'], (RecordListView) => {
    return class extends RecordListView {
        afterRender() {
            super.afterRender();

            if (this.collection.length) {
                return;
            }

            const $empty = this.$el.find('.no-data');

            if (!$empty.length) {
                return;
            }

            const $content = $('<div>')
                .addClass('document-builder-generated-documents-empty text-center')
                .append(
                    $('<span>')
                        .addClass('fas fa-file-pdf fa-3x text-muted')
                        .attr('aria-hidden', 'true')
                )
                .append(
                    $('<h4>')
                        .addClass('document-builder-generated-documents-empty-title')
                        .text(this.translate(
                            'generatedDocumentsEmptyTitle',
                            'messages',
                            'DocumentBuilderDocument'
                        ))
                )
                .append(
                    $('<p>')
                        .addClass('text-muted')
                        .text(this.translate(
                            'generatedDocumentsEmptyText',
                            'messages',
                            'DocumentBuilderDocument'
                        ))
                )
                .append(
                    $('<a>')
                        .addClass('btn btn-primary')
                        .attr('href', '#DocumentBuilderTemplate/create')
                        .text(this.translate(
                            'createTemplate',
                            'labels',
                            'DocumentBuilderDocument'
                        ))
                );

            $empty.empty().append($content);
        }
    };
});
