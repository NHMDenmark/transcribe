{{--
    "Columns" menu for a table: a checkbox per column, remembered in this browser.

    $tableId     id of the table to act on; its cells carry data-col="<key>"
    $storageKey  where the choice is remembered
    $columns     key => ['label' => ..., 'default' => bool]  (the columns shown until the user chooses)

    Cells of a column that is hidden by default are rendered with class d-none by the page itself, so
    there is no flash before this script runs.
--}}
<style>
    /* A long value (a text without spaces, a URL, a JSON) must not stretch the table sideways: the free-text
       columns are given a width of about 40 characters and break onto the next line, anywhere if they must.
       Nothing is cut, the whole value stays in the cell. */
    #{{ $tableId }} td, #{{ $tableId }} th { overflow-wrap: anywhere; }
    #{{ $tableId }} td[data-col="value"], #{{ $tableId }} td[data-col="remarks"], #{{ $tableId }} td[data-col="error"] {
        min-width: 24ch;
        max-width: 40ch;
        white-space: normal;
    }
</style>
<div class="dropdown d-inline-block column-picker">
    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="bi bi-layout-three-columns"></i> {!! get_dialogs('Columns', $active_language) !!}
    </button>
    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 13rem">
        @foreach($columns as $key => $col)
            <div class="form-check">
                <input class="form-check-input column-toggle" type="checkbox" id="{{ $storageKey }}-{{ $key }}" data-col="{{ $key }}" @checked($col['default'])>
                <label class="form-check-label" for="{{ $storageKey }}-{{ $key }}">{{ $col['label'] }}</label>
            </div>
        @endforeach
        <hr class="my-2">
        <button type="button" class="btn btn-link btn-sm p-0 column-reset">{!! get_dialogs('Reset', $active_language) !!}</button>
    </div>
</div>
<script>
    // this menu sits above its table, so the script waits for the page to be there
    (function () {
      function init() {
        var table = document.getElementById({!! json_encode($tableId) !!});
        var key = {!! json_encode('columns.' . $storageKey) !!};
        var defaults = {!! json_encode(array_map(fn($c) => (bool) $c['default'], $columns)) !!};
        if (!table) { return; }

        function load() {
            try { return JSON.parse(window.localStorage.getItem(key)) || {}; } catch (e) { return {}; }
        }
        function save(state) {
            try { window.localStorage.setItem(key, JSON.stringify(state)); } catch (e) { /* the choice just is not remembered */ }
        }
        function visible(state, col) {
            return Object.prototype.hasOwnProperty.call(state, col) ? !!state[col] : defaults[col];
        }
        function apply(state) {
            Object.keys(defaults).forEach(function (col) {
                var show = visible(state, col);
                table.querySelectorAll('[data-col="' + col + '"]').forEach(function (cell) { cell.classList.toggle('d-none', !show); });
                var box = document.getElementById({!! json_encode($storageKey . '-') !!} + col);
                if (box) { box.checked = show; }
            });
        }

        var state = load();
        apply(state);

        document.querySelectorAll('.column-picker .column-toggle').forEach(function (box) {
            box.addEventListener('change', function () {
                state[box.dataset.col] = box.checked;
                save(state);
                apply(state);
            });
        });
        var reset = document.querySelector('.column-picker .column-reset');
        if (reset) {
            reset.addEventListener('click', function () { state = {}; save(state); apply(state); });
        }
      }

      if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', init);
      } else {
          init();
      }
    })();
</script>
