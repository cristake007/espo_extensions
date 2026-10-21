define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <main class="employee-administration-home-page">
                <header class="employee-administration-home-hero">
                    <div class="employee-administration-home-hero-icon" aria-hidden="true">
                        <span class="fas fa-user-cog"></span>
                    </div>
                    <div>
                        <div class="employee-administration-home-eyebrow">
                            {{translate 'Employee Management' category='labels' scope='EmployeeAdministration'}}
                        </div>
                        <h3>{{translate 'Administration'}}</h3>
                        <p>{{translate 'administrationHomeHelp' category='messages' scope='EmployeeAdministration'}}</p>
                    </div>
                </header>

                <section class="employee-administration-home-panel">
                    <div class="employee-administration-home-heading">
                        <div class="employee-administration-home-heading-copy">
                            <div class="employee-administration-home-section-icon" aria-hidden="true">
                                <span class="fas fa-th-large"></span>
                            </div>
                            <div>
                                <h4>{{translate 'Administration Areas' category='labels' scope='EmployeeAdministration'}}</h4>
                                <p>{{translate 'administrationAreasHelp' category='messages' scope='EmployeeAdministration'}}</p>
                            </div>
                        </div>
                        <div class="employee-administration-home-count">
                            {{sectionCount}} {{translate 'Available Areas' category='labels' scope='EmployeeAdministration'}}
                        </div>
                    </div>

                    {{#if hasSections}}
                    <div class="employee-administration-home-grid">
                        {{#each sections}}
                        <a class="employee-administration-home-card" href="#EmployeeAdministration/{{name}}">
                            <span class="employee-administration-home-card-icon" aria-hidden="true">
                                <span class="fas {{icon}}"></span>
                            </span>
                            <span class="employee-administration-home-card-copy">
                                <strong>{{label}}</strong>
                                <small>{{description}}</small>
                            </span>
                            <span class="fas fa-chevron-right employee-administration-home-card-arrow" aria-hidden="true"></span>
                        </a>
                        {{/each}}
                    </div>
                    {{else}}
                    <div class="employee-administration-home-empty">
                        <span class="fas fa-lock" aria-hidden="true"></span>
                        <strong>{{translate 'No Administration Areas' category='labels' scope='EmployeeAdministration'}}</strong>
                        <p>{{translate 'noAdministrationAreasHelp' category='messages' scope='EmployeeAdministration'}}</p>
                    </div>
                    {{/if}}
                </section>
            </main>
        `;

        data() {
            const icons = {
                attendance: 'fa-clock',
                holiday: 'fa-umbrella-beach',
                profiles: 'fa-id-card',
                attendanceMetadata: 'fa-business-time',
            };
            const sections = this.options.sections.map(name => ({
                name,
                icon: icons[name] || 'fa-cog',
                label: this.translate(name, 'labels', 'EmployeeAdministration'),
                description: this.translate(`${name}Description`, 'messages', 'EmployeeAdministration'),
            }));

            return {
                sections,
                sectionCount: sections.length,
                hasSections: sections.length > 0,
            };
        }

        afterRender() {
            this.setPageTitle(this.translate('Administration'));
        }
    };
});
