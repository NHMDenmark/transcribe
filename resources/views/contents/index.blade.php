@extends('layouts.app')

@section('content')
@php
    $modeLabels = [
        'manual' => get_dialogs_simple('by hand', $active_language),
        'newest' => get_dialogs_simple('newest first', $active_language),
        'oldest' => get_dialogs_simple('oldest first', $active_language),
        'title' => get_dialogs_simple('by title', $active_language),
    ];
@endphp
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <h1 class="mb-0">{!! get_dialogs('Contents', $active_language) !!}</h1>
        <span class="d-flex gap-2">
            <a href="{{ url('navigation') }}" class="btn btn-outline-primary"><i class="bi bi-diagram-3"></i> {!! get_dialogs('Menu', $active_language) !!}</a>
            <a href="{{ url('contents/create') }}" class="btn btn-primary"><i class="bi bi-file-earmark-plus"></i> {!! get_dialogs('New content', $active_language) !!}</a>
        </span>
    </div>

    <p class="text-muted">{!! get_dialogs('The texts of the site, under the menu item that shows them. A content without a publish date is a draft that only root and administrators see. Each item orders its contents by hand (drag them here) or automatically; change that on the item. You can also add a content from the page of the item itself.', $active_language) !!}</p>

    <div id="content-message" class="alert alert-danger d-none"></div>

    @forelse($groups as $group)
        <div class="card mb-3" id="node-{{ $group['id'] }}">
            <div class="card-header d-flex align-items-center gap-2">
                <strong style="padding-left: {{ $group['depth'] * 1.25 }}rem">{{ $group['label'] }}</strong>{!! dialog_edit_trigger('navigation_name_' . $group['id']) !!}
                <a class="small text-muted" href="{{ url($group['path']) }}">/{{ $group['path'] }}</a>
                <span class="badge text-bg-light border" title="{{ get_dialogs_simple('Order of its contents', $active_language) }}">{{ $modeLabels[$group['sort_mode']] ?? $group['sort_mode'] }}</span>
                <a class="btn btn-outline-primary btn-sm ms-auto" href="{{ url('contents/create?navigation_id=' . $group['id']) }}"><i class="bi bi-file-earmark-plus"></i> {!! get_dialogs('New content', $active_language) !!}</a>
            </div>
            {{-- dragging reorders only when the item is ordered by hand; between items it always works --}}
            <ul class="content-list list-group list-group-flush" data-navigation-id="{{ $group['id'] }}" data-manual="{{ $group['sort_mode'] === 'manual' ? 1 : 0 }}" style="min-height: 8px">
                @foreach($group['rows'] as $row)
                    @php $rowClass = ['live' => 'success', 'login only' => 'info'][$row['state']] ?? 'warning'; @endphp
                    <li class="list-group-item d-flex align-items-center gap-2 content-row" data-id="{{ $row['id'] }}">
                        <span class="drag-handle text-muted" style="cursor:grab" title="{{ get_dialogs_simple('Drag to move', $active_language) }}"><i class="bi bi-grip-vertical"></i></span>
                        <span>{{ $row['title'] }}</span>{!! dialog_edit_trigger('content_title_' . $row['id']) !!}
                        <span class="badge text-bg-{{ $rowClass }}">{{ $row['state'] }}</span>
                        @if($row['publish_at'])<span class="text-muted small">{{ \Carbon\Carbon::createFromTimestamp($row['publish_at'])->format('Y-m-d H:i') }}</span>@endif
                        <span class="ms-auto d-flex gap-1">
                            <a class="btn btn-primary btn-sm" href="{{ url('contents/' . $row['id'] . '/edit') }}" title="{{ get_dialogs_simple('Edit', $active_language) }}"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="{{ url('contents/' . $row['id']) }}" onsubmit="return confirm({{ json_encode(get_dialogs_simple('Delete this content?', $active_language)) }});">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" title="{{ get_dialogs_simple('Delete', $active_language) }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
            @if(!count($group['rows']))
                <div class="card-body text-muted small py-2">{!! get_dialogs('Nothing here yet.', $active_language) !!}</div>
            @endif
        </div>
    @empty
        <p class="text-muted">{!! get_dialogs('There are no menu items that can hold contents yet. Create one of the kind "contents" in the menu first.', $active_language) !!}</p>
    @endforelse
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    (function () {
        var message = document.getElementById('content-message');

        // every content, with the item it sits under and its place there
        function collect() {
            var out = [];
            document.querySelectorAll('ul.content-list').forEach(function (list) {
                var navId = parseInt(list.dataset.navigationId, 10);
                Array.prototype.forEach.call(list.children, function (li, index) {
                    if (li.dataset.id) { out.push({id: parseInt(li.dataset.id, 10), navigation_id: navId, position: index}); }
                });
            });
            return out;
        }

        function save() {
            fetch({!! json_encode(url('contents/sort')) !!}, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': {!! json_encode(csrf_token()) !!}},
                body: JSON.stringify({items: collect()})
            }).then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        message.textContent = body.message || 'Could not save the new order.';
                        message.classList.remove('d-none');
                        setTimeout(function () { window.location.reload(); }, 2500);
                    } else {
                        window.location.reload();   // an item ordered automatically puts what was dropped in its place
                    }
                });
            });
        }

        document.querySelectorAll('ul.content-list').forEach(function (list) {
            new Sortable(list, {group: 'contents', handle: '.drag-handle', animation: 150, fallbackOnBody: true,
                sort: list.dataset.manual === '1', onEnd: function (event) {
                    // nothing moved: dropped back where it was
                    if (event.from === event.to && event.oldIndex === event.newIndex) { return; }
                    save();
                }});
        });
    })();
</script>
@endsection
