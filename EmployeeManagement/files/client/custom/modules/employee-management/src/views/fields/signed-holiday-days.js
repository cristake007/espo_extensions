define(['views/fields/float'], FloatFieldView => class extends FloatFieldView {
    getValueForDisplay() {
        const formatted = super.getValueForDisplay();
        const value = Number(this.model.get(this.name));

        if (!Number.isFinite(value) || value === 0) {
            return formatted;
        }

        if (value > 0) {
            return `+${formatted}`;
        }

        return `−${formatted.replace(/^-/, '')}`;
    }
});
