{{-- The score behind a phone lead's hot / warm / cold, with every point accounted for. --}}
<div class="mt-6 rounded-lg border border-ink-100 p-4">
    <div class="flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-ink-900">Lead qualification</h3>
        <span class="font-sans text-2xl font-semibold text-ink-900 tabular-nums">{{ $qualification['score'] }}<span class="text-sm font-normal text-ink-400">/100</span></span>
    </div>
    <div class="mt-2 h-2 rounded-full bg-ink-50" aria-hidden="true">
        <div @class([
            'h-2 rounded-full',
            'bg-red-500'   => $qualification['grade'] === 'hot',
            'bg-brass-500' => $qualification['grade'] === 'warm',
            'bg-ink-300'   => $qualification['grade'] === 'cold',
        ]) style="width: {{ max(2, $qualification['score']) }}%"></div>
    </div>
    <ul class="mt-3 space-y-1 text-sm">
        @foreach ($qualification['reasons'] as [$reason, $points])
            <li class="flex justify-between gap-3">
                <span class="text-ink-700">{{ ucfirst($reason) }}</span>
                <span @class(['shrink-0 tabular-nums', 'text-emerald-700' => $points > 0, 'text-red-700' => $points < 0, 'text-ink-400' => $points === 0])>{{ $points > 0 ? '+'.$points : $points }}</span>
            </li>
        @endforeach
    </ul>
    <p class="mt-2 text-xs text-ink-400">Hot from {{ \App\Services\Leads\LeadQualifier::HOT_FROM }}, warm from {{ \App\Services\Leads\LeadQualifier::WARM_FROM }}. Scored from the phone call.</p>
</div>
