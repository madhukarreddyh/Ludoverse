<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;

/**
 * Instant first-response bot for support tickets. On ticket creation the
 * subject + message are scanned for keywords (config/chatbot.php); the
 * first match posts an automated reply marked is_bot. The ticket stays
 * OPEN — a human still triages it.
 */
class ChatbotService
{
    /**
     * Post an automated reply if a keyword matches. Returns the reply or null.
     */
    public function autoReply(SupportTicket $ticket): ?SupportTicketReply
    {
        $haystack = strtolower($ticket->subject.' '.$ticket->message);

        foreach ((array) config('chatbot.keywords', []) as $keyword => $text) {
            if (str_contains($haystack, strtolower((string) $keyword))) {
                return $ticket->replies()->create([
                    'user_id' => null,
                    'body' => '[Automated reply] '.$text,
                    'is_bot' => true,
                ]);
            }
        }

        return null;
    }
}
