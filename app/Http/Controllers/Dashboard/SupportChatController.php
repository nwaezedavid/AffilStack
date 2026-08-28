<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AiChatMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Modules\SupportChatService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportChatController extends Controller
{
    public function show(): View
    {
        $messages = AiChatMessage::where('user_id', auth()->id())->latest()->limit(30)->get()->reverse();

        return view('dashboard.support.chat', compact('messages'));
    }

    public function message(Request $request, SupportChatService $chat): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $result = $chat->reply($request->user(), $validated['message']);

        return response()->json($result);
    }

    public function escalate(Request $request, SupportChatService $chat): RedirectResponse
    {
        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            'subject' => 'Escalated from AI chat',
            'category' => 'other',
            'priority' => 'normal',
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'is_staff' => false,
            'message' => "Transcript from the AI chat assistant:\n\n".$chat->recentTranscript($request->user()),
        ]);

        Notification::make()
            ->title('Escalated from AI chat: '.$ticket->user->name)
            ->body('The AI assistant could not resolve this — full transcript attached.')
            ->actions([
                Action::make('view')
                    ->url(route('filament.admin.resources.support-tickets.view', $ticket))
                    ->markAsRead(),
            ])
            ->sendToDatabase(User::role(['admin', 'support'])->get());

        return redirect()->route('support.show', $ticket)->with('success', "We've opened a ticket with your conversation attached — a human will follow up.");
    }
}
