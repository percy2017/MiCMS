<?php

namespace App\Services;

use App\Models\ScheduledTask;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleLoaderService
{
    public function load(Schedule $schedule): void
    {
        ScheduledTask::query()
            ->where('active', true)
            ->each(function (ScheduledTask $task) use ($schedule): void {
                $event = $schedule->command($task->command, $this->resolveParameters($task))
                    ->description($task->description ?? $task->command)
                    ->cron($task->expression);

                if ($task->timezone) {
                    $event->timezone($task->timezone);
                }

                if ($task->without_overlapping) {
                    $event->withoutOverlapping();
                }

                if ($task->on_one_server) {
                    $event->onOneServer();
                }

                if ($task->run_in_maintenance) {
                    $event->evenInMaintenanceMode();
                }

                $event->thenWithOutput(function () use ($task): void {
                    $task->logs()->create([
                        'exit_code' => 0,
                        'started_at' => now(),
                        'finished_at' => now(),
                    ]);
                });
            });
    }

    protected function resolveParameters(ScheduledTask $task): array
    {
        $params = $task->parameters ?? [];
        $args = [];
        $options = [];

        foreach ($params as $key => $value) {
            if (str_starts_with($key, '--')) {
                $options[substr($key, 2)] = $value;
            } else {
                $args[$key] = $value;
            }
        }

        $resolved = [];
        foreach ($args as $name => $value) {
            $resolved[] = $name.'='.$value;
        }

        foreach ($options as $name => $value) {
            if ($value === true) {
                $resolved[] = '--'.$name;
            } elseif ($value !== false && $value !== null) {
                $resolved[] = '--'.$name.'='.$value;
            }
        }

        return $resolved;
    }
}
