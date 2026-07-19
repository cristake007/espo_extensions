define([], () => {
    const isManualSave = event => Boolean(
        event &&
        !event.altKey &&
        (event.ctrlKey || event.metaKey) &&
        String(event.key).toLowerCase() === 's'
    );

    return Object.freeze({isManualSave});
});
