<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogViewer\DestroyRequest;
use App\Http\Requests\LogViewer\IndexRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\LogFile;
use Opcodes\LogViewer\LogLevels\LaravelLogLevel;
use Opcodes\LogViewer\Logs\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogViewerController extends Controller
{
    /**
     * @var list<string>
     */
    private const ERROR_LEVELS = ['error', 'critical', 'alert', 'emergency'];

    /**
     * @var list<string>
     */
    private const ALL_LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    public function index(IndexRequest $request): Response
    {
        $files = LogViewer::getFiles()->map(fn (LogFile $file): array => $this->fileSummary($file))->values()->all();

        return Inertia::render('admin/logs/index', [
            'files' => $files,
            'levels' => LaravelLogLevel::cases(),
        ]);
    }

    public function show(IndexRequest $request): Response
    {
        $file = $request->fileIdentifier()
            ?? LogViewer::getFiles()->first()?->identifier
            ?? abort(404, 'No hay archivos de log.');

        $logFile = LogViewer::getFile($file) ?? abort(404);

        $query = $logFile->logs();
        $search = $request->string('q')->toString();
        if ($search !== '') {
            $query->search($search);
        }

        $level = $request->string('level')->toString();
        if ($level !== '') {
            $query->only(strtoupper($level));
        }

        $perPage = 50;
        $paginator = $query->paginate($perPage, (int) $request->input('page', 1));

        $entries = collect($paginator->items())->map(fn (Log $log): array => $this->logEntry($log))->all();

        return Inertia::render('admin/logs/show', [
            'file' => [
                'identifier' => $logFile->identifier,
                'name' => basename($logFile->path),
                'path' => $logFile->path,
                'size' => $logFile->sizeFormatted(),
                'size_bytes' => $logFile->size(),
                'mtime' => Carbon::createFromTimestamp($logFile->mtime())->toIso8601String(),
                'mtime_human' => Carbon::createFromTimestamp($logFile->mtime())->diffForHumans(),
            ],
            'entries' => $entries,
            'filters' => [
                'q' => $search,
                'level' => $level,
            ],
            'availableLevels' => self::ALL_LEVELS,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function download(string $file): BinaryFileResponse
    {
        abort_unless(request()->user()?->can('view logs'), 403);
        $this->abortIfInvalidFile($file);

        $logFile = LogViewer::getFile($file);

        return $logFile->download();
    }

    public function destroy(DestroyRequest $request): RedirectResponse
    {
        $file = $request->fileIdentifier();
        $logFile = LogViewer::getFile($file);
        $logFile->delete();

        LogViewer::clearFileCache();

        return redirect()->route('admin.logs.index')->with('success', "Archivo {$logFile->name} eliminado.");
    }

    /**
     * @return array<string, mixed>
     */
    private function fileSummary(LogFile $file): array
    {
        $errorsTotal = 0;
        $errorsToday = 0;
        $today = Carbon::today();

        try {
            $counts = $file->logs()->getLevelCounts();
            foreach ($counts as $count) {
                $name = strtolower($count->level->getName());
                if (in_array($name, self::ERROR_LEVELS, true)) {
                    $errorsTotal += $count->count;
                }
            }

            $entries = $file->logs()->allLevels()->get();
            foreach ($entries as $entry) {
                $entryLevel = strtolower($entry->level ?? '');
                if (in_array($entryLevel, self::ERROR_LEVELS, true)
                    && $entry->datetime && $entry->datetime->isSameDay($today)) {
                    $errorsToday++;
                }
            }
        } catch (\Throwable) {
            $errorsTotal = 0;
            $errorsToday = 0;
        }

        return [
            'identifier' => $file->identifier,
            'name' => basename($file->path),
            'path' => $file->path,
            'size' => $file->sizeFormatted(),
            'size_bytes' => $file->size(),
            'mtime' => Carbon::createFromTimestamp($file->mtime())->toIso8601String(),
            'mtime_human' => Carbon::createFromTimestamp($file->mtime())->diffForHumans(),
            'errors_total' => $errorsTotal,
            'errors_today' => $errorsToday,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logEntry(Log $log): array
    {
        return [
            'datetime' => $log->datetime?->toIso8601String(),
            'datetime_human' => $log->datetime?->format('Y-m-d H:i:s'),
            'level' => strtolower($log->level ?? ''),
            'message' => $log->message,
            'context' => $log->context,
            'extra' => $log->extra,
            'text' => $log->getOriginalText(),
        ];
    }

    private function abortIfInvalidFile(string $file): void
    {
        if (! preg_match('/^[A-Za-z0-9._\-]+$/', $file)) {
            abort(404);
        }

        if (LogViewer::getFile($file) === null) {
            abort(404);
        }
    }
}
