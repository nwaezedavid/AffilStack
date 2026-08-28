<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(): View
    {
        $tickets = auth()->user()->supportTickets()->latest('last_reply_at')->paginate(15);

        return view('dashboard.support.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('dashboard.support.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|in:billing,technical,feature_request,other',
            'priority' => 'required|in:low,normal,high',
            'message' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'is_staff' => false,
            'message' => $validated['message'],
        ]);

        $this->notifyStaff($ticket, 'New ticket: '.$ticket->subject);

        return redirect()->route('support.show', $ticket)->with('success', 'Ticket opened — we usually reply within a few hours.');
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorizeOwner($ticket);

        $ticket->load('messages.user');

        return view('dashboard.support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeOwner($ticket);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'is_staff' => false,
            'message' => $validated['message'],
        ]);

        // A customer reply on a ticket the team considered done reopens it.
        $ticket->update([
            'status' => in_array($ticket->status, ['resolved', 'closed'], true) ? 'open' : $ticket->status,
            'last_reply_at' => now(),
        ]);

        $this->notifyStaff($ticket, 'New reply on: '.$ticket->subject);

        return redirect()->route('support.show', $ticket)->with('success', 'Reply sent.');
    }

    public function rate(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeOwner($ticket);

        abort_unless(in_array($ticket->status, ['resolved', 'closed'], true), 422);

        $validated = $request->validate([
            'csat_rating' => 'required|integer|min:1|max:5',
        ]);

        $ticket->update(['csat_rating' => $validated['csat_rating']]);

        return redirect()->route('support.show', $ticket)->with('success', 'Thanks for the feedback.');
    }

    protected function authorizeOwner(SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === auth()->id(), 403);
    }

    protected function notifyStaff(SupportTicket $ticket, string $title): void
    {
        $staff = User::role(['admin', 'support'])->get();

        Notification::make()
            ->title($title)
            ->body($ticket->user->name.' · '.ucfirst($ticket->category).' · '.ucfirst($ticket->priority).' priority')
            ->actions([
                Action::make('view')
                    ->url(route('filament.admin.resources.support-tickets.view', $ticket))
                    ->markAsRead(),
            ])
            ->sendToDatabase($staff);
    }
}
