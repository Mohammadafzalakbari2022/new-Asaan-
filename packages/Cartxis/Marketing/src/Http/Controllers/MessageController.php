<?php

namespace Cartxis\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use Cartxis\Marketing\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The free inbox behind the website contact form.
 *
 * Messages written on the site (or in the app) land here. Replying sends a
 * normal email through the store's mailer — there is no WhatsApp in this path,
 * because receiving WhatsApp messages needs a paid Meta business account.
 */
class MessageController extends Controller
{
    /**
     * Display the messages inbox.
     */
    public function index(Request $request): Response
    {
        $query = ContactMessage::query();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $messages = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return Inertia::render('Admin/Marketing/Messages/Index', [
            'messages' => $messages,
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'total' => ContactMessage::count(),
                'new' => ContactMessage::where('status', ContactMessage::STATUS_NEW)->count(),
                'read' => ContactMessage::where('status', ContactMessage::STATUS_READ)->count(),
                'replied' => ContactMessage::where('status', ContactMessage::STATUS_REPLIED)->count(),
            ],
        ]);
    }

    /**
     * Return one message (and mark it read), for the detail panel.
     */
    public function show(ContactMessage $message): JsonResponse
    {
        $message->markAsRead();

        return response()->json(['success' => true, 'data' => $message->fresh()]);
    }

    /**
     * Mark a message as read.
     */
    public function markRead(ContactMessage $message): JsonResponse
    {
        $message->markAsRead();

        return response()->json(['success' => true, 'message' => 'Message marked as read.']);
    }

    /**
     * Send an email reply and mark the message as replied.
     */
    public function reply(Request $request, ContactMessage $message): JsonResponse
    {
        $validated = $request->validate([
            'reply' => 'required|string|min:2|max:5000',
        ]);

        if (empty($message->email)) {
            return response()->json([
                'success' => false,
                'message' => 'This message has no email address to reply to. Use the phone number instead.',
            ], 422);
        }

        try {
            Mail::raw($validated['reply'], function ($mail) use ($message) {
                $mail->to($message->email)
                    ->subject('Re: '.($message->subject ?: 'Your message to us'));
            });
        } catch (\Throwable $e) {
            Log::error('Contact message reply failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'The reply could not be sent. Please check the mail settings and try again.',
            ], 500);
        }

        $message->update([
            'status' => ContactMessage::STATUS_REPLIED,
            'replied_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Reply sent.']);
    }

    /**
     * Delete a message.
     */
    public function destroy(ContactMessage $message): JsonResponse
    {
        $message->delete();

        return response()->json(['success' => true, 'message' => 'Message deleted.']);
    }
}
