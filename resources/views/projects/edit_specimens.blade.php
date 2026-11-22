@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{!! get_dialogs('Edit Project', $active_language) !!}</h1>
    <form method="post" action="update_specimens_submit" enctype="multipart/form-data" id="update_specimens">
        <div class="form-group">
            {{ get_dialogs_simple('project_title_'.$project->id, $active_language) }}
        </div>
        <div class="form-group">
            <?php
            $project_specimens_ids = '';
            foreach ($project_specimens as $project_specimen) {
                $project_specimens_ids .= $project_specimen->specimen_id;
            }
            ?>
            @csrf
            <label for="specimens" class="col-form-label">{!! get_dialogs('Specimens:', $active_language) !!}</label>
            <textarea class="form-control" id="specimens" name="specimens">{{$project_specimens_ids}}</textarea>

        </div>
        <input type="hidden" name="project_id" id="project_id" value="">

        <button type="submit" class="btn btn-primary" form="update_specimens">{!! get_dialogs('Submit', $active_language) !!}</button>
    </form>
</div>
@endsection