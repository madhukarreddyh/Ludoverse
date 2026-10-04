<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\ChatbotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Player-facing support tickets. On creation the chatbot scans for
 * keywords and may post an instant automated reply (the ticket stays
 * open for human triage).
 */
class SupportTicketController extends Controller
{
    public function __construct(protected ChatbotService $bot) {}

    public function index(Request $request): View
    {
        return view('support.index', [
            'tickets' => SupportTicket::where('user_id', $request->user()->id)
                ->orderByDesc('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('support.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
        ]);

        $auto = $this->bot->autoReply($ticket);

        return redirect()->route('support.show', $ticket)->with(
            'status',
            $auto
                ? 'Ticket opened. Our automated assistant posted an instant answer below — a human will follow up.'
                : 'Ticket opened. Our team will reply here.'
        );
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);

        return view('support.show', [
            'ticket' => $ticket->load('replies'),
        ]);
    }
}
