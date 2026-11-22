{{-- One menu item and, recursively, its children: a dropdown at the top level, a flyout submenu below that. --}}
@php($hasChildren = count($item['children']) > 0)
@if($depth === 0)
    @if($hasChildren)
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="{{ $item['url'] }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $item['label'] }}{!! dialog_edit_trigger('navigation_name_' . $item['id']) !!}@if($item['state']) <span class="badge text-bg-warning ms-1" title="{{ $item['state'] }}">{{ $item['state'] }}</span>@endif</a>
        <ul class="dropdown-menu">
            @foreach($item['children'] as $child)
                @include('layouts.partials.menu_item', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    </li>
    @else
    <li class="nav-item"><a class="nav-link" href="{{ $item['url'] }}" @if($item['external']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}{!! dialog_edit_trigger('navigation_name_' . $item['id']) !!}@if($item['state']) <span class="badge text-bg-warning ms-1" title="{{ $item['state'] }}">{{ $item['state'] }}</span>@endif</a></li>
    @endif
@else
    @if($hasChildren)
    <li class="dropdown-submenu">
        <a class="dropdown-item dropdown-toggle" href="{{ $item['url'] }}">{{ $item['label'] }}{!! dialog_edit_trigger('navigation_name_' . $item['id']) !!}@if($item['state']) <span class="badge text-bg-warning ms-1" title="{{ $item['state'] }}">{{ $item['state'] }}</span>@endif</a>
        <ul class="dropdown-menu">
            @foreach($item['children'] as $child)
                @include('layouts.partials.menu_item', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    </li>
    @else
    <li><a class="dropdown-item" href="{{ $item['url'] }}" @if($item['external']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}{!! dialog_edit_trigger('navigation_name_' . $item['id']) !!}@if($item['state']) <span class="badge text-bg-warning ms-1" title="{{ $item['state'] }}">{{ $item['state'] }}</span>@endif</a></li>
    @endif
@endif
