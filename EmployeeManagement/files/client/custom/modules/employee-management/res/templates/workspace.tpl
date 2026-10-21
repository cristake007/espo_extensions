<div class="page-header"><h3>Employee Management <small>{{version}}</small></h3></div>
<div class="panel panel-default">
    <div class="panel-body">
        <p>{{translate 'StageOneReadOnly' scope='EmployeeManagement'}}</p>
        {{#if administration}}<p>{{translate 'AdministrationNotEnabled' scope='EmployeeManagement'}}</p>{{/if}}
        <ul class="list-group">
            {{#if attendanceEnabled}}<li class="list-group-item"><a href="#EmployeeAttendance">{{translate 'Attendance' category='labels' scope='EmployeeAttendanceRecord'}}</a></li>{{/if}}
            {{#if attendanceOverviewEnabled}}<li class="list-group-item"><a href="#EmployeeAttendanceOverview">{{translate 'Daily Matrix' category='labels' scope='EmployeeAttendanceRecord'}}</a></li>{{/if}}
            {{#if holidayBookingEnabled}}<li class="list-group-item"><a href="#EmployeeManagement/holiday">{{translate 'EmployeeHolidayRequest' category='scopeNamesPlural'}}</a></li>{{/if}}
            {{#each links}}
            <li class="list-group-item"><a href="#{{scope}}">{{label}}</a></li>
            {{/each}}
        </ul>
    </div>
</div>
