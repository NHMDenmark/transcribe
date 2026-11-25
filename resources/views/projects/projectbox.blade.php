<div class="card p-3 mb-3">
    <div class="row mb-3">
        <div class="col-md-4 col-sm-4">
            <img style="width:100%" src="{{URL('cover_images/'.$project->cover_image)}}">
        </div>
        <div class="col-md-8 col-sm-8">
            <?php
            if (Auth::id() and Gate::allows('manage-projects', $project)) {
            ?>
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="album py-2">
                            <div class="col float-end">
                                @include('projects.controls')
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            }
            ?>
            @if (Auth::id() and Gate::allows('manage-projects', $project))
            @include('projects.ownership')
            @endif
            <h3><?php echo get_dialogs('project_title_' . $project->id, $active_language); ?></h3>
            <p><?php echo get_dialogs_html('project_abstract_' . $project->id, $active_language); ?></p>
            <p><a href="{{URL('/projects/'.$project->id)}}"><?php echo get_dialogs('read_more', $active_language); ?></a></p>
            <p>{{$project->specimens}} <?php echo get_dialogs('Specimens', $active_language); ?></p>
            <div class="progress mb-3" role="progressbar" aria-label="Project Progress" aria-valuenow="<?php echo round($project->completed_percent, 2) . ' %'; ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width:<?php echo round($project->completed_percent, 2) . '%'; ?>"><?php echo round($project->completed_percent, 2) . '%'; ?></div>
            </div>

            <a class="btn btn-primary" href="{{URL('/projects/'.$project->id.'/start')}}" role="button"><?php echo get_dialogs('participate', $active_language); ?></a>
        </div>
    </div>
</div>