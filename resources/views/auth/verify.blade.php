@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{!! get_dialogs('Verify Your Email Address', $active_language) !!}</div>

                <div class="card-body">
                    @if (session('resent'))
                        <div class="alert alert-success" role="alert">
                            {!! get_dialogs('A fresh verification link has been sent to your email address.', $active_language) !!}
                        </div>
                    @endif

                    {!! get_dialogs('Before proceeding, please check your email for a verification link.', $active_language) !!}
                    {!! get_dialogs('If you did not receive the email', $active_language) !!},
                    <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 m-0 align-baseline">{!! get_dialogs('click here to request another', $active_language) !!}</button>.
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
