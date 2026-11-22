<a href="<?php echo url('projects/' . $project->id . '/edit'); ?>" role="button" class="btn btn-primary btn-sm" title="<?php echo get_dialogs_simple('Edit project metadata', $active_language); ?>">
    <i class="bi bi-card-heading"></i>
</a>
<a href="<?php echo url('projects/' . $project->id . '/upload'); ?>" role="button" class="btn btn-primary btn-sm" title="<?php echo get_dialogs_simple('Import data', $active_language); ?>">
    <i class="bi bi-database-fill-up"></i>
</a>
<a href="<?php echo url('projects/' . $project->id . '/specimens'); ?>" role="button" class="btn btn-primary btn-sm" title="<?php echo get_dialogs_simple('Data overview', $active_language); ?>">
    <i class="bi bi-clipboard-data"></i>
</a>
<a href="<?php echo url('projects/' . $project->id . '/fields'); ?>" role="button" class="btn btn-primary btn-sm" title="<?php echo get_dialogs_simple('Configure fields', $active_language); ?>">
    <i class="bi bi-layout-three-columns"></i>
</a>

<button type="button" class=" btn btn-danger btn-sm delete_project" title="<?php echo get_dialogs_simple('Delete project', $active_language); ?>" id="<?php echo $project->id; ?>">
    <i class="bi bi-trash"></i>
</button>