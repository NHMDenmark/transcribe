@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 720px;">
    <h1>{!! get_dialogs('My profile', $active_language) !!}</h1>

    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-4">
            <div>{!! display_avatar($user, 96) !!}</div>
            <div>
                <h5 class="card-title mb-1">{!! get_dialogs('Profile photo', $active_language) !!}</h5>
                <p class="form-text mt-0 mb-2">{!! get_dialogs('Optional. Without one, a circle with your initials is shown. If you hide your name, other users see a neutral pattern instead of your photo.', $active_language) !!}</p>
                @if(\App\Services\AvatarStore::available())
                <?php
                $photo_formats = array_values(array_unique(array_map(fn($f) => $f === 'jpeg' ? 'jpg' : $f, \App\Services\AvatarStore::formats())));
                ?>
                <form method="post" action="{{ url('/profile/avatar') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-start">
                    @csrf
                    <div>
                        <input type="file" name="avatar" accept="{{ implode(',', array_map(fn($f) => '.' . $f, $photo_formats)) }}" class="form-control form-control-sm @error('avatar') is-invalid @enderror" required>
                        @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ strtoupper(implode(', ', $photo_formats)) }}, {!! get_dialogs('up to 2 MB. It is cropped to a square.', $active_language) !!}</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">{!! get_dialogs('Upload', $active_language) !!}</button>
                </form>
                @else
                <p class="form-text">{!! get_dialogs('Photos are not available on this server.', $active_language) !!}</p>
                @endif
                @if($user->avatar)
                <form method="post" action="{{ url('/profile/avatar') }}" class="mt-2">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-secondary btn-sm">{!! get_dialogs('Remove photo', $active_language) !!}</button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="{{ url('/profile') }}">
                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label for="name" class="form-label">{!! get_dialogs('Name or pseudonym', $active_language) !!}</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{!! get_dialogs('This is what other users see next to your comments and contributions. It can be a pseudonym.', $active_language) !!}</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">{!! get_dialogs('E-mail', $active_language) !!}</label>
                    <input type="text" class="form-control" value="{{ $user->email }}" disabled>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="public_name" name="public_name" value="1" {{ old('public_name', $user->public_name) ? 'checked' : '' }}>
                    <label class="form-check-label" for="public_name">{!! get_dialogs('Show my name publicly (leaderboard & activity feed)', $active_language) !!}</label>
                    <div class="form-text">{!! get_dialogs('If you switch this off, other users see "hidden user" instead of your name. Administrators can still tell who you are.', $active_language) !!}</div>
                </div>

                <button type="submit" class="btn btn-primary">{!! get_dialogs('Save', $active_language) !!}</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">{!! get_dialogs('Change password', $active_language) !!}</h5>
            <form method="post" action="{{ url('/profile/password') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="current_password" class="form-label">{!! get_dialogs('Current password', $active_language) !!}</label>
                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" autocomplete="current-password" required>
                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{!! get_dialogs('New password', $active_language) !!}</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">{!! get_dialogs('Confirm new password', $active_language) !!}</label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-primary">{!! get_dialogs('Change password', $active_language) !!}</button>
                <div class="form-text mt-2">{!! get_dialogs('Signed in with Google or GitHub and never set a password? Use "Forgot password" on the login page to set one.', $active_language) !!}</div>
            </form>
        </div>
    </div>

    <div class="card mb-4 border-danger">
        <div class="card-body">
            <h5 class="card-title text-danger">{!! get_dialogs('Delete my account', $active_language) !!}</h5>
            <p>{!! get_dialogs('This removes your name, e-mail address, photo and password, and you will no longer be able to sign in. What you contributed (the values you submitted, your comments, your points) stays in the platform, shown as "Deleted user", so the work of everybody else is not affected. This cannot be undone.', $active_language) !!}</p>
            <form method="post" action="{{ url('/profile') }}" onsubmit="return confirm({{ json_encode(get_dialogs_simple('Delete your account for good?', $active_language)) }});">
                @csrf
                @method('DELETE')
                <div class="mb-3">
                    <label for="confirm_email" class="form-label">{!! get_dialogs('To confirm, type your e-mail address:', $active_language) !!} <code>{{ $user->email }}</code></label>
                    <input type="email" class="form-control @error('confirm_email') is-invalid @enderror" id="confirm_email" name="confirm_email" autocomplete="off" required>
                    @error('confirm_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-danger">{!! get_dialogs('Delete my account', $active_language) !!}</button>
            </form>
        </div>
    </div>
</div>
@endsection
