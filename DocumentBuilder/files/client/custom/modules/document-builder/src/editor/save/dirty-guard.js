define([], () => {
    return class DirtyGuard {
        constructor(router, owner) {
            this.router = router;
            this.owner = owner;
            this.active = false;
        }

        sync(isDirty) {
            if (isDirty && !this.active) {
                this.router.addLeaveOutObject(this.owner);
                this.active = true;

                return;
            }

            if (!isDirty && this.active) {
                this.router.removeLeaveOutObject(this.owner);
                this.active = false;
            }
        }

        dispose() {
            if (this.active) {
                this.router.removeLeaveOutObject(this.owner);
                this.active = false;
            }
        }
    };
});
