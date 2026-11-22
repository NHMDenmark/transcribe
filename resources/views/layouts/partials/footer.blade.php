{{-- The footer: blocks of text and lists of links, side by side. Nothing is drawn when there are none. --}}
@php
    $canEditFooter = auth()->check() && \Illuminate\Support\Facades\Gate::allows('manage-content');
@endphp
@if(!empty($footer_blocks) || $canEditFooter)
<footer class="site-footer border-top bg-light mt-4 py-4">
    <div class="container">
        <div class="row gy-3">
            @foreach($footer_blocks ?? [] as $block)
                <div class="col-12 col-md-{{ $block['width'] }}">
                    @if($block['title'] !== '')
                        <h6 class="text-uppercase text-muted">{{ $block['title'] }}{!! dialog_edit_trigger('footer_title_' . $block['id']) !!}</h6>
                    @endif
                    @if($block['type'] === 'text')
                        <div class="small footer-body">{!! $block['body'] !!}{!! dialog_edit_trigger('footer_body_' . $block['id'], true) !!}</div>
                    @else
                        <ul class="list-unstyled small mb-0">
                            @foreach($block['links'] as $link)
                                <li><a href="{{ $link['url'] }}" @if($link['external']) target="_blank" rel="noopener" @endif>{{ $link['label'] }}{!! dialog_edit_trigger('navigation_name_' . $link['id']) !!}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
        @if($canEditFooter)
            <div class="text-end mt-2"><a class="small text-muted" href="{{ url('footer') }}"><i class="bi bi-pencil"></i> {!! get_dialogs('Edit the footer', $active_language) !!}</a></div>
        @endif
    </div>
</footer>
@endif
