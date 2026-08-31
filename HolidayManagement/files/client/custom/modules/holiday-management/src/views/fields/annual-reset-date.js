define(['views/fields/date'], (DateFieldView) => {
    return class extends DateFieldView {
        stringifyDateValue(value) {
            return super.stringifyDateValue(this.normalizeMonthDay(value));
        }

        parseDate(value) {
            const normalizedValue = this.normalizeMonthDay(value);

            if (normalizedValue !== value) {
                return normalizedValue;
            }

            return super.parseDate(value);
        }

        normalizeMonthDay(value) {
            if (typeof value === 'string') {
                const match = /^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/.exec(value);

                if (match) {
                    const month = Number(match[1]);
                    const day = Number(match[2]);
                    const daysInMonth = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

                    if (day <= daysInMonth[month - 1]) {
                        return `2000-${value}`;
                    }
                }
            }

            return value;
        }
    };
});
