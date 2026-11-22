@extends('layouts.app')

@section('content')
<div class="container">
    @can('manage-access')
    <div class="card mt-3">
        <div class="card-header">
            {!! get_dialogs('Access without logging in', $active_language) !!} {!! help_hint('settings_access', $active_language) !!}
        </div>
        <div class="card-body">
            <form method="POST" action="{{ url('/settings/access') }}">
                @csrf
                <?php $mode = $setting->anonymousAccess(); ?>
                <p class="text-muted">{!! get_dialogs('What people who are not logged in may do on this platform.', $active_language) !!}</p>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="anonymous_access" id="access_login" value="login_required" {{ $mode === 'login_required' ? 'checked' : '' }}>
                    <label class="form-check-label" for="access_login"><strong>{!! get_dialogs('Login required', $active_language) !!}</strong><br><span class="text-muted">{!! get_dialogs('Every page asks for a login first.', $active_language) !!}</span></label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="anonymous_access" id="access_browse" value="browse" {{ $mode === 'browse' ? 'checked' : '' }}>
                    <label class="form-check-label" for="access_browse"><strong>{!! get_dialogs('Anyone can browse', $active_language) !!}</strong><br><span class="text-muted">{!! get_dialogs('Visitors can look at the home page, the projects and the specimens, but must log in to submit anything.', $active_language) !!}</span></label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="anonymous_access" id="access_submit" value="submit" {{ $mode === 'submit' ? 'checked' : '' }}>
                    <label class="form-check-label" for="access_submit"><strong>{!! get_dialogs('Anyone can browse and submit', $active_language) !!}</strong><br><span class="text-muted">{!! get_dialogs('Visitors can also transcribe. What they submit is tied to their browser session instead of a user account, and counts with the points of the lowest level.', $active_language) !!}</span></label>
                </div>

                <button type="submit" class="btn btn-primary">{!! get_dialogs('Save', $active_language) !!}</button>
            </form>
        </div>
    </div>
    @endcan

    @can('manage-access')
    <div class="card mt-3">
        <div class="card-header">
            {!! get_dialogs('Creating accounts', $active_language) !!} {!! help_hint('settings_registration', $active_language) !!}
        </div>
        <div class="card-body">
            <form method="POST" action="{{ url('/settings/registration') }}">
                @csrf
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="self_registration" id="self_registration" value="1" {{ $setting->allowsRegistration() ? 'checked' : '' }}>
                    <label class="form-check-label" for="self_registration"><strong>{!! get_dialogs('Anyone can create their own account', $active_language) !!}</strong><br><span class="text-muted">{!! get_dialogs('A Register link is shown. People confirm their e-mail address and start at the lowest level. When this is off, accounts are created by an administrator, or by signing in with Google or GitHub.', $active_language) !!}</span></label>
                </div>
                <button type="submit" class="btn btn-primary">{!! get_dialogs('Save', $active_language) !!}</button>
            </form>
        </div>
    </div>
    @endcan

    @can('manage-access')
    <div class="card mt-3">
        <div class="card-header">
            {!! get_dialogs('Scheduled tasks', $active_language) !!} {!! help_hint('settings_scheduler', $active_language) !!}
        </div>
        <div class="card-body">
            @if ($setting->schedulerRunning())
                <p class="mb-0"><span class="badge bg-success">{!! get_dialogs('Running', $active_language) !!}</span> {!! get_dialogs('The scheduler ran', $active_language) !!} {{ $setting->scheduler_seen_at->diffForHumans() }}.</p>
            @else
                <p class="mb-2"><span class="badge bg-danger">{!! get_dialogs('Not running', $active_language) !!}</span>
                    @if ($setting->scheduler_seen_at)
                        {!! get_dialogs('The scheduler last ran', $active_language) !!} {{ $setting->scheduler_seen_at->diffForHumans() }}.
                    @else
                        {!! get_dialogs('The scheduler has never run.', $active_language) !!}
                    @endif
                </p>
                <p class="text-muted mb-0">{!! get_dialogs('The weekly ranking, the nightly figures and the automatic sync depend on one cron line on the server. See the installation guide, section Scheduled tasks.', $active_language) !!}</p>
            @endif

            <hr>
            <form method="POST" action="{{ url('/settings/scheduling') }}">
                @csrf
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="auto_sync" id="auto_sync_global" value="1" {{ $setting->auto_sync ? 'checked' : '' }}>
                    <label class="form-check-label" for="auto_sync_global"><strong>{!! get_dialogs('Automatic sync', $active_language) !!}</strong> {!! help_hint('settings_auto_sync', $active_language) !!}<br><span class="text-muted">{!! get_dialogs('Master switch. Each project then has its own schedule in its form; switched off, nothing is sent by itself.', $active_language) !!}</span></label>
                </div>
                <div class="mb-3">
                    <label for="schedule_timezone" class="form-label">{!! get_dialogs('Time zone of the schedules', $active_language) !!}</label>
                    <select class="form-select" name="schedule_timezone" id="schedule_timezone">
                        @foreach (\DateTimeZone::listIdentifiers() as $zone)
                            <option value="{{ $zone }}" {{ $setting->schedule_timezone === $zone ? 'selected' : '' }}>{{ $zone }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">{!! get_dialogs('Save', $active_language) !!}</button>
                <a class="ms-2" href="{{ url('sync-runs') }}">{!! get_dialogs('History of the sync runs', $active_language) !!}</a>
            </form>
        </div>
    </div>
    @endcan

    @can('manage-settings')
    <div class="card mt-3">
        <div class="card-header">
            {!! get_dialogs('Branding', $active_language) !!}
        </div>
        <div class="card-body">
            <p class="text-muted">
                {!! get_dialogs("The logo shown in the navbar, and the loading indicator shown while field suggestions are fetched on the transcription form. Leave either blank to keep it unchanged - there's always a fallback (the app name as text for the logo, a plain spinner for the loading indicator) so neither is ever required.", $active_language) !!}
            </p>

            <form method="POST" action="{{ url('/settings') }}" enctype="multipart/form-data">
                @csrf

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">{!! get_dialogs('Logo', $active_language) !!} {!! help_hint('settings_logo', $active_language) !!}</label>
                        <div class="mb-2">
                            @if ($setting->logoUrl())
                                <img src="{{ $setting->logoUrl() }}" alt="{{ get_dialogs_simple('Current logo', $active_language) }}" style="max-height:60px;max-width:100%;">
                            @else
                                <span class="text-muted fst-italic">{!! get_dialogs('No logo set - the app name is shown as text instead.', $active_language) !!}</span>
                            @endif
                        </div>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        @if ($setting->logoUrl())
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="reset_logo" value="1" id="reset_logo">
                                <label class="form-check-label" for="reset_logo">
                                    {!! get_dialogs('Remove the current logo (fall back to text)', $active_language) !!}
                                </label>
                            </div>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">{!! get_dialogs('Loading indicator (GIF)', $active_language) !!}</label>
                        <div class="mb-2">
                            @if ($setting->loadingGifUrl())
                                <img src="{{ $setting->loadingGifUrl() }}" alt="{{ get_dialogs_simple('Current loading indicator', $active_language) }}" style="max-height:60px;">
                            @else
                                <span class="text-muted fst-italic">{!! get_dialogs('No custom GIF set - a plain spinner is shown instead.', $active_language) !!}</span>
                            @endif
                        </div>
                        <input type="file" name="loading_gif" class="form-control" accept="image/gif">
                        @if ($setting->loadingGifUrl())
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="reset_loading_gif" value="1" id="reset_loading_gif">
                                <label class="form-check-label" for="reset_loading_gif">
                                    {!! get_dialogs('Remove the current GIF (fall back to a plain spinner)', $active_language) !!}
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">{!! get_dialogs('Save', $active_language) !!}</button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
