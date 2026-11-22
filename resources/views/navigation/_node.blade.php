@php
    $stateClass = ['live' => 'success', 'login only' => 'info'][$node['state']] ?? 'warning';
    $icons = ['contents' => 'bi-file-earmark-text', 'group' => 'bi-folder2', 'link' => 'bi-link-45deg'];
@endphp
<li class="nav-node list-group-item p-0 border-0 mb-1" data-id="{{ $node['id'] }}">
    <div class="d-flex align-items-center gap-2 border rounded bg-white px-2 py-1">
        <span class="drag-handle text-muted" style="cursor:grab" title="{{ get_dialogs_simple('Drag to move', $active_language) }}"><i class="bi bi-grip-vertical"></i></span>
        <i class="bi {{ $icons[$node['type']] ?? 'bi-file-earmark' }} text-muted"></i>
        <strong>{{ $node['label'] }}</strong>{!! dialog_edit_trigger('navigation_name_' . $node['id']) !!}
        <span class="text-muted small">{{ $node['type'] === 'link' ? $node['link_url'] : '/' . $node['path'] }}</span>
        <span class="badge text-bg-{{ $stateClass }}">{{ $node['state'] }}</span>
        @if(!$node['show_in_menu'])<span class="badge text-bg-secondary">{!! get_dialogs('not in the menu', $active_language) !!}</span>@endif
        @if($node['type'] === 'contents')
            <a class="badge text-bg-light border text-decoration-none" href="{{ url('contents') }}#node-{{ $node['id'] }}" title="{{ get_dialogs_simple('Manage its contents', $active_language) }}">{{ $node['content_count'] }} {!! get_dialogs('contents', $active_language) !!}</a>
        @endif
        <span class="ms-auto d-flex gap-1">
            @if($node['type'] === 'contents')
                <a class="btn btn-outline-secondary btn-sm" href="{{ url($node['path']) }}" title="{{ get_dialogs_simple('View', $active_language) }}"><i class="bi bi-eye"></i></a>
            @endif
            @if($node['type'] !== 'link')
                <a class="btn btn-outline-secondary btn-sm" href="{{ url('navigation/create?parent_id=' . $node['id']) }}" title="{{ get_dialogs_simple('Add an item under this one', $active_language) }}"><i class="bi bi-plus-lg"></i></a>
            @endif
            <a class="btn btn-primary btn-sm" href="{{ url('navigation/' . $node['id'] . '/edit') }}" title="{{ get_dialogs_simple('Edit', $active_language) }}"><i class="bi bi-pencil"></i></a>
            <form method="post" action="{{ url('navigation/' . $node['id']) }}" onsubmit="return confirm({{ json_encode(get_dialogs_simple('Delete this item and everything under it?', $active_language)) }});">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm" title="{{ get_dialogs_simple('Delete', $active_language) }}"><i class="bi bi-trash"></i></button>
            </form>
        </span>
    </div>


    @if($node['type'] !== 'link')
    <ul class="nav-tree list-unstyled ps-4 mb-0" style="min-height: 8px">
        @foreach($node['children_tree'] as $child)
            @include('navigation._node', ['node' => $child])
        @endforeach
    </ul>
    @endif
</li>
