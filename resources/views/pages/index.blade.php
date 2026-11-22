@extends('layouts.app')

@section('content')

<div class="container">
  <h1>{!!get_dialogs('platform_title', $active_language)!!}</h1>
  <div class="col-md-12 text-left text-secondary mb-5">
    <?php echo get_dialogs_html('platform_message', $active_language); ?>
  </div>
  <div class="row g-0">
    <div class="col-md-8 pe-md-3 pb-3">
      <h3>{!!get_dialogs('projects_active', $active_language)!!}</h3>
      @foreach($projects as $project)

      @include('projects.projectbox')

      @endforeach
    </div>
    <div class="col-md-4 ps-md-3 mb-5">
      <div class="col-md-12 pb-5">
        <h3>{!!get_dialogs('best_contributors', $active_language)!!}</h3>
        <ul class="list-group">
          <?php
          foreach ($top_contributors as $top_contributor) {
            $sum = $top_contributor->validated + $top_contributor->rejected + $top_contributor->submitted;
          ?>
            <li class="list-group-item">
              <span class="float-end badge rounded-pill text-bg-secondary">{!!$sum!!}</span>
              {!! display_avatar($top_contributor, 24) !!} {{ display_name($top_contributor) }}
            </li>
          <?php
          }
          ?>

        </ul>
      </div>

      <div class="col-md-12 pb-5">
        <h3>{!!get_dialogs('best_contributors_week', $active_language)!!}</h3>
        <ul class="list-group">
          <?php
          foreach ($top_contributors_week as $top_contributor) {
          ?>
            <li class="list-group-item">
              <span class="float-end badge rounded-pill text-bg-secondary">{!!$top_contributor->total!!}</span>
              {!! display_avatar($top_contributor->user, 24) !!} {{ display_name($top_contributor->user) }}
            </li>
          <?php
          }
          ?>

        </ul>
      </div>



      <div class="col-md-12 pb-2">
        <div class="row">
          <h3 class="col-8">{!!get_dialogs('recent_comments', $active_language)!!}</h3>
          <div class="col-4"><a class="float-end" href="<?php echo url('/comments'); ?>">{!!get_dialogs('all_comments', $active_language)!!}</a></div>
        </div>
      </div>
      <ul class="list-group">
        <?php
        foreach ($recent_comments as $comment) {
          //print_r($comment);
          $project_id = $comment->project_id ?? null;
          $specimen_id = $comment->specimen_id ?? null;
          if ($project_id != null) {
        ?>
            <li class="list-group-item">
              <?php
              $thumb = $comment->thumbnail;
              $msg = preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a href="\1" target="_blank">\1</a>', $comment->value);
              ?>
              <div class="row">
                <div class="col-4">
                  <a href="<?php echo url('projects/' . $project_id . '/' . $specimen_id . '/' . $comment->field->name); ?>">
                    <img class="mr-3" src="{!!$thumb!!}" alt="{!!$specimen_id!!}" height="75px">
                  </a>
                </div>
                <div class="col-8">
                  <p class="m-0">{!! display_avatar($comment->user, 22) !!} <strong>{{ display_name($comment->user) }}</strong></p>
                  <?php echo $msg; ?><br>
                  <small class="text-muted">{!!substr($comment->created_at, 0, -3)!!}</small>
                </div>
              </div>
            </li>
        <?php
          }
        }
        ?>

      </ul>
    </div>





  </div>
</div>
</div>
@endsection