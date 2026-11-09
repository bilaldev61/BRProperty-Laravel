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
    private const SHOWN = 4;

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
     * @return list<array{key: string, label: string, count: int, url: string, items: list<array{title: string, meta: string, url: string, at: ?string}>}>
     */
    public function groups(): array
    {
        $groups = [];

        if ($this->wants('enquiries') && $this->user->can('enquiries.view')) {
            $groups[] = $this->group(
                'enquiries',
                'Enquiries to answer',
                Enquiry::query()->needsReply()->with('property')->latest(),
                route('admin.enquiries.index'),
                fn(Enquiry $enquiry) => [
                    'title' => $enquiry->name,
                    'meta'  => $enquiry->property?->title ?? $enquiry->typeLabel(),
                    'url'   => route('admin.enquiries.show', $enquiry),
                    'at'    => $enquiry->created_at?->diffForHumans(short: true),
                ],
            );
        }

        if ($this->wants('questions') && $this->user->can('assistant.view')) {
            $groups[] = $this->group(
                'questions',
                'Assistant could not answer',
                ChatUnansweredQuestion::query()->where('created_at', '>=', now()->subWeek())->latest(),
                route('admin.assistant.questions'),
                fn(ChatUnansweredQuestion $question) => [
                    'title' => Str::limit($question->question, 70),
                    'meta'  => $question->topicLabel(),
                    'url'   => route('admin.assistant.questions'),
                    'at'    => $question->created_at?->diffForHumans(short: true),
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
     * @param  callable(TModel): array{title: string, meta: string, url: string, at: ?string}  $line
     * @return array{key: string, label: string, count: int, url: string, items: list<array{title: string, meta: string, url: string, at: ?string}>}
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
