<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Specimen;
use App\Collaborative_fields;
use App\Validate_fields;
use App\Specify_fields;


use App\Models\Week_report;
use App\Models\User;
use App\Models\Comments;
use App\Models\Project;
use DB;
use Auth;

class PagesController extends Controller
{
	public
	function index()
	{

		$title = 'Welcome To Collaborative Platform!';
		//return view('pages.index', compact('title'));

		$projects = Project::orderBy('created_at', 'desc')->where('active', '=', 1)->get();
		foreach ($projects as $key => $project) {
			$project->specimens_count;
			$project->fields_completed;

			$project->possible_fields;

			$all_possible_fields = $project->specimens_count * $project->possible_fields;
			//echo $all_possible_fields.'<br>';	
			//echo $completed_fields.'<br>';
			if ($all_possible_fields > 0) {

				$completed_percent = $project->fields_completed / $all_possible_fields * 100;
			} else {
				$completed_percent = 0;
			}
			//echo $completed_percent.'<br>';

			$projects[$key]->specimens = $project->specimens_count;
			$projects[$key]->fields = $all_possible_fields;
			$projects[$key]->fields_completed = $project->fields_completed;
			$projects[$key]->completed_percent = $completed_percent;
			if ($completed_percent == 100) {
				$projects->forget($key);
			}
		}
		$projects = $projects->sortBy('completed_percent');


		$top_contributors = $this->top_contributors();

		$year_week = app(\App\Services\WeeklyReport::class)->lastWeek()[2];
		$top_contributors_week = $this->top_contributors_week($year_week);


		$recent_comments = Comments::where('status', '=', 1)->orderBy('id', 'desc')->limit(5)->get();
		foreach ($recent_comments as $recent_comment) {
			
			$comment_images = $recent_comment->specimen->images ?? null;
			
			$images = explode('|', $comment_images);

			$recent_comment->thumbnail = $images[0];
		}

		return view('pages.index')->with('projects', $projects)->with('top_contributors', $top_contributors)->with('top_contributors_week', $top_contributors_week)->with('recent_comments', $recent_comments);
	}


	public function top_contributors()
	{
		$top_contributors = User::where(DB::raw("validated + rejected + submitted"), '!=', 0)->orderBy(DB::raw("validated + rejected + submitted"), 'desc')->paginate(10);

		return $top_contributors;
	}

	public function top_contributors_week($year_week)
	{
		$top_contributors_week = Week_report::orderBy('total', 'DESC')->where('year_week', '=', $year_week)->where('total', '!=', 0)->paginate(10);

		return $top_contributors_week;
	}
}
