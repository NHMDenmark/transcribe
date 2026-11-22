@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{!! get_dialogs('Create Language', $active_language) !!}</h1>
    <form method="POST" action="{{ url('languages') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group mb-3">
            <label for="iso2" class="col-form-label">{!! get_dialogs('ISO2 Country Code:', $active_language) !!} {!! help_hint('language_code', $active_language) !!}</label>
            <input type="text" class="form-control" id="iso2" name="iso2" placeholder="iso2" value="{{ old('iso2') }}">
        </div>
        <div class="form-group mb-3">
            <label for="text" class="col-form-label">{!! get_dialogs('Name:', $active_language) !!}</label>
            <input type="text" class="form-control" id="text" name="text" placeholder="{{ get_dialogs_simple('Language Name', $active_language) }}" value="{{ old('text') }}">
        </div>
        <div class="form-group mb-3">
            <input name="cover_image" type="file">
        </div>
        <button type="submit" class="btn btn-primary">{!! get_dialogs('Submit', $active_language) !!}</button>
    </form>
</div>
@endsection
