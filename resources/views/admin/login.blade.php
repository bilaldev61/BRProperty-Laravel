<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin login | {{ config('agent.agency') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen bg-sand-100 lg:grid lg:grid-cols-[1fr_1.1fr]">

    {{-- Brand panel. Says what is behind the door before asking for a password. --}}
    <aside class="relative isolate overflow-hidden bg-ink-950 px-6 py-10 text-sand-300 sm:px-10 lg:flex lg:flex-col lg:justify-between lg:py-14">
        <div class="pointer-events-none absolute -top-24 -right-16 -z-10 h-72 w-72 rounded-full bg-brass-500/10 blur-3xl" aria-hidden="true"></div>

        <div>
            <img src="{{ \App\Support\Media::agent('logo_light') }}" alt="{{ config('agent.agency') }}" width="260" height="62"
                class="h-14 w-auto">

            <p class="mt-6 max-w-sm font-display text-2xl leading-snug text-white">
                The agency's working copy of the website.
            </p>

            <p class="mt-3 max-w-sm text-sm leading-relaxed">
                Listings and photos, the enquiry inbox, appointments, calls and the assistant's conversations - all in
                one place, and only for people with an account.
            </p>
        </div>

        <ul class="mt-10 hidden gap-3 text-sm lg:grid">
            @foreach (['Publish and unpublish listings', 'Answer enquiries and log calls', 'See what the assistant was asked'] as $line)
            <li class="flex items-start gap-2.5">
                <span class="mt-0.5 inline-flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full bg-white/10 text-brass-300">
                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.5 6.5 2.5 2.5 4.5-5" />
                    </svg>
                </span>
                <span>{{ $line }}</span>
            </li>
            @endforeach
        </ul>

        <p class="mt-10 text-xs text-ink-500 lg:mt-0">&copy; {{ date('Y') }} {{ config('agent.agency') }}</p>
    </aside>

    {{-- The form. --}}
    <main class="flex items-center justify-center px-5 py-12 sm:px-8">
        <div class="w-full max-w-sm">
            <p class="text-[11px] font-semibold tracking-[0.18em] text-brass-700 uppercase">Admin</p>
            <h1 class="mt-1 font-display text-3xl text-ink-900">Sign in</h1>
            <p class="mt-2 text-sm text-ink-500">Use the account an owner gave you. Every action is recorded against it.</p>

            @php $demo = config('agent.demo'); @endphp

            @if ($demo['enabled'])
            <div class="mt-6 rounded-xl border border-brass-200 bg-brass-50 px-4 py-3 text-sm text-brass-700">
                <p class="flex items-start gap-2.5">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path stroke-linecap="round" d="M12 11v5M12 7.5h.01" />
                    </svg>
                    <span>
                        <strong class="font-semibold">Demo login</strong>
                        <span class="block">Pick a role and the fields below are filled in for you.</span>
                    </span>
                </p>

                {{-- Only the address differs between the demo accounts, so one tap
                is enough. A <button> rather than a link: nothing navigates. --}}
                <div class="mt-3 flex flex-wrap gap-2 border-t border-brass-200/70 pt-3" role="group"
                    aria-label="Demo accounts">
                    @foreach ([
                    ['Owner', 'admin@example.com'],
                    ['Manager', 'manager@example.com'],
                    ['Agent', 'agent@example.com'],
                    ['Editor', 'editor@example.com'],
                    ] as [$role, $email])
                    <button type="button" data-demo-email="{{ $email }}"
                        aria-pressed="{{ old('email', $demo['email']) === $email ? 'true' : 'false' }}"
                        class="rounded-full border border-brass-300 bg-white px-3 py-1.5 text-xs font-semibold text-brass-700 transition hover:border-brass-500 hover:bg-brass-100 aria-pressed:border-brass-700 aria-pressed:bg-brass-700 aria-pressed:text-white">
                        {{ $role }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            <form method="POST" action="{{ route('admin.login.attempt') }}" class="mt-7 space-y-4">
                @csrf

                @if ($errors->any())
                <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </p>
                @endif

                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $demo['enabled'] ? $demo['email'] : '') }}"
                        class="input" autocomplete="username" required autofocus>
                </div>

                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password" value="{{ $demo['enabled'] ? $demo['password'] : '' }}"
                        class="input" autocomplete="current-password" required>
                </div>

                <label class="flex items-center gap-2 text-sm text-ink-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-ink-300 text-ink-900 focus:ring-brass-500">
                    Keep me signed in
                </label>

                <button type="submit" class="btn-primary w-full">Sign in</button>
            </form>

            <p class="mt-6 text-sm text-ink-500">
                <a href="{{ route('home') }}" class="font-medium text-ink-700 hover:text-brass-700">&larr; Back to the website</a>
            </p>
        </div>
    </main>

    @if ($demo['enabled'])
    <script>
        // Fills the form from a role button. The password is read from this page's
        // own demo config, so changing DEMO_LOGIN_PASSWORD needs no edits here.
        (() => {
            const form = document.querySelector('main form')
            if (!form) return

            const email = form.querySelector('#email')
            const password = form.querySelector('#password')
            const buttons = Array.from(document.querySelectorAll('[data-demo-email]'))

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    email.value = button.dataset.demoEmail
                    password.value = @json($demo['password'])

                    buttons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)))

                    form.querySelector('[type="submit"]').focus()
                })
            })
        })()
    </script>
    @endif

</body>

</html>