@extends('layouts.app')

@section('content')
<style>
    :root {
        --google-red: #ea4335;
        --github-black: #24292e;
    }
    .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .card-header { background: transparent; border-bottom: none; font-weight: 700; font-size: 1.5rem; text-align: center; padding-top: 2rem; color: #333; }
    .btn-social { 
        display: flex; align-items: center; justify-content: center; 
        padding: 12px; border-radius: 8px; font-weight: 500; 
        transition: all 0.3s ease; text-decoration: none; margin-bottom: 12px;
        font-size: 0.95rem;
    }
    .btn-google { border: 1px solid #e0e0e0; color: #555; background: #fff; }
    .btn-google:hover { background: #f8f8f8; border-color: var(--google-red); color: var(--google-red); }
    .btn-github { background: var(--github-black); color: #fff; }
    .btn-github:hover { background: #000; color: #fff; opacity: 0.9; }
    .divider { display: flex; align-items: center; text-align: center; margin: 1.8rem 0; color: #999; text-transform: uppercase; letter-spacing: 1px; }
    .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #eee; }
    .divider:not(:empty)::before { margin-right: .5em; }
    .divider:not(:empty)::after { margin-left: .5em; }
    .form-control { border-radius: 8px; padding: 12px; border: 1px solid #e0e0e0; }
    .form-control:focus { box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15); border-color: #0d6efd; }
</style>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="card p-3">
                <div class="card-header">{!! get_dialogs('Welcome Back', $active_language) !!}</div>

                <div class="card-body">
                    <div class="social-login-hooks">
                        <a href="{{ route('social.redirect', 'google') }}" class="btn-social btn-google">
                            <img src="https://www.svgrepo.com/show/355037/google.svg" width="20" class="me-2" alt="Google">
                            {!! get_dialogs('Sign in with Google', $active_language) !!}
                        </a>

                        <a href="{{ route('social.redirect', 'github') }}" class="btn-social btn-github">
                            <img src="https://www.svgrepo.com/show/512317/github-142.svg" width="20" class="me-2" style="filter: invert(1);" alt="GitHub">
                            {!! get_dialogs('Sign in with GitHub', $active_language) !!}
                        </a>
                    </div>

                    <div class="divider small fw-bold">{!! get_dialogs('Or use email', $active_language) !!}</div>

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold text-muted">{!! get_dialogs('Email Address', $active_language) !!}</label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="name@example.com">
                            @error('email')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label for="password" class="form-label small fw-bold text-muted">{!! get_dialogs('Password', $active_language) !!}</label>
                                @if (Route::has('password.request'))
                                    <a class="small text-decoration-none" href="{{ route('password.request') }}">{!! get_dialogs('Forgot password?', $active_language) !!}</a>
                                @endif
                            </div>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password" placeholder="••••••••">
                            @error('password')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label small text-muted" for="remember">{!! get_dialogs('Remember me', $active_language) !!}</label>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary py-2 fw-bold" style="border-radius: 8px;">
                                {!! get_dialogs('Sign In', $active_language) !!}
                            </button>
                        </div>
                    </form>

                    @if(session('error'))
                        <div class="alert alert-danger mt-4 small border-0 text-center">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
            </div>
            
        </div>
    </div>
</div>
@endsection