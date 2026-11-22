@extends('layouts.app')

@section('content')
<div class="container">

  <h1>{!!get_dialogs('recent_comments', $active_language)!!}</h1>
  
  @if(count($groups) > 0)
  <?php
  foreach ($groups as $group) {

    $comment_images = $group['comments'][0]->specimen->images ?? null;

    $images = explode('|', $comment_images);

    $thumb = $images[0];


  ?>

    <div class="d-flex mb-3">
      <div class="flex-shrink-0">
        <a href="<?php echo url('projects/0/' . $group['comments'][0]->specimen_id . '/' . $group['comments'][0]->field->name); ?>">
          <img style="margin: 5pt 0 10pt 0;" class="mr-3" src="{{$thumb}}" alt="{{$group['comments'][0]->specimen_id}}" height="100px">
        </a>
      </div>
      <div class="flex-grow-1 ms-3">
        <a href="<?php echo url('projects/0/' . $group['comments'][0]->specimen_id . '/' . $group['comments'][0]->field->name); ?>">
          <strong>{{$group['comments'][0]->specimen_id}} - {{$group['comments'][0]->field->name}}</strong>
        </a>
        <ul class="list-group">

          <?php
          foreach ($group['comments'] as $comment) {
          ?>
            <li class="list-group-item">
              <?php
              $msg = preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a href="\1" target="_blank">\1</a>', $comment->value);
              ?>
              <div class="d-flex">
                <div class="flex-grow-1 ms-3">
                  <p class="m-0">{!! display_avatar($comment->user, 22) !!} <strong>{{ display_name($comment->user) }}</strong></p>
                  <?php echo $msg; ?><br>
                  <small class="text-muted">{!!substr($comment->created_at, 0, -3)!!}</small>
                </div>
              </div>

            </li>
          <?php
          }
          ?>

        </ul>
      </div>
    </div>

  <?php
  }
  ?>
  {{$groups->links()}}
  @else
  <p>{!! get_dialogs('No comments found', $active_language) !!}</p>
  @endif
</div>

@endsection