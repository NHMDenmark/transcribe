<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Specify_fields;
use App\Models\Validate_fields;
use Illuminate\Support\Facades\DB;

/**
 * The progress figures of the projects (number of specimens, fields that can be filled, fields completed), stored on
 * the project so that lists do not have to count them on every page. Recalculated when something changes (a
 * submission, an import, a change of the project's fields) for the projects concerned, and for all of them every night
 * (platform:update-project-stats), in case a change was made by a path that does not recalculate.
 */
class ProjectStats
{
    /** @param  array<int>|null  $projectIds  only these projects; null = all of them */
    public function update(?array $projectIds = null): void
    {
        $projects = Project::orderBy('created_at', 'desc');
        if ($projectIds !== null) {
            $projects->whereIn('id', $projectIds);
        }

        foreach ($projects->get() as $project) {
            $project_specimens = DB::table('specimens')->where('project_id', '=', $project['id'])->pluck('specimen_id');
            $project->specimens_count = count($project_specimens);
            $project_fields = $project->fields()->get()->pluck('id')->toArray();

            $possible_fields = DB::table('fields')->whereIn('id', $project_fields)->where('parent', '=', 0)->pluck('id');
            $project->possible_fields = $possible_fields->count();
            $Valid_fields = Validate_fields::where('status', '=', 1)->whereIn('specimen_id', $project_specimens)->whereIn('field_id', $possible_fields)->groupBy('specimen_id', 'field_id')->get();
            $Valid_fields_count = array();
            foreach ($Valid_fields as $Valid_field) {
                $Valid_fields_count[] = $Valid_field->specimen_id . '_' . $Valid_field->field_id;
            }
            // Check which specimens and fields have been imported from the collection database for this project to exclude them
            $specify_fields = Specify_fields::where('status', '>', 0)->whereIn('specimen_id', $project_specimens)->whereIn('field_id', $possible_fields)->where('value', '!=', '')->where('value', 'NOT LIKE', '%|')->where('value', 'NOT LIKE', '|%')->get();
            foreach ($specify_fields as $Valid_field) {
                $Valid_fields_count[] = $Valid_field->specimen_id . '_' . $Valid_field->field_id;
            }

            $project->fields_completed = count(array_unique($Valid_fields_count));
            $project->save();
        }
    }

    /** The projects a specimen belongs to: the ones whose figures a submission on it can change. */
    public function projectsOfSpecimen($specimenId): array
    {
        return DB::table('specimens')->where('specimen_id', $specimenId)->pluck('project_id')->unique()->filter()->values()->all();
    }
}
