@extends( 'layouts.app' )

{{-- This page's .post layout fills the full viewport height below the navbar
     on purpose (image + form side by side) — opt out of the default
     page-content top/bottom padding every other page gets. --}}
@section('main_class', '')

@section( 'content' )

<!-- One -->
<article id="one" class="post style1 alt">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.3.1/dist/leaflet.css" />
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css" media="screen">

  <script type="text/javascript" src="https://unpkg.com/leaflet@1.3.1/dist/leaflet.js"></script>
  <script src="{{URL::asset('js/imgViewer2.js')}}"></script>

  <link rel="stylesheet" href="{{URL::asset('js/typeahead/typeahead.css')}}" />
  <script src="{{URL::asset('js/typeahead/typeahead.js')}}"></script>

  <div class="half-screen-image col-12">

    <img id="image1" src="{{$images[0]}}" alt="" />

    <?php
    if (count($images) >= 2) {
    ?>
      <div style="width: 60px; top:10px; right: 20px; position: absolute; z-index: 1000" id="imageselector">
        <?php
        foreach ($images as $image) {
        ?>
          <a onclick="$('#image1').imgViewer2('destroy');$('#image1').attr('src', '{{$image}}'); $('#image1').imgViewer2();" href="#">
            <div style="margin-top: 10px; width: 50px; height: 50px; float:right; background-image: url({{$image}}); background-size: 50px; background-position: center;  border:2px solid white">&nbsp;</div>
          </a>
        <?php
        }
        ?>
      </div>
    <?php
    }
    ?>

  </div>

  <script>
    (function($) {
      $("#image1").imgViewer2();
    })(jQuery);



    function toogle_active(field_id) {
      checkbox1 = document.getElementById('no_information_' + field_id);
      checkbox2 = document.getElementById('indecipherable_' + field_id);
      var el = document.getElementById('field_' + field_id);
      all = el.querySelectorAll('input,textarea,select,a');
      addbutton = document.getElementById('add' + field_id);
      if ((checkbox1.checked == 0) && (checkbox2.checked == 0)) {
        if (addbutton) {
          addbutton.disabled = false;
        }
        el.style.opacity = 1;
        for (i = 0; i < all.length; i++) {
          all[i].disabled = false;
        }
      } else {
        if (addbutton) {
          addbutton.disabled = true;
        }
        el.style.opacity = 0.4;
        for (i = 0; i < all.length; i++) {
          all[i].disabled = true;
        }
        if (checkbox1.checked == 1) {
          checkbox2.checked = false;
        } else {
          checkbox1.checked = false;
        }
      }
    }

    function toogle_keeporchange(field_id = 1) {
      var keepEl = document.getElementById('keep_' + field_id);
      var changeEl = document.getElementById('change_' + field_id);

      // Lines without an already-validated value (e.g. a freshly added multi-value
      // line) have no keep/change choice rendered at all — nothing to toggle, so
      // just leave them fully interactive instead of throwing on the missing radios.
      if (!keepEl && !changeEl) {
        return;
      }

      var radiobutton_value = (keepEl && keepEl.checked) ? 'keep' : 'change';

      var el = document.getElementById('field_' + field_id);
      var all = el.querySelectorAll('input,textarea,select,a');
      if (radiobutton_value == 'keep') {
        el.style.opacity = 0.4;
        for (var i = 0; i < all.length; i++) {
          all[i].disabled = true;
        }
        document.getElementById('no_information_' + field_id).disabled = true;
        document.getElementById('indecipherable_' + field_id).disabled = true;
      } else {
        el.style.opacity = 1;
        for (var i = 0; i < all.length; i++) {
          all[i].disabled = false;
        }
        document.getElementById('no_information_' + field_id).disabled = false;
        document.getElementById('indecipherable_' + field_id).disabled = false;
      }
    }
  </script>

  <div class="half-screen col-12">


    <?php
    if ($project->id != 0) {
    ?><div class="mt-3">
        <h3>
          {!!get_dialogs('Project', $active_language)!!}: {!!get_dialogs('project_title_'.$project->id, $active_language)!!}
        </h3>
      </div>
      <!--
      <div class="progress">
        <div class="progress-bar" role="progressbar" aria-valuenow="<?php echo $project->completed_percent; ?>"
        aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $project->completed_percent; ?>%">
          <?php echo round($project->completed_percent, 2) . ' %'; ?>
        </div>
      </div>
      -->
    <?php
    } else {
      echo get_dialogs_html('No_project_selected', $active_language);
    }
    ?>

    <?php
    //	$logged = 'autenticado';
    if (Auth::guest()) {
    ?>
      <div class="alert alert-danger">
        {!! get_dialogs_html('not_logged_alert', $active_language) !!}
      </div>
    <?php
    }
    ?>

    <div class="row">
      <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="mb-0">{{$specimen->specimen_id}}</h3>
        <?php $field_view = $field_view ?? 'all'; ?>
        @auth
        <form method="post" action="{{ url('/set_field_view') }}" class="btn-group btn-group-sm my-2" role="group">
          @csrf
          {{-- bare specimen URL; show() resolves it to the right view for the chosen preference --}}
          <input type="hidden" name="return" value="{{ '/projects/'.$project->id.'/'.$specimen->specimen_id }}">

          <button type="submit" name="field_view" value="single" class="btn btn-outline-secondary {{ $field_view === 'single' ? 'active' : '' }}">
            {!! get_dialogs('one field at a time', $active_language) !!}
          </button>
          <button type="submit" name="field_view" value="all" class="btn btn-outline-secondary {{ $field_view === 'all' ? 'active' : '' }}">
            {!! get_dialogs('all fields on one screen', $active_language) !!}
          </button>
        </form>
        @endauth
      </div>
    </div>

    <row>

      <?php

      if (session('different_values')) {
        $different_values = session('different_values');
        //print_r($different_values);
        $equal_values = session('equal_values');
        $submitted_values = session('submitted_values');
        $conflict_groups = 0;
        foreach ($different_values as $groups) {
          $conflict_groups += count($groups);
        }

      ?>
        <div class="col-12" id="conflict-groups" data-next="{{ session('next_form') }}" data-url="{{ route('choose_value') }}" data-token="{{ csrf_token() }}" data-specimen="{{ $specimen->specimen_id }}" data-project="{{ $project->id }}">

          <?php
          foreach ($different_values as $field => $array1) {
            foreach ($array1 as $position => $array2) {
          ?>
              <div class="alert alert-warning conflict-group" data-field="{{ $field }}" data-position="{{ $position }}">
                <strong><?php echo get_dialogs_simple('fields_name_' . $field, $active_language); ?></strong><br>
                <u>{!! get_dialogs_html('line', $active_language) !!} <?php echo $position; ?></u><br>
                <div class="conflict-values">
                <strong>{!! get_dialogs_html('your_answer', $active_language) !!}:</strong><br>
                <ul class="list-group">
                  <li class="list-group-item d-flex justify-content-between align-items-center gap-2" style="white-space: pre-wrap"><span class="value-text"><?php echo e($submitted_values[$field][$position]); ?></span>
                    <button type="button" class="btn btn-sm btn-success choose-value text-nowrap" data-mine="1">{!! get_dialogs('Keep this value', $active_language) !!}</button>
                  </li>
                </ul><br>
                <?php
                if (isset($equal_values[$field][$position])) {
                ?>
                  {!! get_dialogs_html('same_value_form_other_users', $active_language) !!}:
                  <ul class="list-group">
                    <li class="list-group-item list-group-item-success" style="white-space: pre-wrap"><?php echo e($submitted_values[$field][$position]); ?><span class="float-end badge rounded-pill text-bg-secondary"><?php echo $equal_values[$field][$position]; ?></span></li>
                  </ul><br>
                <?php
                }
                ?>
                {!! get_dialogs_html('different_values_form_other_users', $active_language) !!}:
                <ul class="list-group">
                  <?php
                  foreach ($array2 as $key => $count) {
                  ?>
                    <li class="list-group-item list-group-item-danger d-flex justify-content-between align-items-center gap-2" style="white-space: pre-wrap"><span class="value-text"><?php echo e($key); ?></span>
                      <span class="d-flex align-items-center gap-2 text-nowrap">
                        <span class="badge rounded-pill text-bg-secondary"><?php echo $count; ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary choose-value" data-value="<?php echo e($key); ?>">{!! get_dialogs('Use this value', $active_language) !!}</button>
                      </span>
                    </li>
                  <?php
                  }
                  ?>
                </ul><br>
                </div>
              <?php
            }
              ?>
              </div>

            <?php
          }

            ?>
            {!! get_dialogs_html('return_to_previous_form', $active_language) !!} <button class="btn btn-warning" role="button" onclick="javascript:window.location.reload();">{!! get_dialogs_html('previous_form', $active_language) !!}</button>
            <?php if ($conflict_groups > 1) { ?>
              <br><br>
              <button type="button" class="btn btn-success" id="keep-all-values">{!! get_dialogs('Keep all my values and continue', $active_language) !!}</button>
            <?php } ?>
        </div>
      <?php
      }
      ?>
      <div class="col-12" <?php if (session('different_values')) {
                            echo 'style="display:none"';
                          } ?>>


        <div class="row">
          <div class="col-md-12 mb-3">
            <div class="float-end">
              <a class="btn btn-secondary" href="{{URL('/projects/'.$project->id.'/start')}}" role="button"><?php echo get_dialogs('skip', $active_language); ?></a>
            </div>
          </div>


          <div class="col-md-12">
            <div class="float-start">
              <div class="form-check">
                <input class="form-check-input no_information_all" type="checkbox" value="" name="no_information_all">
                <label class="form-check-label" for="checkDefault">
                  <?php echo get_dialogs('no_information_all', $active_language); ?>
                </label>
              </div>
            </div>
            <div class="float-end">
              <div class="form-group">
                <button type="submit" class="btn btn-success" form="specimen_submit">
                  {!!get_dialogs_simple('submit', $active_language)!!}
                </button>
              </div>
            </div>
          </div>

        </div>

        <form method="post" action="{{URL('store_user_response')}}" id="specimen_submit" enctype="multipart/form-data">
          @csrf

          <?php
          foreach ($fields as $field) {
            $is_required = '';
            $required_sign = '';
            if ($field->required == '1') {
              $is_required = 'required';
              $required_sign = '<sup><i class="bi bi-asterisk" style="font-size: 6pt; color: cornflowerblue;"></i></sup>';
            }

          ?>

            <div class="card mt-3 accordion" data-field-id="{{$field->id}}" data-type="{{$field->type}}" data-explode-char="{{$field->explode_char ?? '|'}}" data-reverse="{{$field->explode_save_direction == 'reverse' ? 1 : 0}}">
              <div class="card-header accordion-header accordion-button collapsed py-2 bg-body-secondary" data-bs-toggle="collapse" data-bs-target="#collapse{{$field->id}}" aria-expanded="false" aria-controls="collapse{{$field->id}}">
                {!! get_dialogs('fields_question_'.$field->id, $active_language)!!}
              </div>
              <ul class="list-group list-group-flush ">
                <li class="list-group-item accordion-collapse collapse" id="collapse{{$field->id}}"><small class="form-text text-muted">
                    {!! get_dialogs_html('fields_text_'.$field->id, $active_language)!!}
                  </small></li>
              </ul>
              <div class="card-body">
                @if(!empty($others[$field->id]))
                <?php
                $panel_char = $field->explode_char ?? '|';
                $panel_multipart = count(explode($panel_char, (string) $field->explode_fields)) > 1;
                ?>
                <div class="alert alert-secondary py-2 small admin-others">
                  <strong>{!! get_dialogs('Submitted by other users', $active_language) !!}</strong>
                  @foreach($others[$field->id] as $set)
                  <div class="d-flex justify-content-between align-items-start gap-2 border-top pt-1 mt-1">
                    <div>
                      @foreach($set['lines'] as $line)
                      <?php
                      $shown = $line['value'];
                      if ($panel_multipart) {
                        $shown_parts = explode($panel_char, $shown);
                        if ($field->explode_save_direction == 'reverse') {
                          $shown_parts = array_reverse($shown_parts);
                        }
                        $shown = implode(' · ', $shown_parts);
                      }
                      ?>
                      <div>{{ $shown !== '' ? $shown : '—' }}
                        @if($line['doubt']) <span class="badge text-bg-warning">{!! get_dialogs('have_doubts', $active_language) !!}</span> @endif
                        @if($line['deducted']) <span class="badge text-bg-info">{!! get_dialogs('deducted', $active_language) !!}</span> @endif
                      </div>
                      @endforeach
                      {{-- each person with their own points (not a total); the name is however that person chose to appear (display_name) --}}
                      <div class="text-muted">
                        @foreach($set['users'] as $submitter)
                        <div>{!! $submitter['avatar'] ?? '' !!} {{ $submitter['name'] }} · {{ $submitter['points'] }} {!! get_dialogs('points', $active_language) !!}</div>
                        @endforeach
                      </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary use-value text-nowrap" data-set="{{ json_encode($set['lines']) }}">{!! get_dialogs('Use this value', $active_language) !!}</button>
                  </div>
                  @endforeach
                </div>
                @endif
                <?php
                $number_of_fields = 0;
                $no_information_checkbox = false;
                $indecipherable_checkbox = false;

                $na_value = 'n.a.';
                $indecipherable = 'indecipherable';

                $keys = array_keys(array_column($Collaborative_fields, 'field_id'), $field->id);
                if (empty($keys)) {
                  $Collaborative_fields[] = array('field_id' => $field->id, 'value' => '', 'doubt' => '0', 'deducted' => '0', 'valid_value' => '', 'position' => 1);
                }
                $keys = array_keys(array_column($Collaborative_fields, 'field_id'), $field->id);

                foreach ($keys as $n_position => $key) {
                  $collaborative_field = $Collaborative_fields[$key];

                  //print_r($collaborUser_specimen_fieldsative_field);

                  $texttoappendchild = '';
                  $explode_char = $field->explode_char ?? '|';

                  //if the user has never submitted this value, it will be filled in with the validated value instead of empty. To facilitate corrections.
                  if (($collaborative_field['value'] ?? '') === '') {
                    $collaborative_field['value'] = $collaborative_field['valid_value'];
                  }

                  $values = explode($explode_char, $collaborative_field['value']);
                  // Do NOT reverse $values — they are stored in definition order.
                  // Reversal is applied to the field labels for display only (see below).

                  if ($values[0] == $na_value) {
                    $no_information_checkbox = true;
                  }
                  if ($values[0] == $indecipherable) {
                    $indecipherable_checkbox = true;
                  }

                  $number_of_fields = $number_of_fields + 1;

                ?>
                  <div class="card mb-2 field_card">
                    <div class="card-body">

                      <?php
                      $collaborative_field_valid_value = $collaborative_field['valid_value'] ?? null;
                      if (($collaborative_field['position'] == $number_of_fields) and (str_replace($explode_char, '', $collaborative_field_valid_value) != '')) {
                        $keepfield = 'keepfield';

                      ?>
                        <input type="hidden" id="valid_{{$field->id}}_{{$number_of_fields}}" value="{{$collaborative_field['valid_value']}}">
                        <div class="alert alert-warning" role="alert">{!! get_dialogs('already_verified', $active_language)!!}<br><strong>
                            <?php
                            $valid_values = explode($explode_char, $collaborative_field['valid_value']);
                            if ($field->explode_save_direction == 'reverse') {
                              $reversed_valid_fields = array_reverse(explode($explode_char, $field->explode_fields));
                              $total = count($reversed_valid_fields);
                              $display_valid_values = [];
                              foreach ($reversed_valid_fields as $display_key => $label) {
                                $storage_key = $total - 1 - $display_key;
                                $display_valid_values[$display_key] = [
                                  'label' => $label,
                                  'value' => $valid_values[$storage_key] ?? ''
                                ];
                              }
                              foreach ($display_valid_values as $vv) {
                                if ($vv['value'] == 'n.a.') {
                                  echo get_dialogs('no_information', $active_language) . '<br>';
                                } elseif ($vv['label'] != '') {
                                  echo '<b>' . $vv['label'] . ':</b> ' . ($vv['value'] !== '' ? $vv['value'] : '—') . '<br>';
                                }
                              }
                            } else {
                              // Non-reverse fields: show as before
                              foreach ($valid_values as $vv) {
                                if ($vv == 'n.a.') {
                                  echo get_dialogs('no_information', $active_language) . '<br>';
                                } else {
                                  echo $vv . '<br>';
                                }
                              }
                            }
                            ?>
                          </strong>
                          <br>
                          <input type="radio" name="keeporchange_{{$field->id}}_{{$number_of_fields}}" id="keep_{{$field->id}}_{{$number_of_fields}}" checked="checked" onclick="toogle_keeporchange('{{$field->id}}_{{$number_of_fields}}');" value="dontsubmit">
                          <label class="form-check-label" for="keep_{{$field->id}}_{{$number_of_fields}}">
                            {!! get_dialogs('keep_value', $active_language)!!}
                          </label>
                          <br>
                          <input type="radio" name="keeporchange_{{$field->id}}_{{$number_of_fields}}" id="change_{{$field->id}}_{{$number_of_fields}}" onclick="toogle_keeporchange('{{$field->id}}_{{$number_of_fields}}');">
                          <label class="form-check-label" for="change_{{$field->id}}_{{$number_of_fields}}">
                            {!! get_dialogs('change_value', $active_language)!!}
                          </label>
                        </div>
                      <?php
                      }
                      ?>

                      <div class="row" id="field_{{$field->id}}_{{$number_of_fields}}">
                        <div class="col-md-9">
                          <div class="row">
                            <div class="col-md-4">
                              <label for="field[{{$field->id}}][]" class="form-label float-end">{!! get_dialogs('fields_name_'.$field->id, $active_language)!!} {!!$required_sign!!}
                                <a data-bs-toggle="modal" data-bs-target="#Modalfield{{$field->id}}" style="cursor: pointer"><i class="bi bi-question-circle"></i></a></label>
                            </div>

                            <div class="modal" id="Modalfield{{$field->id}}" tabindex="-1">
                              <div class="modal-dialog">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title">{!! get_dialogs_html('fields_name_'.$field->id, $active_language)!!}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </div>
                                  <div class="modal-body">
                                    <?php
                                    echo get_dialogs_html('fields_helper_' . $field->id, $active_language);

                                    if ($field->helper_image != '') {
                                      echo '<img src="' . URL('images/' . $field->helper_image) . '">';
                                    }
                                    ?>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <div class="col-md-8">
                              @if($field->type == 'text' OR $field->type == 'date')
                              <?php
                              $is_date = '';
                              if ($field->type == 'date') {
                                $is_date = 'date';
                              }

                              if ($is_required == 'required') {
                              ?><script type="text/javascript">
                                  $("#specimen_submit").on("submit", function() {
                                    var input_lenght = 0;
                                    $(".field_{{$field->id}}").each(function() {
                                      input_lenght = input_lenght + $(this).val().length;
                                    });
                                    $(".field_{{$field->id}}_checkbox").each(function() {
                                      if ($(this).is(':checked')) {
                                        input_lenght = input_lenght + 1;
                                      }
                                    });
                                    if (input_lenght == 0) {
                                      alert(`"{!! get_dialogs_simple('fields_name_'.$field->id, $active_language)!!}" is required`);
                                      return false;
                                    }
                                  });
                                </script>
                              <?php
                              }

                              $explode_char = $field->explode_char ?? '|';

                              $exploded_fields = explode($explode_char, $field->explode_fields);
                              // $values already mapped positionally (Year=>[0], Month=>[1], Day=>[2])
                              // For display, reverse only the labels and remap values accordingly
                              if ($field->explode_save_direction == 'reverse') {
                                $reversed_fields = array_reverse($exploded_fields);
                                // Remap: value at position i belongs to reversed_fields[total-1-i]
                                $total = count($exploded_fields);
                                $display_values = [];
                                foreach ($reversed_fields as $display_key => $label) {
                                  $storage_key = $total - 1 - $display_key;
                                  $display_values[$display_key] = $values[$storage_key] ?? '';
                                }
                                $exploded_fields = $reversed_fields;
                                $values = $display_values;
                              }

                              foreach ($exploded_fields as $exploded_key => $exploded_field) {
                                $div_input_class = '';
                                if ($exploded_field != '') {
                                  $div_input_class = 'form-floating';
                                  $is_required = '';
                                }
                                $this_value = $values[$exploded_key] ?? '';
                              ?>
                                <div class="{{$div_input_class}} mb-1">
                                  <input type="text" class="typeahead{{$field->id}}_{{$exploded_key}} form-control {{$field->format}} {{$is_date}} field_{{$field->id}}" placeholder="{{$exploded_field}}" id="field_{{$field->id}}_{{$n_position}}_{{$exploded_key}}" name="field[{{$field->id}}][{{$n_position}}][{{$exploded_field}}]" value="{{$this_value}}" {{$is_required}} autofocus autocomplete="off">
                                  <?php
                                  if ($exploded_field != '') {
                                  ?>
                                    <label>{{$exploded_field}}</label>
                                  <?php
                                  }
                                  ?>
                                </div>
                                <?php
                                if ($field->autocomplete != 0) {
                                ?>
                                  <script type="text/javascript">
                                    $('.typeahead{{$field->id}}_{{$exploded_key}}').typeahead({
                                      minLength: 3,
                                      delay: 500,
                                      items: 'all',
                                      fitToElement: true,
                                      selectOnBlur: false,
                                      changeInputOnMove: false,
                                      source: function(query, process) {
                                        return $.get("{{route('autocomplete', array('id' => $field->id))}}", {
                                          query: query
                                        }, function(data) {
                                          return process(data);
                                        });
                                      },
                                      autoSelect: false,
                                      //this triggers after a value has been selected on the control
                                      afterSelect: function(data) {
                                        //split the string and put values to correspondent input box
                                        const exploded_result = data.split("{{$explode_char}}");
                                        for (let exploded_key = 0; exploded_key < exploded_result.length; ++exploded_key) {
                                          $("#field_{{$field->id}}_{{$n_position}}_" + exploded_key).val(exploded_result[exploded_key].trim());
                                        }
                                        //alert(exploded_result[0]);
                                      }
                                    });
                                  </script>
                                <?php
                                }
                                ?>

                              <?php
                              }
                              ?>
                              @endif
                              @if($field->type == 'textarea')
                              <textarea class="typeahead{{$field->id}} form-control" name="field[{{$field->id}}][]" {{$is_required}} autofocus autocomplete="off">{{$values[0]}}</textarea>
                              <?php
                              if ($field->autocomplete != 0) {
                              ?>
                                <script type="text/javascript">
                                  $('.typeahead{{$field->id}}').typeahead({
                                    minLength: 3,
                                    delay: 500,
                                    items: 'all',
                                    fitToElement: true,
                                    selectOnBlur: false,
                                    changeInputOnMove: false,
                                    source: function(query, process) {
                                      return $.get("{{route('autocomplete', array('id' => $field->id))}}", {
                                        query: query
                                      }, function(data) {
                                        return process(data);
                                      });
                                    },
                                    autoSelect: false,
                                  });
                                </script>
                              <?php
                              }
                              ?>
                              @endif
                              @if($field->type == 'select')
                              <select class="form-select" name="field[{{$field->id}}][]" {{$is_required}} autofocus>
                                <?php
                                // what is saved is the option's value; what is shown is its label in this language
                                foreach ($field->choices($active_language) as $choice) {
                                ?>
                                  <option value="{{$choice['value']}}" <?php if ($choice['value'] == $values[0]) {
                                                                echo 'selected';
                                                              } ?>>{{$choice['label']}}</option>
                                <?php
                                }
                                ?>
                              </select>
                              @endif
                              @if($field->type == 'radio')
                              <?php
                              foreach ($field->choices($active_language) as $choice) {
                              ?>
                                <div class="form-check">
                                  <input class="form-check-input field" type="radio" name="field[{{$field->id}}][]" {{$is_required}} value="{{$choice['value']}}" <?php if ($choice['value'] == $values[0]) {
                                                                                                                                                            echo 'checked';
                                                                                                                                                          } ?>>
                                  <label class="form-check-label" for="field[{{$field->id}}][]">
                                    {{$choice['label']}}
                                  </label>
                                </div>
                              <?php
                              }
                              ?>
                              @endif


                              @if($field->type == 'checkboxes')
                              <div class="form-check checkbox-group {{$is_required}}">
                                <?php
                                // a separator: the options ticked on this line are one value, so they are posted as the
                                // array of this line (field[id][line][]); without one every tick is a line of its own
                                $joined = $field->joinsChoices();
                                $ticked = $joined ? $field->splitChoices((string) ($collaborative_field['value'] ?? '')) : [$values[0] ?? ''];
                                $checkbox_name = $joined ? 'field[' . $field->id . '][' . $n_position . '][]' : 'field[' . $field->id . '][]';
                                foreach ($field->choices($active_language) as $choice) {
                                ?>
                                  <div class="form-check">
                                    <input class="form-check-input field" type="checkbox" name="{{$checkbox_name}}" value="{{$choice['value']}}" <?php if ($choice['value'] !== '' && in_array($choice['value'], $ticked, true)) {
                                                                                                                                                                    echo 'checked';
                                                                                                                                                                  } ?>>
                                    <label class="form-check-label" for="field[{{$field->id}}][]">
                                      {{$choice['label']}}
                                    </label>
                                  </div>
                                <?php
                                }
                                ?>
                              </div>
                              <?php
                              if ($is_required == 'required') {
                              ?>
                                <script type="text/javascript">
                                  $("#specimen_submit").on("submit", function() {
                                    if ($('input.field[type="checkbox"][name^="field[{{$field->id}}]"]:checked').length == 0) {
                                      alert(`There are checboxes missing on "{!! get_dialogs_simple('fields_name_'.$field->id, $active_language)!!}"`);
                                      return false;
                                    }
                                  });
                                </script>
                              <?php
                              }
                              ?>
                              @endif


                              @if($field->type == 'tree')

                              <div class="card">
                                <div class="card-body" style="padding:5px; min-height: 30px" id="panel[{{$field->id}}][]">{{$values[0]}}
                                </div>
                              </div>
                              <div class="input-group input-group-sm">
                                <span class="input-group-text" id="sizing-addon3"><i class="bi bi-search"></i></span>
                                <input type="search" id="search_tree" class="form-control" aria-describedby="sizing-addon3">
                              </div>
                              <div class="card">
                                <div class="card-body" style="height: 300px; overflow-y: scroll">
                                  <div id="tree"></div>
                                  <input class="form-control" style="opacity: 0; height: 0px;" type="text" name="field[{{$field->id}}][]" id="field[{{$field->id}}][]" value="{{$values[0]}}" {{$is_required}} autofocus>
                                </div>
                              </div>

                              <script>
                                let data = <?php echo $field->option_values; ?>;
                                // $jq is just this block's own alias for jQuery — noConflict() here
                                // released the global "$" for the whole page (this form loads no
                                // library that defines its own "$"), breaking the layout's own
                                // scripts and the shared dialog-edit modal with "$ is not a function".
                                var $jq = jQuery;
                                var options = {
                                  // Optionally provide here the jQuery element that you use as the search box for filtering the tree. simpleTree then takes control over the provided box, handling user input
                                  searchBox: $jq('#search_tree'),

                                  // Search starts after at least 3 characters are entered in the search box
                                  searchMinInputLength: 3,

                                  // Number of pixels to indent each additional nesting level
                                  indentSize: 25,

                                  // Show child count badges?
                                  childCountShow: false,

                                  // Symbols for expanded and collapsed nodes that have child nodes
                                  symbols: {
                                    collapsed: '⏵',
                                    expanded: '⏷'
                                  },

                                  // these are the CSS class names used on various occasions. If you change these names, you also need to provide the corresponding CSS class
                                  css: {
                                    childrenContainer: 'simpleTree-childrenContainer',
                                    childCountBadge: 'simpleTree-childCountBadge badge badge-pill badge-secondary',
                                    highlight: 'simpleTree-highlight',
                                    indent: 'simpleTree-indent',
                                    label: 'simpleTree-label',
                                    mainContainer: 'simpleTree-mainContainer',
                                    nodeContainer: 'simpleTree-nodeContainer',
                                    selected: 'simpleTree-selected',
                                    toggle: 'simpleTree-toggle'
                                  }
                                };

                                $jq('#tree').simpleTree(options, data);
                                $jq('#tree').click(function() {
                                  var full_selected = '';
                                  var node = $jq(this).data('simpleTree').getSelectedNode();
                                  while (node != undefined) {
                                    full_selected = node.label + ' > ' + full_selected;
                                    node = node.parent;
                                  }
                                  full_selected = full_selected.replace(/([> ]*)$/g, '');
                                  document.getElementById('field[{{$field->id}}][]').value = full_selected;
                                  document.getElementById('panel[{{$field->id}}][]').innerHTML = full_selected;

                                  //alert('full_selected: ' + full_selected);
                                });
                              </script>
                              @endif

                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <?php
                          if ($field->have_doubts == 1) {
                          ?>
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="doubt[{{$field->id}}][{{$n_position}}]" value="1" <?php if ($collaborative_field['doubt'] == 1) {
                                                                                                                          echo "checked";
                                                                                                                        } ?>>
                              <label class="form-check-label" for="flexCheckDefault">{!!get_dialogs('have_doubts', $active_language)!!}</label>
                            </div>
                          <?php
                          }
                          if ($field->deducted == 1) {
                          ?>
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="deducted[{{$field->id}}][{{$n_position}}]" value="1" <?php if ($collaborative_field['deducted'] == 1) {
                                                                                                                            echo "checked";
                                                                                                                          } ?>><label class="form-check-label" for="deducted[{{$field->id}}][{{$n_position}}]">{!!get_dialogs('deducted', $active_language)!!}</label>
                            </div>
                          <?php
                          }
                          ?>
                        </div>
                      </div>
                      @if($number_of_fields > 1)
                      <div class="col-1 float-end">
                        <button type="button" name="remove" class="btn btn-sm btn-danger btn_remove m-1" style="position:absolute; right:0; top:0"><i class="bi bi-trash3"></i></button>
                      </div>
                      @endif

                    </div>
                    <div class="card-footer">
                      <div class="form-check form-check-inline">
                        <input class="form-check-input field_{{$field->id}}_checkbox no_information_checkbox" type="checkbox" name="field[{{$field->id}}][{{$n_position}}]" id="no_information_{{$field->id}}_{{$number_of_fields}}" value="{{$na_value}}" onclick="toogle_active('{{$field->id}}_{{$number_of_fields}}');">
                        <label class="form-check-label" for="flexCheckDefault">{!!get_dialogs('no_information', $active_language)!!}</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input field_{{$field->id}}_checkbox" type="checkbox" name="field[{{$field->id}}][{{$n_position}}]" id="indecipherable_{{$field->id}}_{{$number_of_fields}}" value="{{$indecipherable}}" onclick="toogle_active('{{$field->id}}_{{$number_of_fields}}');">
                        <label class="form-check-label" for="flexCheckDefault">{!!get_dialogs('indecipherable', $active_language)!!}</label>
                      </div>
                    </div>
                  </div>
                  <script type="text/javascript">
                    toogle_keeporchange('{{$field->id}}_{{$number_of_fields}}');
                  </script>
                <?php
                }
                ?>

                <?php
                if ($field->multiple == 1) {
                ?>
                  <div class="">
                    <button class="btn btn-primary m-1 add_line" type="button" name="add" id="add{{$field->id}}">add line</button>
                  </div>
                <?php
                }
                ?>

              </div>
              <div class="card-footer ">
                <span>{!!get_dialogs('comments', $active_language)!!}</span>
                <table class="table mb-1">
                  <tbody id="comments_panel_{{$field->id}}">
                    <?php
                    $last_comment_id = 0;
                    foreach ($comments as $comment) {
                      if ($comment->field_id == $field->id) {
                        $last_comment_id = $comment->id;

                    ?>
                        <tr>
                          <td>
                            <strong>
                              {!! display_avatar($comment->user, 22, 'auto', $project->id != 0 ? $project : null) !!} {{ display_name($comment->user, 'auto', $project->id != 0 ? $project : null) }}
                            </strong>
                            <div style="float: right">
                              <small>
                                <?php echo $comment->created_at; ?>
                              </small>
                            </div><br>
                            <?php
                            $msg = preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a href="\1" target="_blank">\1</a>', $comment->value);
                            echo $msg;
                            ?>
                            <?php
                            if (Auth::check() && $comment->user && Auth::id() == $comment->user->id) {
                            ?>
                              <div style="float: right">
                                <button type="button" class="btn btn-danger btn-sm delete_comment" id="{{$comment->id}}">
                                  <i class="bi bi-trash3"></i>
                                </button>
                              </div>
                            <?php
                            }
                            ?>

                          </td>
                        </tr>

                    <?php
                      }
                    }
                    ?>

                  </tbody>
                </table>
                <?php
                //	$logged = 'autenticado';
                if (Auth::guest()) {
                ?>
                  <h6>{!!get_dialogs('log in to comment', $active_language)!!}</h6>
                <?php
                } else {
                ?>
                  <input type="hidden" id="last_comment_id_{{$field->id}}" value="{{$last_comment_id}}">
                  <button class="btn btn-outline-secondary btn-sm" type="button" name="add_comment" id="add_comment{{$field->id}}" data-bs-toggle="modal" data-bs-target="#addcommentModal" onClick="assign_values_to_comment('{{$field->id}}')">{!!get_dialogs('write comment', $active_language)!!}</button>
                <?php
                }
                ?>

              </div>
              @auth
              <div class="card-footer py-1 text-end bg-transparent border-0">
                <button type="submit" form="banfield_{{$field->id}}" class="btn btn-link btn-sm text-muted text-decoration-none p-0">
                  <i class="bi bi-eye-slash"></i> {!! get_dialogs("don't show me this field", $active_language) !!}
                </button>
              </div>
              @endauth
            </div>

            <?php
            if ($no_information_checkbox == true) {
            ?>
              <script type="text/javascript">
                document.getElementById('no_information_{{$field->id}}_{{$number_of_fields}}').checked = true;
                toogle_active('{{$field->id}}_{{$number_of_fields}}');
              </script>
            <?php
            }

            if ($indecipherable_checkbox == true) {
            ?>
              <script type="text/javascript">
                document.getElementById('indecipherable_{{$field->id}}_{{$number_of_fields}}').checked = true;
                toogle_active('{{$field->id}}_{{$number_of_fields}}');
              </script>
          <?php
            }
          }
          ?>
          <input type="hidden" name="specimen_id" value="{{$specimen->specimen_id}}">
          <input id="project_id" name="project_id" type="hidden" value="{{$project->id}}">
          <input name="displayed_at" type="hidden" value="{{ \Illuminate\Support\Facades\Crypt::encryptString(now()->toDateTimeString()) }}">
          <div class="row">
            <div class="col-md-12 mt-3">

              <div class="float-start">
                <div class="form-check">
                  <input class="form-check-input no_information_all" type="checkbox" value="" name="no_information_all">
                  <label class="form-check-label" for="checkDefault">
                    <?php echo get_dialogs('no_information_all', $active_language); ?>
                  </label>
                </div>
              </div>

              <div class="float-end">
                <div class="form-group">
                  <button type="submit" class="btn btn-success" form="specimen_submit">
                    {!!get_dialogs_simple('submit', $active_language)!!}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </form>

        @auth
        {{-- hidden helper forms for the per-field "hide / show" toggles (kept outside #specimen_submit) --}}
        @foreach($fields->concat($banned_fields ?? collect()) as $togglefield)
          <form id="banfield_{{$togglefield->id}}" method="post" action="{{ url('/toggle_field_ban') }}" class="d-none">
            @csrf
            <input type="hidden" name="field_id" value="{{ $togglefield->id }}">
            <input type="hidden" name="return" value="{{ '/projects/'.$project->id.'/'.$specimen->specimen_id }}">
          </form>
        @endforeach

        @if(($banned_fields ?? collect())->isNotEmpty())
        <div class="alert alert-light border mt-3 py-2">
          <small class="text-muted">{!! get_dialogs('fields you have hidden', $active_language) !!}:</small>
          @foreach($banned_fields as $bf)
            <button type="submit" form="banfield_{{$bf->id}}" class="btn btn-sm btn-outline-secondary ms-1">
              {!! get_dialogs('fields_name_'.$bf->id, $active_language) !!} <i class="bi bi-eye"></i>
            </button>
          @endforeach
        </div>
        @endif
        @endauth
      </div>
      @if(!session('different_values'))
      <div class="row">
        <div class="col-md-12 mt-3">
          <div class="float-end">
            <a class="btn btn-secondary" href="{{URL('/projects/'.$project->id.'/start')}}" role="button"><?php echo get_dialogs('skip', $active_language); ?></a>
          </div>
        </div>
      </div>
      @endif
    </row>
  </div>
</article>

<!-- Comment Modal -->
<div class="modal fade" id="addcommentModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="addcommentModalLabel">{!!get_dialogs('write comment', $active_language)!!}</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form action="" method="POST" id="comment_form">
          @csrf
          <div class="form-group">
            <label for="comment">{!!get_dialogs('my comment', $active_language)!!}:</label>
            <textarea class="form-control" rows="5" id="comment_value" name="comment_value"></textarea>
            <input type="hidden" id="comment_specimen_id" name="comment_specimen_id" value="{{$specimen->specimen_id}}">
            <input type="hidden" id="comment_field_id" name="comment_field_id" value="">
            <input type="hidden" id="comment_project_id" name="comment_project_id" value="{{$specimen->project_id}}">
            <input type="hidden" id="last_comment_id" name="last_comment_id" value="">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!!get_dialogs('close', $active_language)!!}</button>
        <button type="submit" class="btn btn-primary" form="submit_comment" id="submit_comment">{!!get_dialogs('submit', $active_language)!!}</button>
      </div>

    </div>
  </div>
</div>


<script src="{{URL::asset('js/jquery.maskedinput.min.js')}}"></script>
<script type="text/javascript">
  $(document).on('click', '.no_information_all', function() {
    var status = $(this).prop('checked');
    //becasue there are 2 buttons, apply the same status to both
    $('.no_information_all').prop("checked", status);
    $('.no_information_checkbox').each(function(i, obj) {
      $(this).prop("checked", status);
      var no_information_id = $(this).attr('id');
      var field_id = no_information_id.replace("no_information_", "");
      toogle_active(field_id);

    });
  });


  //aplica uma mascara aos campos "data" no formulário de pesquisa
  jQuery(function($) {
    //  $.mask.definitions['h'] = "[0-9*]"; 
    $.mask.definitions['y'] = '[12]';
    $.mask.definitions['m'] = '[01]';
    $.mask.definitions['d'] = '[0-3]';
    $(".date").mask("y999-m9-d9", {
      placeholder: "YYYY-MM-DD",
      autoclear: false
    });
  });

  $('.add_line').click(function() {
    var button_id = $(this).attr("id");
    var field_id = button_id.replace("add", "");
    var $wrapper = $(this).parent();
    var $cards = $wrapper.siblings('.field_card');
    var $template = $cards.first();

    var removeButtonHtml = '<div class="col-1 float-end"><button type="button" name="remove" class="btn btn-sm btn-danger btn_remove m-1" style="position:absolute; right:0; top:0"><i class="bi bi-trash3"></i></button></div>';

    function ensureRemoveButton($card) {
      if ($card.find('.btn_remove').length === 0) {
        $card.find('.card-body').first().append(removeButtonHtml);
      }
    }

    // every card for this field must post under its own unique position index, or
    // the browser silently drops all but the last one (they'd share the same
    // field[ID][N][...] / doubt[ID][N] / deducted[ID][N] names) and everything gets
    // saved as a single position=1 row, with only the last line's doubt/deducted.
    var namePrefixes = ['field[' + field_id + ']', 'doubt[' + field_id + ']', 'deducted[' + field_id + ']'];

    var maxIndex = -1;
    $cards.each(function() {
      var $card = $(this);
      namePrefixes.forEach(function(prefix) {
        $card.find('[name^="' + prefix + '["]').each(function() {
          var m = new RegExp('^' + prefix.replace(/[[\]]/g, '\\$&') + '\\[(\\d+)\\]').exec($(this).attr('name'));
          if (m) {
            var n = parseInt(m[1], 10);
            if (n > maxIndex) maxIndex = n;
          }
        });
      });
    });
    var newIndex = maxIndex + 1;

    var $clone = $template.clone();

    // renumber name="PREFIX[OLD][...]" -> PREFIX[NEW][...] for field/doubt/deducted,
    // and the matching id="field_ID_OLD_..." so the new line's inputs (and their
    // autocomplete ids) don't collide with the template's.
    namePrefixes.forEach(function(prefix) {
      var escapedPrefix = prefix.replace(/[[\]]/g, '\\$&');
      $clone.find('[name^="' + prefix + '["]').each(function() {
        var name = $(this).attr('name');
        $(this).attr('name', name.replace(new RegExp('^' + escapedPrefix + '\\[\\d+\\]'), prefix + '[' + newIndex + ']'));
      });
    });
    $clone.find('[id^="field_' + field_id + '_"]').each(function() {
      var id = $(this).attr('id');
      $(this).attr('id', id.replace(new RegExp('^field_' + field_id + '_\\d+'), 'field_' + field_id + '_' + newIndex));
    });
    $clone.find('[id^="no_information_' + field_id + '_"], [id^="indecipherable_' + field_id + '_"]').each(function() {
      var id = $(this).attr('id');
      $(this).attr('id', id.replace(/_\d+$/, '_' + (newIndex + 1)));
    });

    // the new line starts blank — don't carry over the template card's values
    $clone.find('input[type="text"], textarea').val('');
    $clone.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
    $clone.find('.btn_remove').closest('.col-1').remove();

    $wrapper.before($clone);

    ensureRemoveButton($template);
    ensureRemoveButton($clone);
  });

  @if(!($can_submit ?? true))
  // visitors who may only browse: nothing here can be submitted
  $(function() {
    $('button[type="submit"]').filter(function() {
      return (this.form && this.form.id === 'specimen_submit') || this.getAttribute('form') === 'specimen_submit';
    }).prop('disabled', true).attr('title', {!! json_encode(get_dialogs_simple('Log in to submit', $active_language)) !!});
  });
  @endif

  // "Different values" page: pick which value to keep for each disagreeing line.
  // Keeping your own changes nothing; picking another value switches your submission to it.
  // When every line is decided the page moves on to the next specimen by itself.
  (function() {
    var $root = $('#conflict-groups');
    if (!$root.length) return;

    function goNext() {
      window.location.replace($root.attr('data-next'));
    }

    function decided($group, text) {
      $group.attr('data-decided', '1').removeClass('alert-warning').addClass('alert-success')
        .find('.conflict-values').replaceWith($('<div class="small">').text('✓ ' + text));
      if ($('.conflict-group:not([data-decided])').length === 0) {
        goNext();
      }
    }

    $root.on('click', '.choose-value', function() {
      var $btn = $(this);
      var $group = $btn.closest('.conflict-group');
      var text = $btn.closest('li').find('.value-text').text();

      if ($btn.attr('data-mine')) {
        decided($group, text);
        return;
      }

      $btn.prop('disabled', true);
      $.ajax({
        type: 'POST',
        url: $root.attr('data-url'),
        data: {
          _token: $root.attr('data-token'),
          specimen_id: $root.attr('data-specimen'),
          project_id: $root.attr('data-project'),
          field_id: $group.attr('data-field'),
          position: $group.attr('data-position'),
          value: $btn.attr('data-value')
        }
      }).done(function() {
        decided($group, text);
      }).fail(function(xhr) {
        $btn.prop('disabled', false);
        alert((xhr.responseJSON && xhr.responseJSON.message) || 'error');
      });
    });

    $('#keep-all-values').on('click', function() {
      goNext();
    });
  })();

  // Admins: copy another user's submitted lines into this field's inputs. Nothing is
  // sent anywhere — the admin reviews the filled-in form and submits it as usual.
  function fillLine($card, line, cfg) {
    var value = line.value || '';
    var parts = value.split(cfg.char);

    // a line that already has a validated value starts on "keep": switch to "change" so it can be edited
    var $change = $card.find('input[type="radio"][id^="change_"]');
    if ($change.length && !$change.prop('checked')) {
      $change.trigger('click');
    }

    var $row = $card.find('[id^="field_"]').first();
    var mode = parts[0] === 'n.a.' ? 'na' : (parts[0] === 'indecipherable' ? 'indecipherable' : null);

    $card.find('input[id^="no_information_"]').prop('checked', mode === 'na');
    $card.find('input[id^="indecipherable_"]').prop('checked', mode === 'indecipherable');
    $row.css('opacity', mode ? 0.4 : 1).find('input,textarea,select').prop('disabled', !!mode);
    if (mode) {
      return;
    }

    if (cfg.type === 'text' || cfg.type === 'date') {
      var $inputs = $row.find('input[type="text"]');
      if ($inputs.length > 1) {
        var dom = cfg.reverse ? parts.slice().reverse() : parts;
        $inputs.each(function(i) {
          $(this).val(dom[i] !== undefined ? dom[i] : '').trigger('change');
        });
      } else {
        $inputs.val(value).trigger('change');
      }
    } else if (cfg.type === 'textarea') {
      $row.find('textarea').val(value).trigger('change');
    } else if (cfg.type === 'select') {
      $row.find('select').val(value).trigger('change');
    } else if (cfg.type === 'radio') {
      $row.find('input[type="radio"]').each(function() {
        $(this).prop('checked', this.value === value);
      });
    } else if (cfg.type === 'checkboxes') {
      $row.find('input[type="checkbox"]').each(function() {
        $(this).prop('checked', parts.indexOf(this.value) !== -1);
      });
    } else if (cfg.type === 'tree') {
      document.getElementById('field[' + cfg.fieldId + '][]').value = value;
      document.getElementById('panel[' + cfg.fieldId + '][]').innerHTML = $('<div>').text(value).html();
    }

    $row.find('input[name^="doubt["]').prop('checked', !!line.doubt);
    $row.find('input[name^="deducted["]').prop('checked', !!line.deducted);
  }

  $(document).on('click', '.use-value', function() {
    var $fieldCard = $(this).closest('[data-field-id]');
    var lines = JSON.parse($(this).attr('data-set'));
    var cfg = {
      fieldId: $fieldCard.attr('data-field-id'),
      type: $fieldCard.attr('data-type'),
      char: $fieldCard.attr('data-explode-char'),
      reverse: $fieldCard.attr('data-reverse') === '1'
    };

    // as many lines as the submission has (multi-value fields only)
    var $add = $('#add' + cfg.fieldId);
    $add.prop('disabled', false);
    while ($fieldCard.find('.field_card').length < lines.length && $add.length) {
      $add.trigger('click');
    }
    $fieldCard.find('.field_card').slice(lines.length).remove();

    $fieldCard.find('.field_card').each(function(i) {
      fillLine($(this), lines[i] || {
        value: '',
        doubt: 0,
        deducted: 0
      }, cfg);
    });
  });

  $(document).on('click', '.btn_remove', function() {
    //        var button_id = $(this).attr("id");
    // alert(button_id);
    //        $('#row_' + button_id).remove();
    $(this).parents('.field_card').remove();
  });

  function assign_values_to_comment(field_id) {
    document.getElementById('comment_field_id').value = field_id;
    document.getElementById('last_comment_id').value = document.getElementById('last_comment_id_' + field_id).value;
  }

  function validate_submit() {
    var submit = true;

    var comment_value = document.getElementById('comment_value').value;
    if (comment_value == '') {
      alert('You must write a comment before submit');
      submit = false;
    }
    return submit;
  }

  $(document).on('click', '#submit_comment', function(e) {
    e.preventDefault();
    var comment_field_id = document.getElementById('comment_field_id').value;

    var submit = validate_submit();
    if (submit == true) {
      $.ajax({
        type: "POST",
        cache: false,
        url: "{{URL('submit_comment')}}",
        data: $("#comment_form").serialize(),
        success: function(data) {
          $('#addcommentModal').modal('hide');
          $("#comments_panel_" + comment_field_id).append($(data.html).hide().fadeIn(2000));
          $('#last_comment_id_' + comment_field_id).val(data.last_comment_id);
          $('#comment_value').val("");

        }
      });
    }
  });

  $(document).on('click', '.delete_comment', function(e) {
    e.preventDefault();
    var comment_id = this.id;
    if (!confirm("{!!get_dialogs_simple('confirm delete comment?', $active_language)!!}")) {
      return false;
    }

    $.ajax({
      type: "POST",
      cache: false,
      url: "{{URL('delete_comment')}}",
      data: {
        "_token": "{{ csrf_token() }}",
        "comment_id": comment_id
      },
      success: function(data) {
        $("#comments_panel_" + data.field_id).html(data.html);
      }
    });
  });
</script>

@endsection