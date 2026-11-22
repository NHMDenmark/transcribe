<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Comments;

use DB;

class CommentsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['index', 'show']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //$posts = Post::all();
        //return Post::where('title', 'Post Two')->get();
        //$posts = DB::select('SELECT * FROM posts');
        //$posts = Post::orderBy('title','desc')->take(1)->get();
        //$posts = Post::orderBy('title','desc')->get();

        $groups = Comments::where( 'status', '=', 1 )->latest('created_at')->groupBy('specimen_id','field_id')->paginate(10);

        foreach($groups as $group)
        {
            $group['comments'] =  Comments::where( 'status', '=', 1 )->where('specimen_id', '=', $group->specimen_id)->where('field_id', '=', $group->field_id)->orderBy('created_at','asc')->get();
        }

        return view('comments.index')->with('groups', $groups);
    }
}
