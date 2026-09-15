<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    /**
     * Polled by the bell dropdown in layouts.app every ~20s. Keeps things
     * simple (no websockets/broadcasting infra to run on a Hostinger VPS)
     * while still surfacing a finished background task without a full
     * page reload.
     */
    public function poll(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(15)->get();

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'items' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'read_at' => $n->read_at,
                'success' => (bool) ($n->data['success'] ?? true),
                'message' => $this->messageFor($n->data),
                'url' => $n->data['url'] ?? '#',
                'created_at' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function messageFor(array $data): string
    {
        // Non-generation notifications (the AI agents' own) key off 'type'
        // rather than 'module' — handled first so the fallback below never
        // has to guess at a module label for them.
        if (isset($data['type'])) {
            return match ($data['type']) {
                'maintenance_scheduled' => 'Scheduled maintenance: '.Carbon::parse($data['starts_at'])->format('M j, g:ia'),
                'maintenance_completed' => 'Scheduled maintenance is complete',
                'welcome' => 'Welcome to AffilStack — say hi to Sam anytime from support chat',
                'ticket_replied' => 'New reply on your ticket: '.($data['subject'] ?? ''),
                'ticket_status_changed' => 'Your ticket was '.(($data['status'] ?? 'resolved') === 'closed' ? 'closed' : 'marked resolved').': '.($data['subject'] ?? ''),
                default => $data['reason'] ?? 'Update from AffilStack',
            };
        }

        $title = $data['title'] ?? 'Your generation';
        $moduleLabel = match ($data['module'] ?? null) {
            'research' => 'offer research',
            'blog_article' => 'blog article',
            'linkedin_keywords' => 'LinkedIn keyword research',
            'linkedin_dm_sequence' => 'LinkedIn DM sequence',
            'linkedin_post' => 'LinkedIn post',
            'linkedin_article' => 'LinkedIn article',
            'youtube_script' => 'YouTube video script',
            'youtube_metadata' => 'YouTube video metadata',
            'ugc_angles' => 'UGC angle ideas',
            'ugc_content' => 'UGC script & platform pack',
            'x_thread' => 'X thread',
            'tiktok_video' => 'TikTok video package',
            'pinterest_pin' => 'Pinterest pin pack',
            'email_nurture' => 'email nurture sequence',
            'competitor_angles' => 'competitor angle scan',
            default => 'generation',
        };

        return ($data['success'] ?? true)
            ? "\"{$title}\" — {$moduleLabel} is ready"
            : "\"{$title}\" — {$moduleLabel} failed";
    }
}
