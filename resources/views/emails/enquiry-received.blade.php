{{--
    The first email to the person who made an enquiry: a fact-checked AI
    answer when there is one ($paragraphs), otherwise the template wording.
    The card, contacts and hours are always from the records. Never their own
    free text (see App\Mail\EnquiryReceived).
    Markdown mail: keep lines flush left - indentation becomes a code block.
--}}
<x-mail::message>
    @if ($paragraphs)
    {{-- A checked AI answer to their question. Escaped: it is model output. --}}
    @foreach ($paragraphs as $paragraph)
    {!! nl2br(e($paragraph)) !!}

    @endforeach
    @else
    # Thanks, {{ $enquiry->name }}

    @if ($enquiry->type === 'appraisal')
    Your request for a free property appraisal has reached us. A valuation needs a proper look at the property, so we will be in touch to arrange a time that suits you.
    @elseif ($property)
    Your enquiry about **{{ $property->title }}** has reached us, and we will get back to you shortly.
    @else
    Your message has reached us, and we will get back to you shortly.
    @endif
    @endif

    @if ($property)
    <x-mail::panel>
        **{{ $property->title }}**<br>
        {{ $property->fullAddress() }}

        **{{ $property->priceDisplay() }}** · {{ $property->statusLabel() }}<br>
        {{ collect([
    $property->hasRooms() ? $property->bedrooms.' bed · '.$property->bathrooms.' bath' : null,
    $property->landDisplay(),
    $property->floorDisplay() ? $property->floorDisplay().' covered' : null,
])->filter()->implode(' · ') }}
        @if (filled($property->inspection_times) && $property->status !== 'sold')

        Inspection times: {{ $property->inspection_times }}
        @endif
    </x-mail::panel>

    <x-mail::button :url="route('properties.show', $property)">
        View the listing
    </x-mail::button>
    @endif

    @if ($enquiry->type === 'appraisal' && filled($enquiry->details))
    **The details you gave us**

    <x-mail::table>
        | | |
        |:--|:--|
        @foreach ($enquiry->details as $key => $value)
        | {{ ucfirst(str_replace('_', ' ', $key)) }} | {{ $value }} |
        @endforeach
    </x-mail::table>
    @endif

    @if ($paragraphs)
    **Reach us directly:** call or WhatsApp **{{ $agent['phone'] }}**
    @else
    ## What happens next

    @if (filled($enquiry->phone))
    We will call you on **{{ $enquiry->phone }}**, usually within one business day.
    @else
    We will reply to this email address, usually within one business day.
    @endif
    If it is urgent, call or WhatsApp us on **{{ $agent['phone'] }}**.
    @endif

    <x-mail::button :url="$whatsapp" color="success">
        Message us on WhatsApp
    </x-mail::button>

    **Office hours**<br>
    @foreach ($agent['hours'] as $days => $time)
    {{ $days }}: {{ $time }}<br>
    @endforeach

    @unless ($paragraphs)
    Regards,<br>
    **{{ $agent['name'] }}**<br>
    {{ $agent['title'] }}, {{ $agent['agency'] }}
    @endunless

    <x-slot:subcopy>
        @if ($paragraphs)
        This reply was prepared automatically from our listing records so you would hear back straight away. {{ $agent['name'] }} will follow up personally.
        @endif
        You are receiving this because an enquiry was made with this email address on {{ parse_url(config('app.url'), PHP_URL_HOST) }}. If that was not you, you can ignore this email - nothing else will be sent. Just reply to reach {{ $agent['name'] }} directly.
    </x-slot:subcopy>
</x-mail::message>