define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <div class="header page-header">
                <h3>{{translate 'Administration'}}</h3>
            </div>
            <div class="admin-content attendance-manager-admin-page">
                <div class="row">
                    <div class="col-sm-6 col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title">
                                    {{translate 'Attendance Management' category='labels' scope='Admin'}}
                                </h4>
                            </div>
                            <div class="panel-body">
                                <a class="admin-panel-link" href="#Admin/attendanceManagementSettings">
                                    <span class="fas fa-business-time" aria-hidden="true"></span>
                                    <span>{{translate 'Attendance Settings' category='labels' scope='Admin'}}</span>
                                </a>
                                <p class="text-muted small">
                                    {{translate 'attendanceManagerSettings' category='descriptions' scope='Admin'}}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        afterRender() {
            this.setPageTitle(this.translate('Administration'));
        }
    };
});
