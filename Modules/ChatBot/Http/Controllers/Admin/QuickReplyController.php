<?php

namespace Modules\ChatBot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Http\Requests\StoreQuickReplyRequest;
use Modules\ChatBot\Http\Requests\UpdateQuickReplyRequest;
use Modules\ChatBot\Models\QuickReply;

class QuickReplyController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view quick replies'), 403);

        $query = QuickReply::with('media:id,disk,path,mime_type,name,size')
            ->orderBy('sort')
            ->orderBy('id');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('shortcut', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }
        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }
        if ($request->has('enabled') && $request->input('enabled') !== '') {
            $query->where('enabled', $request->boolean('enabled'));
        }

        $replies = $query->get()->map(fn (QuickReply $r): array => [
            'id' => $r->id,
            'shortcut' => $r->shortcut,
            'title' => $r->title,
            'content' => $r->content,
            'category' => $r->category,
            'media_id' => $r->media_id,
            'media_url' => $r->media?->url(),
            'media_mime' => $r->media?->mime_type,
            'media_name' => $r->media?->name,
            'sort' => $r->sort,
            'enabled' => $r->enabled,
        ]);

        $categories = QuickReply::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        return Inertia::render('ChatBot::QuickReplies/Index', [
            'replies' => $replies,
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', ''),
                'enabled' => $request->input('enabled', ''),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('create quick replies'), 403);

        return Inertia::render('ChatBot::QuickReplies/Edit', [
            'reply' => [
                'id' => null,
                'is_new' => true,
                'shortcut' => '',
                'title' => '',
                'content' => '',
                'category' => null,
                'media_id' => null,
                'media_url' => null,
                'media_mime' => null,
                'media_name' => null,
                'sort' => 0,
                'enabled' => true,
            ],
        ]);
    }

    public function store(StoreQuickReplyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $reply = QuickReply::create([
            'shortcut' => $data['shortcut'],
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'category' => $data['category'] ?? null,
            'media_id' => $data['media_id'] ?? null,
            'sort' => $data['sort'] ?? (QuickReply::max('sort') ?? 0) + 1,
            'enabled' => $data['enabled'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('chatbot.admin.quick-replies.index')
            ->with('success', "Respuesta rápida '/{$reply->shortcut}' creada.");
    }

    public function edit(Request $request, QuickReply $quickReply): Response
    {
        abort_unless($request->user()?->can('update quick replies'), 403);
        $quickReply->load('media:id,disk,path,mime_type,name,size');

        return Inertia::render('ChatBot::QuickReplies/Edit', [
            'reply' => [
                'id' => $quickReply->id,
                'is_new' => false,
                'shortcut' => $quickReply->shortcut,
                'title' => $quickReply->title,
                'content' => $quickReply->content,
                'category' => $quickReply->category,
                'media_id' => $quickReply->media_id,
                'media_url' => $quickReply->media?->url(),
                'media_mime' => $quickReply->media?->mime_type,
                'media_name' => $quickReply->media?->name,
                'sort' => $quickReply->sort,
                'enabled' => $quickReply->enabled,
            ],
        ]);
    }

    public function update(UpdateQuickReplyRequest $request, QuickReply $quickReply): RedirectResponse
    {
        $data = $request->validated();

        $quickReply->update([
            'shortcut' => $data['shortcut'],
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'category' => $data['category'] ?? null,
            'media_id' => $data['media_id'] ?? null,
            'sort' => $data['sort'] ?? $quickReply->sort,
            'enabled' => $data['enabled'] ?? true,
        ]);

        return redirect()->route('chatbot.admin.quick-replies.index')
            ->with('success', "Respuesta rápida '/{$quickReply->fresh()->shortcut}' actualizada.");
    }

    public function destroy(Request $request, QuickReply $quickReply): RedirectResponse
    {
        abort_unless($request->user()?->can('delete quick replies'), 403);

        $quickReply->delete();

        return redirect()->route('chatbot.admin.quick-replies.index')
            ->with('success', 'Respuesta rápida eliminada.');
    }

    /**
     * API endpoint para el dropdown del composer (slash commands).
     * Devuelve solo las habilitadas, ordenadas.
     */
    public function api(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view quick replies'), 403);

        $replies = QuickReply::with('media:id,disk,path,mime_type,name,size')
            ->where('enabled', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (QuickReply $r): array => [
                'id' => $r->id,
                'shortcut' => $r->shortcut,
                'title' => $r->title,
                'content' => $r->content,
                'category' => $r->category,
                'media_id' => $r->media_id,
                'media_url' => $r->media?->url(),
                'media_mime' => $r->media?->mime_type,
                'media_name' => $r->media?->name,
            ]);

        return response()->json([
            'replies' => $replies,
        ]);
    }
}
