@if(Auth::check() && Gate::allows('edit-dialogs'))
{{-- Shared dialog-text edit modal — included once in layouts.app, available
     wherever get_dialogs()/get_dialogs_html() render an edit-trigger icon.
     One shared modal (not one per dialog) avoids duplicate markup, and
     living here — outside every page's own <form> — avoids the nested-form
     breakage this feature originally had on the specimen transcription
     form. Values for the clicked dialog are fetched on demand via AJAX
     (GET /dialog_values/{identifier}) rather than preloaded for every
     dialog on the page. --}}
<style>
    /* Edit triggers are opt-in via the navbar toggle (#dialogEditModeToggle) -
       on every page by default they'd be a distracting amount of clutter for
       an admin who isn't actively editing text right now. */
    .dialog-edit-trigger { display: none; cursor: pointer; color: var(--bs-primary, #054791); margin-left: .3rem; }
    .dialog-edit-trigger:hover, .dialog-edit-trigger:focus { color: #000; }
    body.dialog-edit-mode .dialog-edit-trigger { display: inline-block; line-height: 1; vertical-align: baseline; }
    .btn .dialog-edit-trigger { color: inherit; opacity: .8; }

    /* In the navbar an inline pencil would widen every item enough to wrap the bar onto a second line,
       so there it is a small chip laid over the item without taking room:
       - in a menu (dropdown items and headings): inside the item, at the right, centred on its line;
       - on a top-level item: at its top right corner, clear of the dropdown caret. */
    body.dialog-edit-mode .navbar .nav-link,
    body.dialog-edit-mode .navbar .dropdown-item,
    body.dialog-edit-mode .navbar .dropdown-header {
        position: relative;
    }
    body.dialog-edit-mode .navbar .dialog-edit-trigger {
        position: absolute;
        margin: 0;
        font-size: 10px;
        line-height: 1;
        background: #fff;
        border-radius: 50%;
        padding: 1px;
        box-shadow: 0 0 0 1px rgba(0,0,0,.1);
        z-index: 1;
    }
    body.dialog-edit-mode .navbar .nav-link .dialog-edit-trigger { top: 3px; right: -4px; }
    body.dialog-edit-mode .navbar .dropdown-menu .dropdown-item { padding-right: 1.75rem; }
    body.dialog-edit-mode .navbar .dropdown-menu .dialog-edit-trigger { top: 50%; right: .35rem; transform: translateY(-50%); font-size: 11px; }
</style>
<div class="modal fade" id="dialogEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" id="dialogEditModalDialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{!! get_dialogs('Edit text:', $active_language) !!} <code id="dialogEditIdentifier"></code></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="{{ URL('editdialog_inline') }}" id="dialogEditForm">
                    @csrf
                    <input type="hidden" name="identifier" id="dialogEditIdentifierInput">
                    @foreach($languages as $language)
                    <div class="mb-2">
                        <label class="col-form-label" for="dialogEditField_{{ $language->iso2 }}">
                            <img width="20" src="{{ url('/') }}/flags/4x3/{{ $language->iso2 }}.svg" alt=""> {{ $language->iso2 }}
                        </label>
                        {{-- Left as a plain textarea (no "ckeditor" class) so it is NOT
                             auto-replaced on page load — get_dialogs() dialogs stay plain
                             text; get_dialogs_html() ones get CKEDITOR.replace()'d on open
                             (see script below), matching how each is actually rendered. --}}
                        <textarea class="form-control" name="{{ $language->iso2 }}" id="dialogEditField_{{ $language->iso2 }}" rows="3"></textarea>
                    </div>
                    @endforeach
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
                <button type="submit" class="btn btn-primary" form="dialogEditForm">{!! get_dialogs('Save', $active_language) !!}</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function($) {
// Some pages (e.g. /fields) call jQuery.noConflict(), freeing the global
// "$" — this modal is included on every page via the layout, so it can't
// assume "$" means jQuery globally. Binding it as this IIFE's own parameter
// keeps it working regardless of what any other page does with "$".
var dialogEditLanguages = @json($languages->pluck('iso2'));

function dialogEditFieldId(iso2) {
    return 'dialogEditField_' + iso2;
}

// Some plain (get_dialogs()) dialogs were saved with markup in the past —
// e.g. edited once through a rich editor elsewhere on the site — even though
// the display side already strips it (strip_tags() in get_dialogs()). Strip
// it here too so the plain editor shows the same clean text the page does,
// instead of "<p>Read more</p>".
function dialogEditStripTags(html) {
    var el = document.createElement('div');
    el.innerHTML = html;
    return el.textContent || '';
}

function dialogEditSetValue(iso2, text, isHtml) {
    var id = dialogEditFieldId(iso2);
    if (CKEDITOR.instances[id]) {
        CKEDITOR.instances[id].setData(text);
    } else {
        $('#' + id).val(isHtml ? text : dialogEditStripTags(text));
    }
}

function openDialogEditModal(e) {
    // preventDefault/stopPropagation in case the trigger sits inside its own
    // clickable ancestor (e.g. a Bootstrap accordion header) — this stops
    // the <a href="#"> from jumping the page, though a same-click Bootstrap
    // data-api toggle on that ancestor can still fire (Bootstrap's own
    // delegated listener runs independently of ours); harmless, just a minor
    // "the card also expands" side effect where that applies.
    e.preventDefault();
    e.stopPropagation();

    var identifier = this.dataset.dialog;
    var isHtml = this.dataset.html === '1';

    $('#dialogEditIdentifier').text(identifier);
    $('#dialogEditIdentifierInput').val(identifier);
    // CKEditor (rich/HTML) fields need room to be usable — plain text ones
    // don't, so only widen the modal when it's actually showing CKEditor.
    $('#dialogEditModalDialog').toggleClass('modal-lg', isHtml);

    dialogEditLanguages.forEach(function(iso2) {
        var id = dialogEditFieldId(iso2);
        var instance = CKEDITOR.instances[id];

        if (isHtml && !instance) {
            CKEDITOR.replace(id);
        } else if (!isHtml && instance) {
            // switching this field back to plain text — drop the rich editor
            // instance so the underlying textarea (and its plain value) is
            // what actually gets submitted.
            instance.destroy();
        }

        dialogEditSetValue(iso2, '', isHtml);
    });

    $.get("{{ url('dialog_values') }}/" + encodeURIComponent(identifier), function(values) {
        $.each(values, function(iso2, text) {
            dialogEditSetValue(iso2, text, isHtml);
        });
    });

    $('#dialogEditModal').modal('show');
}

// Triggers are rendered by dialog_edit_trigger() throughout the page, inside links, buttons and menus. One listener
// on the document, in the capture phase, serves every one of them (also those added later): it runs before the
// link, button or menu around the pencil sees the click, and stops it there.
document.addEventListener('click', function(e) {
    var trigger = e.target.closest ? e.target.closest('.dialog-edit-trigger') : null;
    if (trigger) {
        openDialogEditModal.call(trigger, e);
    }
}, true);
document.addEventListener('keydown', function(e) {
    var trigger = e.target.closest ? e.target.closest('.dialog-edit-trigger') : null;
    if (trigger && (e.key === 'Enter' || e.key === ' ')) {
        openDialogEditModal.call(trigger, e);
    }
}, true);

// Edit-mode toggle (navbar pencil button): shows/hides every
// .dialog-edit-trigger on the page via the "dialog-edit-mode" class on
// <body> (see <style> above). Persisted per-browser via localStorage —
// this is a per-admin display preference, not app data, so it doesn't need
// a server round-trip. Defaults to off: an admin who isn't actively editing
// text shouldn't see pencils scattered across every page.
$(function() {
    var STORAGE_KEY = 'dialogEditMode';
    var $toggle = $('#dialogEditModeToggle');
    var enabled = false;

    try {
        enabled = localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
        // localStorage unavailable (private browsing, etc.) — fall back to off.
    }

    function apply() {
        $('body').toggleClass('dialog-edit-mode', enabled);
        $toggle.toggleClass('btn-primary', enabled).toggleClass('btn-outline-secondary', !enabled);
    }

    apply();

    $toggle.on('click', function() {
        enabled = !enabled;
        try {
            localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
        } catch (e) {
            // ignore — worst case the preference doesn't persist this session
        }
        apply();
    });
});
})(jQuery);
</script>
@endif
