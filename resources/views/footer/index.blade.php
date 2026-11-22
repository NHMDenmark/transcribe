@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <h1 class="mb-0">{!! get_dialogs('Footer', $active_language) !!}</h1>
        <span class="d-flex gap-2">
            <a href="{{ url('footer/create?type=links') }}" class="btn btn-outline-primary"><i class="bi bi-link-45deg"></i> {!! get_dialogs('New links block', $active_language) !!}</a>
            <a href="{{ url('footer/create?type=text') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {!! get_dialogs('New text block', $active_language) !!}</a>
        </span>
    </div>

    <p class="text-muted">{!! get_dialogs('The footer of every page is made of blocks side by side, in this order (drag to reorder). A text block holds a free text (address, contacts, credits); a links block lists menu items, and only shows each visitor the ones they may open.', $active_language) !!}</p>

    <div id="footer-message" class="alert alert-danger d-none"></div>

    <ul class="list-group" id="footer-blocks">
        @foreach($blocks as $block)
            <li class="list-group-item d-flex align-items-center gap-2" data-id="{{ $block['id'] }}">
                <span class="drag-handle text-muted" style="cursor:grab" title="{{ get_dialogs_simple('Drag to move', $active_language) }}"><i class="bi bi-grip-vertical"></i></span>
                <span class="badge text-bg-secondary">{{ $block['type'] }}</span>
                <strong>{{ $block['title'] !== '' ? $block['title'] : '—' }}</strong>{!! dialog_edit_trigger('footer_title_' . $block['id']) !!}
                <span class="text-muted small">{{ $block['summary'] }}</span>
                <span class="badge text-bg-light border">{{ $block['width'] }}/12</span>
                @unless($block['active'])<span class="badge text-bg-warning">{!! get_dialogs('inactive', $active_language) !!}</span>@endunless
                <span class="ms-auto d-flex gap-1">
                    <a class="btn btn-primary btn-sm" href="{{ url('footer/' . $block['id'] . '/edit') }}" title="{{ get_dialogs_simple('Edit', $active_language) }}"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="{{ url('footer/' . $block['id']) }}" onsubmit="return confirm({{ json_encode(get_dialogs_simple('Delete this block?', $active_language)) }});">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm" title="{{ get_dialogs_simple('Delete', $active_language) }}"><i class="bi bi-trash"></i></button>
                    </form>
                </span>
            </li>
        @endforeach
    </ul>
    @if(!count($blocks))
        <p class="text-muted">{!! get_dialogs('Nothing here yet.', $active_language) !!}</p>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    (function () {
        var list = document.getElementById('footer-blocks');
        new Sortable(list, {handle: '.drag-handle', animation: 150, onEnd: function () {
            var ids = Array.prototype.map.call(list.children, function (li) { return parseInt(li.dataset.id, 10); });
            fetch({!! json_encode(url('footer/sort')) !!}, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': {!! json_encode(csrf_token()) !!}},
                body: JSON.stringify({ids: ids})
            }).then(function (response) {
                if (!response.ok) {
                    var message = document.getElementById('footer-message');
                    message.textContent = 'Could not save the new order.';
                    message.classList.remove('d-none');
                }
            });
        }});
    })();
</script>
@endsection
