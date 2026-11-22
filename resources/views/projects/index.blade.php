@extends('layouts.app')

@section('content')
<div class="container">

  <?php
  if (Auth::check()) {
    if (Gate::allows('manage-projects')) {
  ?>
      <div class="float-end">
        <a href="<?php echo url('projects/create'); ?>" role="button" class="btn btn-primary btn-sm" title="<?php echo get_dialogs_simple('Add a new project', $active_language); ?>">
          <i class="bi bi-plus-lg"></i>
        </a>
      </div>

      @include('projects.edit_modal')
  <?php
    }
  }
  ?>
  <h1><?php echo get_dialogs('Projects_page_title', $active_language); ?></h1>

  @if(count($projects) > 0)
  @foreach($projects as $project)

  @include('projects.projectbox')

  @endforeach
  {{$projects->links()}}
  @else
  <p><?php echo get_dialogs('no_projects_found', $active_language); ?></p>
  @endif
</div>




@endsection