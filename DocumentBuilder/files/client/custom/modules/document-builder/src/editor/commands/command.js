define([], () => {
    return class Command {
        constructor() {
            this.executed = false;
        }

        execute(layout, context) {
            if (this.executed) {
                throw new Error('Editor command instances can only be executed once.');
            }

            this.executed = true;

            return this.apply(layout, context);
        }

        apply() {
            throw new Error('An editor command must implement apply.');
        }
    };
});
