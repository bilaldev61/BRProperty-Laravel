<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a visitor asked that the listing records could not answer.
 *
 * Read in aggregate, these tell the agent which facts to add to their listings:
 * if every buyer asks about possession, possession belongs on the page.
 */
class ChatUnansweredQuestion extends Model
{
    /** value => label. The model must pick one of these keys. */
    public const TOPICS = [
        'price_negotiation' => 'Price & negotiation',
        'possession'        => 'Closing & possession',
        'paperwork'         => 'Title, NOC & paperwork',
        'charges'           => 'Service charges & fees',
        'viewing'           => 'Showings & availability',
        'location'          => 'Location & neighbourhood',
        'construction'      => 'Construction & condition',
        'financing'         => 'Financing & mortgage',
        'other'             => 'Other',
    ];

    protected $guarded = [];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? ucfirst(str_replace('_', ' ', $this->topic));
    }
}
