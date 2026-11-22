@extends('layouts.app')

@section('content')
@php
    $editing = $node->exists;
    $kinds = [
        'contents' => get_dialogs_simple('Contents (a place where texts are shown)', $active_language),
        'group' => get_dialogs_simple('Group (a menu heading)', $active_language),
        'link' => get_dialogs_simple('Link', $active_language),
    ];
@endphp
<div class="container">
    <h1>{!! $editing ? get_dialogs('Edit menu item', $active_language) : get_dialogs('New menu item', $active_language) !!}</h1>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $editing ? url('navigation/' . $node->id) : url('navigation') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        @unless($editing)<input type="hidden" name="type" value="{{ $type }}">@endunless

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label d-block">{!! get_dialogs('Kind', $active_language) !!}</label>
                        @if($editing)
                            <span class="badge text-bg-secondary">{{ $kinds[$type] ?? $type }}</span>
                        @else
                            {{-- plain links: choosing a kind reloads the form for it, no script involved --}}
                            <div class="btn-group" role="group">
                                @foreach($kinds as $value => $label)
                                    <a class="btn btn-outline-primary @if($type === $value) active @endif"
                                       href="{{ url('navigation/create') . '?' . http_build_query(array_filter(['type' => $value, 'parent_id' => $parentId])) }}">{{ $label }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label for="parent_id" class="form-label">{!! get_dialogs('Under', $active_language) !!}</label>
                        <select name="parent_id" id="parent_id" class="form-select">
                            <option value="">{{ get_dialogs_simple('(top level)', $active_language) }}</option>
                            @foreach($parents as $parent)
                                <option value="{{ $parent['id'] }}" @selected((string) old('parent_id', $parentId) === (string) $parent['id'])>{{ $parent['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="slug" class="form-label">{!! get_dialogs('Address', $active_language) !!}</label>
                        <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug', $node->slug) }}" placeholder="{{ get_dialogs_simple('made from the name when left empty', $active_language) }}">
                        <div class="form-text">
                            @if($path) {{ url($path) }} @else {!! get_dialogs('Lower case letters, digits and hyphens.', $active_language) !!} @endif
                        </div>
                    </div>

                    @if($type === 'contents')
                    <div class="col-md-6">
                        <label for="sort_mode" class="form-label">{!! get_dialogs('Order of its contents', $active_language) !!}</label>
                        <select name="sort_mode" id="sort_mode" class="form-select">
                            @foreach(['manual' => 'By hand (drag them on the Contents screen)', 'newest' => 'Newest first (by publish date)', 'oldest' => 'Oldest first (by publish date)', 'title' => 'By title, A to Z'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('sort_mode', $node->sort_mode ?: 'manual') === $value)>{{ get_dialogs_simple($label, $active_language) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    @if($type === 'link')
                    <div class="col-12">
                        <label for="link_url" class="form-label">{!! get_dialogs('Link address', $active_language) !!}</label>
                        <input type="text" name="link_url" id="link_url" class="form-control" value="{{ old('link_url', $node->link_url) }}" placeholder="/projects  or  https://example.org">
                        <div class="form-text">{!! get_dialogs('A path of this platform (starting with /) or a full address.', $active_language) !!}</div>
                    </div>
                    @endif

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="members_only" id="members_only" value="1" @checked(old('members_only', $node->members_only))>
                            <label class="form-check-label" for="members_only">{!! get_dialogs('Only for logged-in users', $active_language) !!}</label>
                            <div class="form-text">{!! get_dialogs('Otherwise every visitor sees it. Root and administrators always see everything. This also applies to what is under it.', $active_language) !!}</div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active', $editing ? $node->active : true))>
                            <label class="form-check-label" for="active">{!! get_dialogs('Active', $active_language) !!}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_in_menu" id="show_in_menu" value="1" @checked(old('show_in_menu', $editing ? $node->show_in_menu : true))>
                            <label class="form-check-label" for="show_in_menu">{!! get_dialogs('Show in the menu', $active_language) !!}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{!! get_dialogs('Name in the menu, by language', $active_language) !!}</div>
            <div class="card-body">
                @foreach($langCodes as $language)
                    <div class="row g-2 mb-2 align-items-center">
                        <div class="col-auto" style="width: 4rem"><label class="col-form-label" for="label-{{ $language }}">{{ $language }}</label></div>
                        <div class="col">
                            <input type="text" id="label-{{ $language }}" name="labels[{{ $language }}]" class="form-control" value="{{ old('labels.' . $language, strip_tags($labels[$language] ?? '')) }}">
                        </div>
                    </div>
                @endforeach
                <div class="form-text">{!! get_dialogs('A language left empty shows the default language instead.', $active_language) !!}</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">{!! get_dialogs('Save', $active_language) !!}</button>
        <a href="{{ url('navigation') }}" class="btn btn-link">{!! get_dialogs('Cancel', $active_language) !!}</a>
    </form>
</div>
@endsection
