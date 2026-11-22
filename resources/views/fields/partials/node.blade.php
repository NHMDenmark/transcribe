<div class="node-level mb-2" data-level="{{ $node->id }}" data-depth="{{ $depth }}" style="margin-left: {{ $depth * 20 }}px;">
    <div class="row">
        <label class="col-sm-2 col-form-label">{{ $node->part }}</label>
        <div class="col-sm-4">
            <select class="form-select param-select" name="levels[{{ $node->id }}][param]">
                <option value="">-- choose --</option>
                {!! $tables_options !!}
            </select>
        </div>
        <div class="col-sm-3">
            <select class="form-select action-select" name="levels[{{ $node->id }}][action]">
                <option value="">-- action --</option>
                <option value="node" @if($node->type == 'node') selected @endif>node</option>
                <option value="write" @if($node->type == 'write') selected @endif>write</option>
                <option value="pick" @if($node->type == 'pick') selected @endif>pick</option>
            </select>
        </div>
    </div>

    @if(!empty($node->children))
        @foreach($node->children as $child)
            @include('fields.partials.node', ['node' => $child, 'depth' => $depth + 1])
        @endforeach
    @endif
</div>
