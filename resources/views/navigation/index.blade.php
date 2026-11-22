@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <h1 class="mb-0">{!! get_dialogs('Menu', $active_language) !!}</h1>
        <span class="d-flex gap-2">
            <a href="{{ url('contents') }}" class="btn btn-outline-primary"><i class="bi bi-card-text"></i> {!! get_dialogs('Contents', $active_language) !!}</a>
            <a href="{{ url('navigation/create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {!! get_dialogs('New menu item', $active_language) !!}</a>
        </span>
    </div>

    <p class="text-muted">{!! get_dialogs('The structure of the site, as a tree of any depth: a group is a heading with items under it, a link points to another address, and a contents item is where texts are shown. Drag to reorder, or to move an item under another one. The texts themselves are managed under Contents. Root and administrators always see everything; "only for logged-in users" restricts what other visitors see.', $active_language) !!}</p>

    <div id="nav-message" class="alert alert-danger d-none"></div>

    <ul class="nav-tree list-unstyled mb-0" id="nav-root" style="min-height: 40px">
        @foreach($tree as $node)
            @include('navigation._node', ['node' => $node])
        @endforeach
    </ul>

    @if(!count($tree))
        <p class="text-muted">{!! get_dialogs('Nothing here yet.', $active_language) !!}</p>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    (function () {
        var message = document.getElementById('nav-message');

        // the whole menu tree, flattened as id / parent / position, in document order
        function collect(list, parentId, out) {
            Array.prototype.forEach.call(list.children, function (li, index) {
                if (!li.dataset.id) { return; }
                out.push({id: parseInt(li.dataset.id, 10), parent_id: parentId, position: index});
                var sub = li.querySelector(':scope > ul.nav-tree');
                if (sub) { collect(sub, parseInt(li.dataset.id, 10), out); }
            });
            return out;
        }

        function save() {
            fetch({!! json_encode(url('navigation/sort')) !!}, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': {!! json_encode(csrf_token()) !!}},
                body: JSON.stringify({items: collect(document.getElementById('nav-root'), null, [])})
            }).then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        message.textContent = body.message || 'Could not save the new order.';
                        message.classList.remove('d-none');
                        setTimeout(function () { window.location.reload(); }, 2500);
                    } else {
                        message.classList.add('d-none');
                        window.location.reload();   // the paths and states depend on where things are
                    }
                });
            });
        }

        document.querySelectorAll('ul.nav-tree').forEach(function (list) {
            new Sortable(list, {group: 'nav', handle: '.drag-handle', animation: 150, fallbackOnBody: true, swapThreshold: 0.65, onEnd: save});
        });
    })();
</script>
@endsection
