<?php

namespace App\Http\Controllers;

use App\Models\ScheduledTask;
use App\Models\ScheduledTaskLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Console\Output\BufferedOutput;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('view schedule');

        $tasks = ScheduledTask::query()
            ->with('lastLog')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ScheduledTask $task) => [
                'id' => $task->id,
                'command' => $task->command,
                'description' => $task->description,
                'expression' => $task->expression,
                'active' => $task->active,
                'last_run' => $task->lastLog->first()?->started_at?->diffForHumans(),
                'last_exit_code' => $task->lastLog->first()?->exit_code,
                'created_at' => $task->created_at->diffForHumans(),
            ]);

        return Inertia::render('admin/schedule/index', [
            'tasks' => $tasks,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manage schedule');

        return Inertia::render('admin/schedule/create', [
            'commands' => $this->getAvailableCommands(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage schedule');

        $validated = $request->validate([
            'command' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'expression' => ['required', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone'],
            'parameters' => ['nullable', 'array'],
            'without_overlapping' => ['boolean'],
            'on_one_server' => ['boolean'],
            'run_in_maintenance' => ['boolean'],
            'active' => ['boolean'],
        ]);

        ScheduledTask::create($validated);

        return Redirect::route('admin.schedule.index')
            ->with('flash', ['toast' => 'Tarea programada creada exitosamente.']);
    }

    public function edit(ScheduledTask $task): Response
    {
        $this->authorize('manage schedule');

        return Inertia::render('admin/schedule/edit', [
            'task' => $task,
            'commands' => $this->getAvailableCommands(),
        ]);
    }

    public function update(Request $request, ScheduledTask $task): RedirectResponse
    {
        $this->authorize('manage schedule');

        $validated = $request->validate([
            'command' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'expression' => ['required', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone'],
            'parameters' => ['nullable', 'array'],
            'without_overlapping' => ['boolean'],
            'on_one_server' => ['boolean'],
            'run_in_maintenance' => ['boolean'],
            'active' => ['boolean'],
        ]);

        $task->update($validated);

        return Redirect::route('admin.schedule.index')
            ->with('flash', ['toast' => 'Tarea programada actualizada exitosamente.']);
    }

    public function destroy(ScheduledTask $task): RedirectResponse
    {
        $this->authorize('manage schedule');

        $task->delete();

        return Redirect::route('admin.schedule.index')
            ->with('flash', ['toast' => 'Tarea programada eliminada.']);
    }

    public function toggle(ScheduledTask $task): RedirectResponse
    {
        $this->authorize('manage schedule');

        $task->update(['active' => ! $task->active]);

        $status = $task->active ? 'activada' : 'desactivada';

        return Redirect::route('admin.schedule.index')
            ->with('flash', ['toast' => "Tarea programada {$status}."]);
    }

    public function run(ScheduledTask $task): RedirectResponse
    {
        $this->authorize('run schedule');

        $output = new BufferedOutput;
        $params = $task->parameters ?? [];

        $log = $task->logs()->create([
            'started_at' => now(),
        ]);

        try {
            $exitCode = Artisan::call($task->command, $params, $output);

            $log->update([
                'finished_at' => now(),
                'exit_code' => $exitCode,
                'output' => $output->fetch(),
                'duration_ms' => (int) $log->started_at->diffInMilliseconds(now()),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'finished_at' => now(),
                'exit_code' => 1,
                'output' => $e->getMessage(),
                'duration_ms' => (int) $log->started_at->diffInMilliseconds(now()),
            ]);
        }

        return Redirect::route('admin.schedule.index')
            ->with('flash', ['toast' => 'Tarea ejecutada manualmente.']);
    }

    public function history(ScheduledTask $task): Response
    {
        $this->authorize('view schedule');

        $logs = $task->logs()
            ->orderByDesc('started_at')
            ->paginate(50);

        return Inertia::render('admin/schedule/history', [
            'task' => [
                'id' => $task->id,
                'command' => $task->command,
                'description' => $task->description,
            ],
            'logs' => $logs->through(fn (ScheduledTaskLog $log) => [
                'id' => $log->id,
                'started_at' => $log->started_at->toIso8601String(),
                'finished_at' => $log->finished_at?->toIso8601String(),
                'exit_code' => $log->exit_code,
                'output' => $log->output,
                'duration_ms' => $log->duration_ms,
            ]),
        ]);
    }

    public function commands(): array
    {
        $this->authorize('manage schedule');

        return $this->getAvailableCommands();
    }

    protected function getAvailableCommands(): array
    {
        $commands = [];
        $all = Artisan::all();

        foreach ($all as $name => $command) {
            if ($command->isHidden()) {
                continue;
            }

            if (str_starts_with($name, 'env:') || $name === 'serve' || $name === 'tinker') {
                continue;
            }

            $commands[] = [
                'name' => $name,
                'description' => $command->getDescription(),
                'arguments' => $command->getDefinition()->getArguments(),
                'options' => $command->getDefinition()->getOptions(),
            ];
        }

        return $commands;
    }
}
