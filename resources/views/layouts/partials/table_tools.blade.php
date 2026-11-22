{{--
    A search box (and, optionally, sorting) for a table, done in the page: it filters what is already listed.

    $tableId      id of the table
    $rowSelector  the rows to filter and sort (default: every body row)
    $placeholder  hint inside the box
    $azColumn     (optional) index of the column an "A-Z" button sorts by; pressing it again goes back to the page's own order
    Column headers marked data-sort="text|number" become clickable to sort by that column.

    The search ignores case and accents and needs every word typed to be somewhere in the row.
--}}
@php
    $rowSelector = $rowSelector ?? 'tbody tr';
    $hasAz = isset($azColumn);
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-2 table-tools" id="tools-{{ $tableId }}">
    <div class="input-group input-group-sm" style="max-width: 22rem">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control table-search" placeholder="{{ $placeholder ?? get_dialogs_simple('Search...', $active_language) }}" aria-label="{{ $placeholder ?? get_dialogs_simple('Search...', $active_language) }}">
    </div>
    @if($hasAz)
        <button type="button" class="btn btn-outline-secondary btn-sm table-az" aria-pressed="false">A&ndash;Z</button>
    @endif
    <span class="small text-muted table-count"></span>
</div>
<script>
    // this box sits above its table, so the script waits for the page to be there
    (function () {
        function init() {
            var table = document.getElementById({!! json_encode($tableId) !!});
            if (!table) { return; }
            var rowSelector = {!! json_encode($rowSelector) !!};
            var azColumn = {!! json_encode($hasAz ? (int) $azColumn : null) !!};
            var box = document.getElementById({!! json_encode('tools-' . $tableId) !!});
            var input = box.querySelector('.table-search');
            var count = box.querySelector('.table-count');
            var azButton = box.querySelector('.table-az');

            function rows() { return Array.prototype.slice.call(table.querySelectorAll(rowSelector)); }
            // the page's own order, kept only while a sorted view is on (the rows may be dragged around before that)
            var pageOrder = null;
            function announce(original) {
                table.dispatchEvent(new CustomEvent('tabletools:order', {detail: {original: original}}));
            }

            function normalize(text) {
                return String(text).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            }

            // ---- search
            function filter() {
                var words = normalize(input.value).split(/\s+/).filter(Boolean);
                var shown = 0, all = rows();
                all.forEach(function (row) {
                    var text = normalize(row.textContent);
                    var match = words.every(function (w) { return text.indexOf(w) !== -1; });
                    row.classList.toggle('d-none', !match);
                    if (match) { shown++; }
                });
                count.textContent = words.length ? shown + ' / ' + all.length : '';
            }
            input.addEventListener('input', filter);

            // ---- sorting
            function cellText(row, index) {
                var cell = row.cells[index];
                return cell ? cell.textContent.trim() : '';
            }
            function placeRows(sorted) {
                var parent = sorted[0] ? sorted[0].parentNode : null;
                if (!parent) { return; }
                sorted.forEach(function (row) { parent.appendChild(row); });
            }
            function compare(index, type, direction) {
                return function (a, b) {
                    var x = cellText(a, index), y = cellText(b, index), result;
                    if (type === 'number') {
                        var nx = parseFloat((x.match(/-?\d+(\.\d+)?/) || [''])[0]), ny = parseFloat((y.match(/-?\d+(\.\d+)?/) || [''])[0]);
                        nx = isNaN(nx) ? -Infinity : nx; ny = isNaN(ny) ? -Infinity : ny;
                        result = nx === ny ? 0 : (nx < ny ? -1 : 1);
                    } else {
                        result = x.localeCompare(y, undefined, {sensitivity: 'base', numeric: true});
                    }
                    return direction * result;
                };
            }
            function clearArrows() {
                table.querySelectorAll('thead th .sort-arrow').forEach(function (s) { s.remove(); });
            }

            table.querySelectorAll('thead th[data-sort]').forEach(function (th) {
                th.style.cursor = 'pointer';
                th.setAttribute('title', {!! json_encode(get_dialogs_simple('Click to sort', $active_language)) !!});
                var direction = 1;
                th.addEventListener('click', function () {
                    placeRows(rows().sort(compare(th.cellIndex, th.getAttribute('data-sort'), direction)));
                    announce(false);
                    clearArrows();
                    var arrow = document.createElement('span');
                    arrow.className = 'sort-arrow ms-1';
                    arrow.textContent = direction === 1 ? '▲' : '▼';
                    th.appendChild(arrow);
                    if (azButton) { azButton.classList.remove('active'); azButton.setAttribute('aria-pressed', 'false'); }
                    direction = -direction;
                });
            });

            if (azButton) {
                azButton.addEventListener('click', function () {
                    var on = azButton.getAttribute('aria-pressed') !== 'true';
                    azButton.setAttribute('aria-pressed', on ? 'true' : 'false');
                    azButton.classList.toggle('active', on);
                    clearArrows();
                    if (on) {
                        pageOrder = pageOrder || rows();
                        placeRows(rows().sort(compare(azColumn, 'text', 1)));
                    } else if (pageOrder) {
                        placeRows(pageOrder);
                        pageOrder = null;
                    }
                    announce(!on);
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
