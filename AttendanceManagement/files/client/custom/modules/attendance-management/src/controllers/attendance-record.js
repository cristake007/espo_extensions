define(['controllers/record'], (RecordController) => {
    return class extends RecordController {
        actionAttendance() {
            this.main('attendance-management:views/attendance/my', {
                scope: this.name,
            });
        }
    };
});
