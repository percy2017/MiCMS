<?php

use Illuminate\Support\Facades\File;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $logDir = storage_path('logs/test-logs-'.uniqid());
    File::makeDirectory($logDir, 0755, true);
    config(['log-viewer.path' => $logDir]);
    config(['log-viewer.include_files' => ['*.log']]);
    config(['log-viewer.exclude_files' => []]);
    $svc = app('log-viewer');
    $svc->clearFileCache();
});

afterEach(function () {
    $logDir = config('log-viewer.path');
    if (is_string($logDir) && File::exists($logDir) && str_contains($logDir, 'test-logs-')) {
        File::deleteDirectory($logDir);
    }
});

function writeTestLog(string $name, string $content): string
{
    $dir = config('log-viewer.path');
    $path = $dir.'/'.$name;
    File::put($path, $content);

    return $name;
}

test('admin can view the log list', function () {
    writeTestLog('laravel.log', "[2026-06-08 10:00:00] local.INFO: Test info\n");

    actingAs(adminUser())
        ->get(route('admin.logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/logs/index')
            ->has('files', 1)
            ->where('files.0.name', 'laravel.log')
        );
});

test('editor cannot view the log list', function () {
    writeTestLog('laravel.log', "[2026-06-08 10:00:00] local.INFO: Test\n");

    actingAs(editorUser())
        ->get(route('admin.logs.index'))
        ->assertForbidden();
});

test('basic user cannot view the log list', function () {
    writeTestLog('laravel.log', "[2026-06-08 10:00:00] local.INFO: Test\n");

    actingAs(basicUser())
        ->get(route('admin.logs.index'))
        ->assertForbidden();
});

test('admin can view a log file detail', function () {
    $lines = [];
    for ($i = 0; $i < 10; $i++) {
        $lines[] = "[2026-06-08 10:00:0{$i}] local.INFO: Message {$i}";
    }
    writeTestLog('laravel.log', implode("\n", $lines)."\n");

    actingAs(adminUser())
        ->get(route('admin.logs.show', ['file' => 'laravel.log']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/logs/show')
            ->where('file.name', 'laravel.log')
            ->has('entries', 10)
            ->where('pagination.total', 10)
        );
});

test('search filter works', function () {
    $content = "[2026-06-08 10:00:00] local.INFO: User logged in\n"
        ."[2026-06-08 10:01:00] local.ERROR: Something broke\n"
        ."[2026-06-08 10:02:00] local.INFO: User logged out\n";
    writeTestLog('laravel.log', $content);

    actingAs(adminUser())
        ->get(route('admin.logs.show', ['file' => 'laravel.log', 'q' => 'broke']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries', 1)
            ->where('entries.0.message', 'Something broke')
        );
});

test('level filter works', function () {
    $content = "[2026-06-08 10:00:00] local.INFO: An info\n"
        ."[2026-06-08 10:01:00] local.ERROR: An error\n"
        ."[2026-06-08 10:02:00] local.WARNING: A warning\n";
    writeTestLog('laravel.log', $content);

    actingAs(adminUser())
        ->get(route('admin.logs.show', ['file' => 'laravel.log', 'level' => 'error']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries', 1)
            ->where('entries.0.level', 'error')
        );
});

test('path traversal is blocked', function () {
    writeTestLog('laravel.log', "[2026-06-08 10:00:00] local.INFO: Test\n");

    actingAs(adminUser())
        ->get(route('admin.logs.show', ['file' => '..%2F..%2Fetc%2Fpasswd']))
        ->assertNotFound();
});

test('admin can download a log file', function () {
    writeTestLog('laravel.log', "[2026-06-08 10:00:00] local.INFO: Test content\n");

    actingAs(adminUser())
        ->get(route('admin.logs.download', ['file' => 'laravel.log']))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('admin can delete a log file', function () {
    $name = writeTestLog('disposable.log', "[2026-06-08 10:00:00] local.INFO: bye\n");
    $path = config('log-viewer.path').'/'.$name;
    expect(File::exists($path))->toBeTrue();

    $response = actingAs(adminUser())
        ->from(route('admin.logs.index'))
        ->delete(route('admin.logs.destroy', ['file' => $name]));

    $response->assertRedirect(route('admin.logs.index'));
    $response->assertSessionHas('success');

    expect(File::exists($path))->toBeFalse();
});

test('editor cannot delete log files', function () {
    $name = writeTestLog('protected.log', "[2026-06-08 10:00:00] local.INFO: stay\n");

    actingAs(editorUser())
        ->delete(route('admin.logs.destroy', ['file' => $name]))
        ->assertForbidden();

    expect(File::exists(config('log-viewer.path').'/'.$name))->toBeTrue();
});
