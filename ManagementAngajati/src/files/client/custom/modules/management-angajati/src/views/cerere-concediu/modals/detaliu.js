define(['views/modals/detail'], (FereastraDetaliu) => {
    /**
     * Cererile de concediu se modifică exclusiv prin operațiile dedicate
     * de aprobare, respingere și anulare. Fereastra Calendarului rămâne
     * intenționat doar pentru consultare.
     */
    return class FereastraDetaliuCerereConcediu extends FereastraDetaliu {
        editDisabled = true
        removeDisabled = true
        fullFormDisabled = true
    };
});
