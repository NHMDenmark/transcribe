<style>
    .help-hint { position: relative; display: inline-block; margin-left: .35rem; font-weight: 400; text-transform: none; letter-spacing: 0; }
    .help-hint-icon { cursor: pointer; color: var(--bs-secondary-color, #6c757d); line-height: 1; }
    .help-hint-icon:hover, .help-hint-icon:focus, .help-hint.open .help-hint-icon { color: var(--bs-primary, #054791); outline: none; }
    .help-hint-body {
        display: none; position: absolute; left: 0; top: 1.5rem; z-index: 1080;
        width: 21rem; max-width: 78vw; padding: .6rem .75rem;
        background: #fff; color: #212529; border: 1px solid #ced4da; border-radius: .5rem;
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
        font-size: .85rem; line-height: 1.4; text-align: left; white-space: normal;
    }
    .help-hint.open .help-hint-body { display: block; }
    .help-hint.end .help-hint-body { left: auto; right: 0; }
    .help-hint-more { display: block; margin-top: .4rem; font-size: .8rem; }
</style>
<script>
(function () {
    if (window.__helpHints) { return; }
    window.__helpHints = true;

    function closeAll(except) {
        document.querySelectorAll('.help-hint.open').forEach(function (hint) {
            if (hint !== except) {
                hint.classList.remove('open');
                hint.querySelector('.help-hint-icon').setAttribute('aria-expanded', 'false');
            }
        });
    }

    // open towards the left when the right edge of the screen would cut it
    function place(hint) {
        hint.classList.remove('end');
        var body = hint.querySelector('.help-hint-body');
        if (body.getBoundingClientRect().right > window.innerWidth - 8) {
            hint.classList.add('end');
        }
    }

    function toggle(icon) {
        var hint = icon.parentNode;
        var open = !hint.classList.contains('open');
        closeAll(hint);
        hint.classList.toggle('open', open);
        icon.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { place(hint); }
    }

    // capture phase: the hint sits inside labels, buttons and menus, and must not activate them
    document.addEventListener('click', function (e) {
        var icon = e.target.closest ? e.target.closest('.help-hint-icon') : null;
        if (icon) {
            e.preventDefault();
            e.stopPropagation();
            toggle(icon);
        } else if (!(e.target.closest && e.target.closest('.help-hint-body'))) {
            closeAll(null);
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        var icon = e.target.closest ? e.target.closest('.help-hint-icon') : null;
        if (icon && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            toggle(icon);
        } else if (e.key === 'Escape') {
            closeAll(null);
        }
    }, true);
})();
</script>
