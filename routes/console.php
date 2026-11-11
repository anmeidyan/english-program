<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('exams:finalize-expired')->everyMinute()->withoutOverlapping();
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();
Schedule::command('streaks:reset')->hourly();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run --only-db')->daily()->at('01:30');
