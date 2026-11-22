<div class="modal fade" id="project_Modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="">{!! get_dialogs('Edit Project', $active_language) !!}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="projects" enctype="multipart/form-data" id="project_edit">
                    @csrf
                    <div class="form-group">
                        <label for="name" class="col-form-label">{!! get_dialogs('Name:', $active_language) !!}</label>
                        <input type="text" class="form-control" id="name" name="name">
                    </div>

                    <div class="form-group mt-4">
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="project_title-tab" data-bs-toggle="tab" data-bs-target="#project_title-tab-pane" type="button" role="tab" aria-controls="project_title-tab-pane" aria-selected="true">{!! get_dialogs('Title', $active_language) !!}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="project_abstract-tab" data-bs-toggle="tab" data-bs-target="#project_abstract-tab-pane" type="button" role="tab" aria-controls="project_abstract-tab-pane" aria-selected="false">{!! get_dialogs('Abstract', $active_language) !!}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="project_body-tab" data-bs-toggle="tab" data-bs-target="#project_body-tab-pane" type="button" role="tab" aria-controls="project_body-tab-pane" aria-selected="false">{!! get_dialogs('Description', $active_language) !!}</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade show active" id="project_title-tab-pane" role="tabpanel" aria-labelledby="project_title-tab" tabindex="0">
                                <?php
                                foreach ($languages as $language) {
                                ?>
                                    <div class="form-group">
                                        <label for="project_title_{{$language->iso2}}" class="col-form-label"><img width="20px" src="{{url('/flags/4x3/'.$language->iso2 .'.svg')}}" /> {{$language->iso2}}:</label>
                                        <textarea class="form-control ckeditor" id="project_title_{{$language->iso2}}" name="project_title_{{$language->iso2}}">project_title_{{$language->iso2}}</textarea>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>
                            <div class="tab-pane fade" id="project_abstract-tab-pane" role="tabpanel" aria-labelledby="project_abstract-tab" tabindex="0">
                                <?php
                                foreach ($languages as $language) {
                                ?>
                                    <div class="form-group">
                                        <label for="project_abstract_{{$language->iso2}}" class="col-form-label"><img width="20px" src="{{url('/flags/4x3/'.$language->iso2 .'.svg')}}" /> {{$language->iso2}}:</label>
                                        <textarea class="form-control ckeditor" id="project_abstract_{{$language->iso2}}" name="project_abstract_{{$language->iso2}}">project_abstract_{{$language->iso2}}</textarea>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>
                            <div class="tab-pane fade" id="project_body-tab-pane" role="tabpanel" aria-labelledby="project_body-tab" tabindex="0">
                                <?php
                                foreach ($languages as $language) {
                                ?>
                                    <div class="form-group">
                                        <label for="project_body_{{$language->iso2}}" class="col-form-label"><img width="20px" src="{{url('/flags/4x3/'.$language->iso2 .'.svg')}}" /> {{$language->iso2}}:</label>
                                        <textarea class="form-control ckeditor" id="project_body_{{$language->iso2}}" name="project_body_{{$language->iso2}}">project_body_{{$language->iso2}}</textarea>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="project_id" id="project_id" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
                <button type="submit" class="btn btn-primary" form="project_edit">{!! get_dialogs('Submit', $active_language) !!}</button>
            </div>
        </div>
    </div>
</div>