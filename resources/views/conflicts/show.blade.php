@extends('layouts.app')

{{-- Same full-viewport image + panel layout as the transcription page. --}}
@section('main_class', '')

@section('content')

<article id="one" class="post style1 alt">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.3.1/dist/leaflet.css" />
  <script type="text/javascript" src="https://unpkg.com/leaflet@1.3.1/dist/leaflet.js"></script>
  <script src="{{URL::asset('js/imgViewer2.js')}}"></script>

  <div class="half-screen-image col-12">
    @if(count($images))
      <img id="image1" src="{{ $images[0] }}" alt="" />
      @if(count($images) >= 2)
        <div style="width: 60px; top:10px; right: 20px; position: absolute; z-index: 1000" id="imageselector">
          @foreach($images as $image)
            <a onclick="$('#image1').imgViewer2('destroy');$('#image1').attr('src', '{{ $image }}'); $('#image1').imgViewer2();" href="#">
              <div style="margin-top: 10px; width: 50px; height: 50px; float:right; background-image: url({{ $image }}); background-size: 50px; background-position: center; border:2px solid white">&nbsp;</div>
            </a>
          @endforeach
        </div>
      @endif
    @else
      <div class="p-4 text-muted">{!! get_dialogs('No image for this specimen', $active_language) !!}</div>
    @endif
  </div>

  @if(count($images))
  <script>
    (function($) {
      $("#image1").imgViewer2();
    })(jQuery);
  </script>
  @endif

  <div class="half-screen col-12">

    <div class="mt-3 d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h3 class="mb-1">{!! get_dialogs('Conflict with Specify', $active_language) !!}</h3>
        <p class="text-muted mb-1">
          {!! get_dialogs('Project', $active_language) !!}: {{ $project->name ?? '—' }}
          &middot; {!! get_dialogs('Specimen', $active_language) !!}:
          <a href="{{ url('projects/' . ($record->project_id ?? 0) . '/' . $record->specimen_id) }}">{{ $record->specimen_id }}</a>
          &middot; {!! get_dialogs('Field', $active_language) !!}: {{ $record->field->name ?? $record->field_id }}
          &middot; #{{ $record->id }}
        </p>
      </div>
      @if($rootUrl)
        <a href="{{ $rootUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-box-arrow-up-right"></i> {!! get_dialogs('Open the record in Specify', $active_language) !!}
        </a>
      @endif
    </div>

    @if((int) $record->status !== 4)
      <div class="alert alert-info mt-3">{!! get_dialogs('This record is not in conflict (any more).', $active_language) !!}</div>
      <a href="{{ url('/validated') }}" class="btn btn-outline-secondary">{!! get_dialogs('Back to validated values', $active_language) !!}</a>
    @else
      @if(!$specifyLoaded)
        <div class="alert alert-warning mt-3">{!! get_dialogs('Could not read the record from Specify right now, so its current values are not shown.', $active_language) !!}</div>
      @else
        <p class="small text-muted mt-3 mb-2">{!! get_dialogs('What Specify holds is read live, as it is right now. Parts marked "Different" were left untouched by the sync.', $active_language) !!}</p>
      @endif

      <div class="card mb-3">
        <div class="card-body p-0 table-responsive">
          <table class="table mb-0 text-break align-middle">
            <thead>
              <tr>
                <th style="width:24%">{!! get_dialogs('Part', $active_language) !!}</th>
                <th style="width:38%">{!! get_dialogs('Validated value', $active_language) !!}</th>
                <th style="width:38%">{!! get_dialogs('In Specify', $active_language) !!}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $row)
                <tr @if($row['decision'] === 'different') class="table-warning" @endif>
                  <td class="text-muted small">{{ $row['label'] ?? '' }}</td>
                  <td>{{ $row['validated'] !== '' ? $row['validated'] : '—' }}</td>
                  <td>
                    @if($row['decision'] === 'equal')
                      {{ $row['specify'] }} <i class="bi bi-check text-success" title="{{ get_dialogs_simple('Same', $active_language) }}"></i>
                    @else
                      {{ $row['specify'] ?? '—' }}
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <td></td>
                <td>
                  <form method="post" action="{{ url('/conflicts/' . $record->id . '/overwrite') }}"
                        onsubmit="return confirm({{ json_encode(get_dialogs_simple('Overwrite the value in Specify with the validated one, now?', $active_language)) }});">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100">{!! get_dialogs('Overwrite Specify with this value', $active_language) !!}</button>
                  </form>
                </td>
                <td>
                  <form method="post" action="{{ url('/conflicts/' . $record->id . '/keep') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary w-100">{!! get_dialogs('Keep the value in Specify', $active_language) !!}</button>
                  </form>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <a href="{{ url('/validated?status=4') }}" class="btn btn-link ps-0 mb-4">{!! get_dialogs('Back to conflicts', $active_language) !!}</a>
    @endif

  </div>
</article>
@endsection
