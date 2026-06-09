<?php

namespace Modules\ChatBot\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Http\Requests\UpdateWidgetRequest;
use Modules\ChatBot\Models\ChatBotWidget;

class WidgetController extends Controller
{
    public function edit(): Response
    {
        abort_unless(auth()->user()?->can('view chatbot'), 403);

        $widget = ChatBotWidget::current();
        $widget->loadMissing('avatar');

        return Inertia::render('ChatBot::Widget', [
            'widget' => [
                'id' => $widget->id,
                'enabled' => $widget->enabled,
                'title' => $widget->title,
                'subtitle' => $widget->subtitle,
                'greeting' => $widget->greeting,
                'position' => $widget->position,
                'avatar_media_id' => $widget->avatar_media_id,
                'avatar_url' => $widget->avatar?->url(),
                'require_auth' => $widget->require_auth,
                'show_typing' => $widget->show_typing,
                'offline_message' => $widget->offline_message,
            ],
        ]);
    }

    public function update(UpdateWidgetRequest $request)
    {
        ChatBotWidget::current()->update($request->validated());

        return back()->with('success', 'Configuración del widget actualizada.');
    }
}
