<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin side of support tickets: triage, reply, close.
 */
class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupportTicket::with('user')->orderByDesc('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('admin.support.index', [
            'tickets' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'openCount' => SupportTicket::where('status', 'open')->count(),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support.show', [
            'ticket' => $ticket->load(['user', 'replies']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_bot' => false,
        ]);
        $ticket->forceFill(['status' => 'answered', 'admin_reply' => $data['body']])->save();

        return back()->with('status', 'Reply sent.');
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $ticket->forceFill(['status' => 'closed'])->save();

        return back()->with('status', 'Ticket closed.');
    }

    public function reopen(SupportTicket $ticket): RedirectResponse
    {
        $ticket->forceFill(['status' => 'open'])->save();

        return back()->with('status', 'Ticket re-opened.');
    }
}
