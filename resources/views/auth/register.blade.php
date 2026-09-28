@extends('layouts.portal')

@section('title', 'Create account')

@section('content')
<main class="auth-layout">
    <section class="auth-story" aria-label="Meterwise introduction">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span>
            <span>meterwise</span>
        </a>
        <div class="story-copy">
            <p class="eyebrow eyebrow-light">A BETTER VIEW OF BILLING</p>
            <h1>Make every<br>unit <em>count.</em></h1>
            <p class="story-description">Bring customer usage, plan economics, and billing signals into one calm workspace.</p>
            <div class="story-note"><span class="note-dot"></span><span>YOUR MERCHANT DATA STAYS YOURS</span></div>
        </div>
        <div class="story-foot"><span>USAGE IN. CLARITY OUT.</span><span>02 / 03</span></div>
    </section>

    <section class="auth-panel">
        <div class="auth-panel-top"><span>START YOUR WORKSPACE</span><a href="{{ route('login') }}">Sign in <span aria-hidden="true">↗</span></a></div>
        <div class="auth-form-wrap register-form-wrap">
            <p class="eyebrow">GET STARTED</p>
            <h2>Create your<br>merchant account.</h2>
            <p class="form-intro">Set up your workspace to start measuring usage.</p>

            @if ($errors->any())
                <div class="form-alert" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="auth-form">
                @csrf
                <label for="merchant_name">Company name</label>
                <input id="merchant_name" name="merchant_name" type="text" autocomplete="organization" value="{{ old('merchant_name') }}" placeholder="Acme, Inc." required autofocus>

                <label for="email">Work email</label>
                <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" placeholder="you@company.com" required>

                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required>
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </button>
                </div>

                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Enter it once more" required>

                <button class="button button-dark button-full" type="submit">Create workspace <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
        </div>
        <footer class="auth-footer"><span>© {{ now()->year }} Meterwise</span><span>SECURE MERCHANT ACCESS</span></footer>
    </section>
</main>
@endsection