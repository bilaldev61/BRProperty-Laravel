{{--
    Server-owned enquiry form.

    Laravel prints every field into the first HTML, exactly like a plain
    Bootstrap form does. Nothing ever re-renders these inputs: JavaScript
    (resources/js/enquiry-form.js) only listens to the submit event, checks the
    values and posts them in the background. With JavaScript off the browser
    submits the same form normally and EnquiryController answers with a
    redirect plus the session('status') banner on the page.

    Usage:
        <x-enquiry-form type="contact" heading="Send a message" submit-label="Send message" />
        <x-enquiry-form type="appraisal" :types="config('agent.property_types')" compact />
--}}
@props([
'type' => 'contact', // contact | property | appraisal
'endpoint' => null,
'propertyId' => null,
'propertyTitle' => null,
'heading' => '',
'subheading' => '',
'submitLabel' => 'Send enquiry',
'compact' => false,
'types' => null,
'tone' => 'glass', // solid | glass (form sits on a photo)
'message' => null,
])

@php
$endpoint = $endpoint ?? route('enquiries.store');
$isAppraisal = $type === 'appraisal';
$onGlass = $tone === 'glass';
$types = $types ?? config('agent.property_types');

$labelClass = $onGlass ? 'label-on-dark' : 'label';
$hintClass = $onGlass ? 'font-normal text-sand-300' : 'font-normal text-ink-400';
$errorClass = $onGlass ? 'mt-1 text-sm text-red-200' : 'mt-1 text-sm text-red-600';
$mutedClass = $onGlass ? 'text-sand-300' : 'text-ink-400';

$message = $message ?? ($type === 'property' && $propertyTitle
? "Hi, I'd like more information about {$propertyTitle}."
: '');

$successTitle = $isAppraisal ? 'Appraisal requested' : 'Message sent';
@endphp

<div data-enquiry>
    @if ($heading || $subheading)
    <div class="mb-6">
        @if ($heading)
        <h2 class="{{ $onGlass ? 'text-2xl font-semibold text-white sm:text-3xl' : 'text-2xl' }}">{{ $heading }}</h2>
        @endif
        @if ($subheading)
        <p class="mt-2 {{ $onGlass ? 'text-sand-200' : 'text-ink-500' }}">{{ $subheading }}</p>
        @endif
    </div>
    @endif

    <form method="POST" action="{{ $endpoint }}" class="space-y-4" data-enquiry-form>
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        @if ($propertyId)
        <input type="hidden" name="property_id" value="{{ $propertyId }}">
        @endif

        <p
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
            data-form-error
            hidden></p>

        <div class="{{ $compact ? 'space-y-4' : 'grid gap-4 sm:grid-cols-2' }}">
            <div>
                <label class="{{ $labelClass }}" for="{{ $type }}-name">Your name</label>
                <input
                    id="{{ $type }}-name"
                    name="name"
                    type="text"
                    autocomplete="name"
                    class="input"
                    placeholder="Jordan Smith"
                    maxlength="120"
                    required>
                <p class="{{ $errorClass }}" data-error="name" hidden></p>
            </div>

            <div>
                <label class="{{ $labelClass }}" for="{{ $type }}-email">Email</label>
                <input
                    id="{{ $type }}-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    class="input"
                    placeholder="jordan@example.com"
                    maxlength="180"
                    required>
                <p class="{{ $errorClass }}" data-error="email" hidden></p>
            </div>
        </div>

        <div>
            <label class="{{ $labelClass }}" for="{{ $type }}-phone">Phone <span class="{{ $hintClass }}">(optional)</span></label>
            <input
                id="{{ $type }}-phone"
                name="phone"
                type="tel"
                autocomplete="tel"
                class="input"
                placeholder="+971 50 123 4567"
                pattern="[\d\s()+.\-]{7,20}"
                maxlength="40">
            <p class="{{ $errorClass }}" data-error="phone" hidden></p>
        </div>

        @if ($isAppraisal)
        <div>
            <label class="{{ $labelClass }}" for="appraisal-address">Property address</label>
            <input
                id="appraisal-address"
                name="address"
                type="text"
                autocomplete="street-address"
                class="input"
                placeholder="Villa 14, Dubai Hills Estate"
                maxlength="200">
            <p class="{{ $errorClass }}" data-error="address" hidden></p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}" for="appraisal-suburb">Area</label>
                <input id="appraisal-suburb" name="suburb" type="text" class="input" placeholder="Dubai Hills" maxlength="120">
            </div>
            <div>
                <label class="{{ $labelClass }}" for="appraisal-type">Property type</label>
                <select id="appraisal-type" name="property_type" class="input">
                    @foreach ($types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}" for="appraisal-beds">Bedrooms</label>
                <input id="appraisal-beds" name="bedrooms" type="number" min="0" max="20" class="input" placeholder="4">
                <p class="{{ $errorClass }}" data-error="bedrooms" hidden></p>
            </div>
            <div>
                <label class="{{ $labelClass }}" for="appraisal-timeframe">When are you looking to sell?</label>
                <select id="appraisal-timeframe" name="timeframe" class="input">
                    <option value="">Please choose</option>
                    <option>ASAP</option>
                    <option>1-3 months</option>
                    <option>3-6 months</option>
                    <option>6-12 months</option>
                    <option>Just researching</option>
                </select>
            </div>
        </div>
        @endif

        <div>
            <label class="{{ $labelClass }}" for="{{ $type }}-message">
                Message @if ($isAppraisal)<span class="{{ $hintClass }}">(optional)</span>@endif
            </label>
            <textarea
                id="{{ $type }}-message"
                name="message"
                rows="4"
                class="input"
                placeholder="Tell us a little about what you need..."
                maxlength="4000">{{ $message }}</textarea>
            <p class="{{ $errorClass }}" data-error="message" hidden></p>
        </div>

        {{-- Honeypot: hidden from people, catnip for bots --}}
        <div class="absolute h-0 w-0 overflow-hidden" aria-hidden="true">
            <label>Company<input name="company" type="text" tabindex="-1" autocomplete="off"></label>
        </div>

        <button type="submit" class="btn-accent w-full" data-submit>
            <svg data-submit-spinner class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
            </svg>
            <span data-submit-text>{{ $submitLabel }}</span>
        </button>

        <p class="text-center text-xs {{ $mutedClass }}">
            Your details stay private and are never shared with third parties.
        </p>
    </form>

    {{-- Confirmation: revealed by JavaScript on success, already in the first HTML --}}
    <div
        class="rounded-card border border-emerald-200 bg-emerald-50 p-6 text-center"
        role="status"
        aria-live="polite"
        data-success
        data-success-title="{{ $successTitle }}"
        hidden>
        <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-emerald-600">
            <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
            </svg>
        </div>
        <p class="font-semibold text-emerald-900">{{ $successTitle }}</p>
        <p class="mt-1 text-sm text-emerald-800" data-success-text></p>
        <button type="button" class="mt-4 text-sm font-semibold text-emerald-900 underline" data-success-reset>
            Send another
        </button>
    </div>
</div>