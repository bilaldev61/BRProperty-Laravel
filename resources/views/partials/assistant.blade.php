{{--
    The chat bubble. Shown whenever chat is switched on. With an API key it
    answers with the model; without one it runs rules-only - replies straight
    from the listing data, and hands anything else to the agent. The label below
    reflects which mode it is in. On a listing page it is scoped to that listing.
--}}
@php $assistant = app(\App\Services\Assistant\PropertyAssistant::class); @endphp
@if ($assistant->isAvailable())
<x-island
    name="PropertyAssistant"
    :props="[
            'endpoint'      => route('assistant.show'),
            'property'      => $property?->slug,
            'propertyTitle' => $property?->title,
            'agentName'     => config('agent.name'),
            'agentTitle'    => config('agent.title'),
            'agentPhoto'    => \App\Support\Media::agent('avatar'),
            'phone'         => config('agent.phone'),
            'phoneDial'     => config('agent.phone_dial'),
            'whatsapp'      => config('agent.whatsapp'),
            'privacyUrl'    => route('privacy'),
            'aiPowered'     => $assistant->modelAvailable(),
        ]" />
@endif