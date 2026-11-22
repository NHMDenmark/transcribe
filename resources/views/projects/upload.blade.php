@extends( 'layouts.app' )

@section( 'content' )
<div class="container">
    <div id="flash-message" class="alert d-none">
    </div>
</div>
<div class="container">
    <div class="row justify-content-center">
        <div class="album py-2">
            <div class="col float-end">
                @include('projects.controls')
            </div>
        </div>
    </div>
</div>
<div class="container">

    <div class="card my-2">
        <div class="card-header">
            {!! get_dialogs('Upload data', $active_language) !!}
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <p class="mb-2"><strong>{!! get_dialogs('File format:', $active_language) !!}</strong> {!! get_dialogs('.xlsx, .xls or .csv, with a header row.', $active_language) !!}</p>
                <p class="mb-2"><strong>{!! get_dialogs('Required columns:', $active_language) !!}</strong></p>
                <ul class="mb-2">
                    <li><code>specimen_id</code> — {!! get_dialogs("the specimen's catalog number", $active_language) !!}</li>
                    <li><code>images</code> — {!! get_dialogs('one image URL, or several separated by | (pipe) for a specimen with multiple images, e.g.', $active_language) !!}<br>
                        <code>https://.../image1.jpg|https://.../image2.jpg</code></li>
                </ul>
                <p class="mb-0"><strong>{!! get_dialogs('Replace vs. append:', $active_language) !!}</strong> {!! get_dialogs('"replace existing records" deletes every specimen already imported for this project before importing the file; "append to existing records" adds the file\'s rows without touching what\'s already there.', $active_language) !!}</p>
            </div>
            <form method="post" action="{{URL('import')}}" enctype="multipart/form-data" id="data_upload">
                {{ csrf_field() }}
                <div class="form-group row mb-2 {{ $errors->has('file') ? ' has-error' : '' }}">
                    <label for="file" class="col-sm-2 col-form-label">{!! get_dialogs('File', $active_language) !!} {!! help_hint('upload_file', $active_language) !!}<sub>{!! get_dialogs('Max size:', $active_language) !!}<?php echo ini_get("upload_max_filesize"); ?></sub></label>
                    <div class="col-sm-10">
                        <input type="file" name="file" id="file">
                    </div>
                </div>

                <div class="form-group row mb-2 {{ $errors->has('replace_update') ? ' has-error' : '' }}">
                    <label for="replace_update" class="col-sm-2 col-form-label">{!! get_dialogs('Action', $active_language) !!}</label>
                    <div class="col-sm-10">
                        <select name="replace_update">
                            <option value="0" selected>{{ get_dialogs_simple('replace existing records', $active_language) }}</option>
                            <option value="1">{{ get_dialogs_simple('append to existing records', $active_language) }}</option>
                        </select>
                    </div>
                </div>

                <div class="form-group row mb-2 {{ $errors->has('min_user_level') ? ' has-error' : '' }}">
                    <label for="min_user_level" class="col-sm-2 col-form-label">{!! get_dialogs('Minimum User Level', $active_language) !!} {!! help_hint('upload_min_level', $active_language) !!}</label>
                    <div class="col-sm-10">
                        <select name="min_user_level">
                            <?php
                            foreach($user_categories->sortBy('points') as $category)
                            {
                                ?>
                                <option value="{{$category->id}}" selected>{{$category->category}}</option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>


                <div class="form-group">
                    <input type="hidden" name="project_id" value="{{$project->id}}">
                    <input type="submit" name="submit" value="{{ get_dialogs_simple('Upload', $active_language) }}">
                </div>

            </form>
        </div>
    </div>
</div>
@endsection