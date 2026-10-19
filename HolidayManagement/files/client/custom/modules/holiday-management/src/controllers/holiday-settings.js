define(['controller', 'model', 'views/settings/edit'], (Controller, Model, SettingsEditView) => {
    return class extends Controller {
        actionIndex() {
            if (this.getUser().isAdmin()) {
                this.getRouter().dispatch('Admin', 'index');

                return;
            }

            const scopes = [
                'HolidayManagementSettings',
                'HolidayProfileManagement',
                'AttendanceOverview',
            ];

            if (!scopes.some(scope => this.getAcl().check(scope))) {
                throw new Espo.Exceptions.AccessDenied();
            }

            this.main('holiday-management:views/admin/index');
        }

        async actionOpen() {
            if (!this.getAcl().check('HolidayManagementSettings')) {
                throw new Espo.Exceptions.AccessDenied();
            }

            const data = await Espo.Ajax.getRequest('HolidayManagement/settings');
            const model = new Model(data);

            model.entityType = 'Settings';
            model.id = '1';
            model.save = async (attributes = {}, options = {}) => {
                const payload = {...model.attributes, ...attributes};

                delete payload.id;
                delete payload.isAdmin;

                const response = await Espo.Ajax.putRequest(
                    'HolidayManagement/settings',
                    payload,
                );

                model.set(response);
                model.trigger('sync', model, response, options);
                model.trigger('after:save', model, options);

                return model;
            };

            const view = new SettingsEditView({
                model,
                headerTemplate: 'admin/settings/headers/page',
                recordView: 'holiday-management:views/admin/settings',
                page: 'holidayManagementSettings',
                label: 'Holiday Management',
                optionsToPass: ['page', 'label'],
            });

            this.main(view);
        }
    };
});
