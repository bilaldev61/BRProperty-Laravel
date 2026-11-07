<x-layouts.admin title="Reviews" heading="Reviews"
    intro="What clients said, newest first. The newest featured review leads the block on the home page.">
    <x-slot:actions>
        <a href="{{ route('admin.testimonials.create') }}" class="btn-primary !px-4 !py-2 text-xs">+ New review</a>
    </x-slot:actions>

    @if ($testimonials->isEmpty())
    <x-empty-state title="No reviews yet" message="Add reviews from past clients to build trust on the site."
        :action-url="route('admin.testimonials.create')" action-label="Add a review" />
    @else
    <x-admin.card :padded="false" tone="brass">
        <div class="divide-y divide-ink-50">
            @foreach ($testimonials as $testimonial)
            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-ink-900">{{ $testimonial->author }}</span>
                        <span class="text-sm text-brass-500" aria-label="{{ $testimonial->rating }} out of 5">
                            {{ str_repeat('★', $testimonial->rating) }}<span class="text-ink-200">{{ str_repeat('★', 5 - $testimonial->rating) }}</span>
                        </span>
                        @if ($testimonial->is_featured)
                        <x-admin.badge tone="brass">Featured</x-admin.badge>
                        @endif
                        @unless ($testimonial->is_published)
                        <x-admin.badge>Draft</x-admin.badge>
                        @endunless
                    </p>
                    <p class="mt-1 line-clamp-2 text-sm text-ink-500">{{ $testimonial->body }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-3 text-sm">
                    <a href="{{ route('admin.testimonials.edit', $testimonial) }}" class="font-medium text-brass-700 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('admin.testimonials.destroy', $testimonial) }}"
                        data-confirm="This review will be permanently removed."
                        data-confirm-title="Delete this review?">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </x-admin.card>

    <div>{{ $testimonials->links() }}</div>
    @endif
</x-layouts.admin>