<?php

namespace App\Support;

use App\Models\ChatUnansweredQuestion;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What the bell in the admin header points at: work the site took in and nobody
 * has answered yet. Each group is behind the same ability as its own screen, so
 * the bell can never announce something the reader is refused.
 */
final class AdminNotifications
{
    /**
     * Rows per group in the bell. Three keeps the panel to one screen when all
     * four groups have work in it; the count beside the label and the "See all"
     * link carry the rest.
     */
    private const SHOWN = 3;

    /** The icon and tone each kind of enquiry carries in the bell. */
    private const ENQUIRY_LOOKS = [
        'property'  => ['icon' => 'house', 'tone' => 'teal'],
        'appraisal' => ['icon' => 'appraisal', 'tone' => 'brass'],
        'contact'   => ['icon' => 'envelope', 'tone' => 'indigo'],
    ];

    /**
     * @param  list<string>  $except  groups to leave out - the screen being read
     *                                already shows them, and a bell that repeats
     *                                the page you are on is noise.
     */
    public function __construct(
        private readonly User $user,
        private readonly array $except = [],
    ) {}

    /**
     * @return list<array{key: string, label: string, count: int, url: string, items: list<array{title: string, meta: string, url: string, at: ?string, icon: string, tone: string}>}>
     */
    public function groups(): array
    {
        $groups = [];

        if ($this->wants('enquiries') && $this->user->can('enquiries.view')) {
            // Chat leads come first and in red: the bubble ran out of answers,
            // the visitor left a name and email, and there is a person to reply
            // to. They are kept out of the enquiries band below, so one visitor
            // is never counted twice.
            $leads = $this->group(
                'leads',
                'Chat leads to answer',
                Enquiry::query()->needsReply()->where('source', Enquiry::SOURCE_CHAT)->latest(),
                route('admin.enquiries.index'),
                fn(Enquiry $enquiry) => [
                    'title' => $enquiry->name,
                    'meta'  => $enquiry->email ?: ($enquiry->phone ?: $enquiry->typeLabel()),
                    'url'   => route('admin.enquiries.show', $enquiry),
                    'at'    => $enquiry->created_at?->diffForHumans(short: true),
                    'icon'  => 'alert',
                    'tone'  => 'red',
                ],
            );

            if ($leads['count'] > 0) {
                $groups[] = $leads;
            }

            $groups[] = $this->group(
                'enquiries',
                'Enquiries to answer',
                Enquiry::query()->needsReply()
                    ->where(fn(Builder $query) => $query->whereNull('source')->orWhere('source', '!=', Enquiry::SOURCE_CHAT))
                    ->with('property')->latest(),
                route('admin.enquiries.index'),
                fn(Enquiry $enquiry) => [
                    'title' => $enquiry->name,
                    'meta'  => $enquiry->property?->title ?? $enquiry->typeLabel(),
                    'url'   => route('admin.enquiries.show', $enquiry),
                    'at'    => $enquiry->created_at?->diffForHumans(short: true),
                    ...(self::ENQUIRY_LOOKS[$enquiry->type] ?? self::ENQUIRY_LOOKS['contact']),
                ],
            );
        }

        if ($this->wants('questions') && $this->user->can('assistant.view')) {
            $groups[] = $this->group(
                'questions',
                'Assistant could not answer',
                // A question whose chat became a lead is answered by that lead,
                // and appearing here too would be the same failure twice.
                ChatUnansweredQuestion::query()
                    ->where('created_at', '>=', now()->subWeek())
                    ->whereDoesntHave('conversation', fn(Builder $query) => $query->whereNotNull('enquiry_id'))
                    ->latest(),
                route('admin.assistant.questions'),
                fn(ChatUnansweredQuestion $question) => [
                    'title' => Str::limit($question->question, 70),
                    'meta'  => $question->topicLabel(),
                    'url'   => route('admin.assistant.questions'),
                    'at'    => $question->created_at?->diffForHumans(short: true),
                    'icon'  => 'question',
                    'tone'  => 'clay',
                ],
            );
        }

        return $groups;
    }

    public function total(): int
    {
        return (int) array_sum(array_column($this->groups(), 'count'));
    }

    private function wants(string $group): bool
    {
        return ! in_array($group, $this->except, true);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array{title: string, meta: string, url: string, at: ?string, icon: string, tone: string}  $line
     * @return array{key: string, label: string, count: int, url: string, items: list<array{title: string, meta: string, url: string, at: ?string, icon: string, tone: string}>}
     */
    private function group(string $key, string $label, Builder $query, string $url, callable $line): array
    {
        $count = (clone $query)->count();

        return [
            'key'   => $key,
            'label' => $label,
            'count' => $count,
            'url'   => $url,
            'items' => $count === 0 ? [] : $query->limit(self::SHOWN)->get()->map($line)->all(),
        ];
    }
}
