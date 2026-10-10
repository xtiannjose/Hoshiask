<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($supportLogoUrl = \App\Models\User::supportLogoUrl())
    <title>Sign In | {{ config('app.name') }}</title>
    @include('partials.head-assets')
</head>
<body
    class="font-sans antialiased"
    x-data="loginPageState"
    data-login-page
>
    <div class="motion-page-enter relative min-h-dvh overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div class="login-orb-left pointer-events-none absolute -left-20 top-8 h-72 w-72 rounded-full bg-hoshishi-blue-300/20 blur-3xl"></div>
        <div class="login-orb-right pointer-events-none absolute -right-24 bottom-10 h-80 w-80 rounded-full bg-hoshishi-blue-200/30 blur-3xl"></div>

        <button
            type="button"
            @click="toggleDarkMode"
            class="app-pressable login-toggle fixed right-4 top-4 z-30 inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-700 transition hover:bg-white sm:right-6 sm:top-6"
            :aria-pressed="darkMode.toString()"
            aria-label="Toggle dark mode"
        >
            <x-ui.icon name="sun" class="theme-icon-sun h-5 w-5" />
            <x-ui.icon name="moon" class="theme-icon-moon h-5 w-5" />
        </button>

        <div class="login-card relative mx-auto grid max-w-5xl overflow-hidden rounded-2xl border border-slate-200 shadow-2xl shadow-slate-200/70 backdrop-blur md:grid-cols-2">
            <div class="login-hero hero-glow hidden h-full flex-col justify-between p-10 md:flex">
                <div>
                    <img src="{{ $supportLogoUrl }}" alt="{{ \App\Models\User::supportOrganizationName() }} logo" class="h-14 w-auto">
                    <h1 class="mt-8 font-display text-3xl font-semibold text-slate-900">{{ config('app.name') }}</h1>
                    <p class="mt-3 max-w-sm text-sm text-slate-600">
                        Managed by {{ \App\Models\User::supportOrganizationName() }} for secure ticket intake and support coordination.
                    </p>
                </div>
                <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Secure Internal Portal</p>
            </div>

            <div class="p-8 sm:p-10">
                <div class="mb-8">
                    <h2 class="font-display text-2xl font-semibold text-slate-900">Sign in</h2>
                </div>

                <form action="{{ route('login') }}" method="POST" class="space-y-5" data-submit-feedback>
                    @csrf

                    @if (session('login_notice'))
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
                            {{ session('login_notice') }}
                        </div>
                    @endif

                    <div>
                        <label for="login" class="form-label">Username or Email</label>
                        <input id="login" name="login" type="text" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus
                               class="login-input form-input @error('login') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                               placeholder="Username or email" value="{{ old('login') }}">
                    </div>

                    <div>
                        <label for="password" class="form-label">Password</label>
                        <div class="relative">
                            <input id="password" name="password" type="password" autocomplete="current-password" required
                                   class="login-input form-input pr-12" placeholder="Enter your password">
                            <button
                                type="button"
                                data-login-show-password
                                class="login-peek absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-xl text-slate-500 transition hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-hoshishi-primary"
                                aria-label="Show password"
                                aria-pressed="false"
                                aria-controls="password"
                                title="Show password"
                            >
                                <x-ui.icon name="eye" data-eye-open class="h-5 w-5" />
                                <svg data-eye-closed class="hidden h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.98 8.223A10.477 10.477 0 0 0 2.458 12c1.274 4.057 5.065 7 9.542 7 1.99 0 3.84-.58 5.397-1.58M6.228 6.228A9.956 9.956 0 0 1 12 5c4.477 0 8.268 2.943 9.542 7a10.52 10.52 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <label class="inline-flex items-center text-sm text-slate-600">
                        <input id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }} class="mr-2 rounded border-slate-300 text-hoshishi-blue-600 focus:ring-hoshishi-blue-500">
                        Remember me
                    </label>

                    <button type="submit" class="btn-primary w-full">
                        Sign in
                    </button>
                </form>

                @php($passwordHelpContact = \App\Support\PasswordHelp::contact())
                <p class="mt-4 text-center text-sm text-slate-600" data-password-help>
                    @if($passwordHelpContact === null)
                        Forgot your password? Contact your administrator.
                    @elseif(\App\Support\PasswordHelp::contactIsEmail())
                        Forgot your password? Contact your administrator at <a href="mailto:{{ $passwordHelpContact }}" class="font-semibold text-hoshishi-blue-700 underline">{{ $passwordHelpContact }}</a>.
                    @else
                        Forgot your password? Contact your administrator at <span class="font-semibold">{{ $passwordHelpContact }}</span>.
                    @endif
                </p>

                <p class="mt-3 text-center text-xs text-slate-500">
                    By signing in, you agree to the
                    <button type="button" @click="openLegalModal('terms')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900">Terms</button>,
                    <button type="button" @click="openLegalModal('privacy')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900">Privacy Notice</button>,
                    and
                    <button type="button" @click="openLegalModal('ticket-consent')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900">Ticket Consent</button>.
                </p>
            </div>
        </div>
    </div>
    @include('legal.modal')
</body>
</html>
