@extends('layouts.portal')

@section('title', 'Sign in')

@section('content')
<main class="auth-layout">
    <section class="auth-story" aria-label="Meterwise introduction">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span>
            <span>meterwise</span>
        </a>
        <div class="story-copy">
            <p class="eyebrow eyebrow-light">SUBSCRIPTION INTELLIGENCE</p>
            <h1>Usage, made<br><em>understandable.</em></h1>
            <p class="story-description">A clear view of what your customers use, what they owe, and where to look next.</p>
            <div class="story-visual" aria-hidden="true">
                <div class="visual-topline"><span>MONTHLY USAGE</span><span>+18.6%</span></div>
                <div class="visual-number">2.48<span>m</span></div>
                <div class="visual-chart">
                    <i style="--bar: 28%"></i><i style="--bar: 40%"></i><i style="--bar: 35%"></i><i style="--bar: 52%"></i><i style="--bar: 46%"></i><i style="--bar: 66%"></i><i style="--bar: 58%"></i><i style="--bar: 78%"></i><i style="--bar: 72%"></i><i style="--bar: 92%"></i><i style="--bar: 84%"></i><i style="--bar: 100%"></i>
                </div>
                <div class="visual-months"><span>APR</span><span>MAY</span><span>JUN</span><span>JUL</span><span>AUG</span><span>SEP</span></div>
            </div>
        </div>
        <div class="story-foot"><span>BUILT FOR RECURRING REVENUE</span><span>01 / 03</span></div>
    </section>

    <section class="auth-panel">
        <div class="auth-panel-top"><span>MERCHANT PORTAL</span><a href="{{ route('register') }}">Create account <span aria-hidden="true">↗</span></a></div>
        <div class="auth-form-wrap">
            <p class="eyebrow">WELCOME BACK</p>
            <h2>Sign in to<br>your workspace.</h2>
            <p class="form-intro">Your billing picture is right where you left it.</p>

            @if ($errors->any())
                <div class="form-alert" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf
                <label for="email">Work email</label>
                <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" placeholder="you@company.com" required autofocus>

                <div class="label-row"><label for="password">Password</label></div>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </button>
                </div>
                <button class="button button-dark button-full" type="submit">Sign in <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">New to Meterwise? <a href="{{ route('register') }}">Create an account</a></p>
        </div>
        <footer class="auth-footer"><span>© {{ now()->year }} Meterwise</span><span>SECURE MERCHANT ACCESS</span></footer>
    </section>
</main>
@endsection