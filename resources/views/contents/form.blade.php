@extends('layouts.app')

@section('content')
@php
    $editing = $content->exists;
    $dt = fn($value) => $value ? \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
@endphp
<div class="container">
    <h1>{!! $editing ? get_dialogs('Edit content', $active_language) : get_dialogs('New content', $active_language) !!}</h1>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $editing ? url('contents/' . $content->id) : url('contents') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="back" value="{{ $back }}">

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="navigation_id" class="form-label">{!! get_dialogs('Shown under', $active_language) !!}</label>
                        <select name="navigation_id" id="navigation_id" class="form-select" required>
                            @foreach($places as $place)
                                <option value="{{ $place['id'] }}" @selected((string) old('navigation_id', $navigationId) === (string) $place['id'])>{{ $place['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{!! get_dialogs('Only menu items of the kind "contents" can hold contents. Several contents under one item are shown one after the other.', $active_language) !!}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check mt-md-4">
                            <input class="form-check-input" type="checkbox" name="members_only" id="members_only" value="1" @checked(old('members_only', $content->members_only))>
                            <label class="form-check-label" for="members_only">{!! get_dialogs('Only for logged-in users', $active_language) !!}</label>
                            <div class="form-text">{!! get_dialogs('Otherwise every visitor sees it once published. Root and administrators always see everything.', $active_language) !!}</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="publish_at" class="form-label">{!! get_dialogs('Publish at', $active_language) !!}</label>
                        <input type="datetime-local" name="publish_at" id="publish_at" class="form-control" value="{{ old('publish_at', $dt($content->publish_at)) }}">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="publish_now" id="publish_now" value="1" @checked(old('publish_now'))>
                            <label class="form-check-label" for="publish_now">{!! get_dialogs('Publish now', $active_language) !!}</label>
                        </div>
                        <div class="form-text">{!! get_dialogs('Without a publish date this is a draft that only root and administrators see.', $active_language) !!}</div>
                    </div>
                    <div class="col-md-6">
                        <label for="archive_at" class="form-label">{!! get_dialogs('Archive at', $active_language) !!}</label>
                        <input type="datetime-local" name="archive_at" id="archive_at" class="form-control" value="{{ old('archive_at', $dt($content->archive_at)) }}">
                        <div class="form-text">{!! get_dialogs('From then on visitors no longer see it.', $active_language) !!}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{!! get_dialogs('Texts, by language', $active_language) !!}</div>
            <div class="card-body">
                <ul class="nav nav-tabs" role="tablist">
                    @foreach($langCodes as $language)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" data-bs-target="#lang-{{ $language }}" type="button" role="tab">{{ $language }}</button>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content pt-3">
                    @foreach($langCodes as $language)
                    <div class="tab-pane fade @if($loop->first) show active @endif" id="lang-{{ $language }}" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label" for="title-{{ $language }}">{!! get_dialogs('Title', $active_language) !!}</label>
                            <input type="text" id="title-{{ $language }}" name="titles[{{ $language }}]" class="form-control" value="{{ old('titles.' . $language, strip_tags($titles[$language] ?? '')) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="body-{{ $language }}">{!! get_dialogs('Text', $active_language) !!}</label>
                            <textarea id="body-{{ $language }}" name="bodies[{{ $language }}]" class="form-control ckeditor" rows="12">{{ old('bodies.' . $language, $bodies[$language] ?? '') }}</textarea>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="form-text">{!! get_dialogs('A language left empty shows the default language instead.', $active_language) !!}</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">{!! get_dialogs('Save', $active_language) !!}</button>
        <a href="{{ $cancelUrl }}" class="btn btn-link">{!! get_dialogs('Cancel', $active_language) !!}</a>
    </form>

    @if($editing)
        <form method="post" action="{{ url('contents/' . $content->id) }}" class="mt-4" onsubmit="return confirm({{ json_encode(get_dialogs_simple('Delete this content?', $active_language)) }});">
            @csrf
            @method('DELETE')
            <input type="hidden" name="back" value="{{ $back }}">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> {!! get_dialogs('Delete this content', $active_language) !!}</button>
        </form>
    @endif
</div>
@endsection
