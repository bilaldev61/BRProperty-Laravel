<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatUnansweredQuestion;
use App\Models\ChatUsage;
use Illuminate\Http\Request;

/**
 * What the public assistant has been doing: every chat, which ones became
 * leads, what visitors asked that the listings could not answer, and what the
 * model was sent to answer the rest.
 */
class AssistantController extends Controller
{
    /** The report looks back this far - long enough to see a pattern. */
    private const REPORT_DAYS = 90;

    public function index(Request $request)
    {
        $since = now()->subDays(30);

        $conversations = ChatConversation::with(['property', 'enquiry'])
            ->withCount('unansweredQuestions')
            ->where('visitor_messages', '>', 0)
            ->when($request->boolean('leads'), fn($q) => $q->whereNotNull('enquiry_id'))
            ->latest('last_message_at')
            ->paginate(25)
            ->withQueryString();

        $replies = ChatMessage::where('role', ChatMessage::ASSISTANT)
            ->where('created_at', '>=', $since)
            ->whereNotNull('answered_by')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when answered_by like 'rule:%' then 1 else 0 end) as by_rules")
            ->selectRaw("sum(case when answered_by = 'ai' then 1 else 0 end) as by_ai")
            ->selectRaw('coalesce(sum(tokens), 0) as tokens')
            ->first();

        $chats = ChatConversation::where('visitor_messages', '>', 0)->where('created_at', '>=', $since)->count();
        $leads = ChatConversation::whereNotNull('enquiry_id')->where('created_at', '>=', $since)->count();

        return view('admin.assistant.index', [
            'conversations' => $conversations,
            'stats' => [
                'chats'      => $chats,
                'leads'      => $leads,
                'conversion' => $chats ? round($leads / $chats * 100) : 0,
                'questions'  => ChatUnansweredQuestion::where('created_at', '>=', $since)->count(),
                'without_ai' => $replies->total ? round($replies->by_rules / $replies->total * 100) : 0,
                'ai_replies' => (int) $replies->by_ai,
                'tokens'     => (int) $replies->tokens,
                'per_reply'  => $replies->by_ai ? (int) round($replies->tokens / $replies->by_ai) : 0,
            ],
        ]);
    }

    public function show(ChatConversation $conversation)
    {
        $conversation->load(['messages', 'property', 'enquiry', 'unansweredQuestions', 'usage']);

        return view('admin.assistant.show', ['conversation' => $conversation]);
    }

    /**
     * The split behind a reply's token total: one row per request the model was
     * sent, so a tool round is visible as the extra request it is, and a cache
     * hit is visible at all. `chat_messages.tokens` holds only the total.
     */
    public function usage()
    {
        $since = now()->subDays(30);
        $window = fn() => ChatUsage::where('created_at', '>=', $since);

        $totals = $window()
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(prompt_tokens), 0) as prompt')
            ->selectRaw('coalesce(sum(completion_tokens), 0) as completion')
            ->selectRaw('coalesce(sum(cached_tokens), 0) as cached')
            ->selectRaw('coalesce(sum(cache_write_tokens), 0) as written')
            ->selectRaw('coalesce(avg(latency_ms), 0) as latency')
            ->first();

        $byModel = $window()
            ->select('model')
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(prompt_tokens), 0) as prompt')
            ->selectRaw('coalesce(sum(cached_tokens), 0) as cached')
            ->selectRaw('coalesce(avg(latency_ms), 0) as latency')
            ->groupBy('model')
            ->orderByDesc('requests')
            ->get();

        $requests = $window()
            ->with(['conversation.enquiry', 'message'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.assistant.usage', [
            'requests' => $requests,
            'byModel'  => $byModel,
            'stats'    => [
                'requests'    => (int) $totals->requests,
                'prompt'      => (int) $totals->prompt,
                'completion'  => (int) $totals->completion,
                'cached'      => (int) $totals->cached,
                'written'     => (int) $totals->written,
                'cache_rate'  => $totals->prompt ? (int) round($totals->cached / $totals->prompt * 100) : 0,
                'latency'     => (int) round($totals->latency),
                // A separate count: `round` reads as a function inside raw SQL.
                'tool_rounds' => $window()->where('round', '>', 0)->count(),
            ],
        ]);
    }

    /**
     * What visitors keep asking that the listings do not say, grouped by topic
     * and by listing - the to-do list for making the listings answer it.
     */
    public function questions()
    {
        $recent = ChatUnansweredQuestion::with('property')
            ->where('created_at', '>=', now()->subDays(self::REPORT_DAYS))
            ->latest()
            ->get();

        $byTopic = $recent->groupBy('topic')
            ->map(fn($group, $topic) => [
                'topic'    => $topic,
                'label'    => $group->first()->topicLabel(),
                'count'    => $group->count(),
                'examples' => $group->take(5),
            ])
            ->sortByDesc('count')
            ->values();

        $byProperty = $recent->whereNotNull('property_id')
            ->groupBy('property_id')
            ->map(fn($group) => [
                'property' => $group->first()->property,
                'count'    => $group->count(),
                'topics'   => $group->map->topicLabel()->countBy()->sortDesc(),
            ])
            ->filter(fn($row) => $row['property'] !== null)
            ->sortByDesc('count')
            ->take(10)
            ->values();

        return view('admin.assistant.questions', [
            'byTopic'    => $byTopic,
            'byProperty' => $byProperty,
            'total'      => $recent->count(),
            'days'       => self::REPORT_DAYS,
        ]);
    }
}
