<?php
namespace App\Http\Controllers;

use App\Events\PostComment;
use App\Events\PostLike;
use App\Events\SendNotification;
use App\Models\Comment;
use App\Models\CommentLikes;
use App\Models\Like;
use App\Models\LiveStream;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostUpload;
use App\Models\Subscription;
use App\Models\Payment;
use App\User;
use App\Models\UserData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Abraham\TwitterOAuth\TwitterOAuth;
use Dompdf\Dompdf;
use App;
use PDF;
use View;

class PostController extends Controller
{

    private $language;
    private $user_meta;
    function __construct(){
        $this->language = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Translations/index.php";
        $this->user_meta = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Meta/countycode.php";
    }

    // public profile
    public function get_public_profile_posts(Request $request, $usname)
    {
        $auth_user = false;
        if($request->bearerToken())
        {
        $auth_user = JWTAuth::parseToken()->authenticate();
        }
        $user = User::where("username", $usname)->first();
        $id = $user->id;

        //      $posts = Post::join("users", "posts.user_id", "users.id")
        //     ->join("user_data", "posts.user_id", "user_data.user_id")
        //     ->leftJoin("post_plans" , "posts.id", "post_plans.post_id")
        //     ->select("posts.*, JSON_ARRAYAGG(post_plans.access_level),users.username, user_data.profile_image, user_data.display_name")
        //     ->select("posts.*", "post_plans.access_level","users.username", "user_data.profile_image", "user_data.display_name")
        //     ->where("posts.user_id", $id)
        //     ->orderBy("id", "desc")
        //     // ->groupBy("id")
        //     ->get();

        DB::enableQueryLog();

        $posts = DB::select(DB::RAW("select posts.* ,GROUP_CONCAT(post_plans.access_level) as access_level, GROUP_CONCAT(user_plans.plan_name) as plan_names, GROUP_CONCAT(user_plans.amount) 
                                    as plan_price, users.username, user_data.profile_image, user_data.display_name,
                                    ppua.options as userselected, ppo.end_date, ppo.is_end_date, ppo.is_single_option, ppo.post_options
                                    from posts inner join users on posts.user_id = users.id 
                                    inner join user_data on  posts.user_id = user_data.user_id
                                    left Join post_plans on posts.id = post_plans.post_id
                                    left Join user_plans on user_plans.id = post_plans.access_level
                                    left JOIN post_poll_options ppo on ppo.post_id = posts.id
                                    left join post_poll_user_answers ppua ON ppua.post_id = posts.id and ppua.user_id = $id
                                    where posts.user_id = $id AND posts.status = 1 group by posts.id
                                    order by posts.id desc"));

        // $qLog = DB::getQueryLog();
        
        // echo "<pre>"; 
        // print_r($qLog); die();

        foreach ($posts as $p) {
            $p->access_level = $holds = explode(',', $p->access_level);
            $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;
            $p->images = PostUpload::where("post_id", $p->id)->pluck('file');
            foreach ($p->images as $key => $value) {
                $p->images[$key] = url('/') . '/public/storage/post-images/' . $value;
            }

            $p->ext = PostUpload::where("post_id", $p->id)->pluck('ext');
            foreach ($p->ext as $key => $value) {
                if ($p->ext[$key] == "mp4" || $p->ext[$key] == "ogv" || $p->ext[$key] == "webm") {
                    $p->ext[$key] = "video";
                } else {
                    $p->ext[$key] = "image";
                }
            }

            // foreach ($p->uploads as $up) {
            //     $up->file = url('/') . '/public/storage/post-images/' . $up->file;
            //     //$up->created = $up->created_at->diffForHumans();
            //     $up->created = date("d-M-Y", strtotime($up->created_at));
            // }
       
            $p->comments = Comment::select("comments.*", "users.username", "user_data.profile_image")
                ->join("users", "users.id", "comments.user_id")
                ->join("user_data", "users.id", "user_data.user_id")
                ->where(["comments.post_id" => $p->id])
                ->orderby("comments.id", "asc")
                ->get();
            foreach ($p->comments as $comment) {
                $comment->profile_image = url('/') . '/public/storage/profile-images/' . $comment->profile_image;
                $comment->created = $comment->created_at->diffForHumans();
                $comment->total_likes = count(CommentLikes::where("comment_id", $comment->id)->get());
                if($auth_user==false)
                {
                    $comment->user_liked_comment = 0;
                }
                else
                {
                    $comment->user_liked_comment = count(CommentLikes::where(["comment_id" => $comment->id, "user_id" => $auth_user->id])->get());
                }
            }

            $p->total_comments = count($p->comments);
            $p->total_likes = count(Like::where("post_id", $p->id)->get());
            if($auth_user==false)
            {
                $p->user_liked = 0;
            }
            else
            {
                $p->user_liked = count(Like::where(["post_id" => $p->id, "user_id" => $auth_user->id])->get());

            }
        }
        return response()->json(['status' => 'success', 'res' => $posts], 200);
    }
    // @my profile page
    public function get_profile_posts($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $userID = $user->id;

        DB::enableQueryLog();
    
        $posts = Post::join("users", "posts.user_id", "users.id")
            ->join("user_data", "posts.user_id", "user_data.user_id")
            ->leftJoin("post_plans" , "posts.id", "post_plans.post_id")
            ->leftJoin("post_poll_options", "posts.id", "post_poll_options.post_id")
            ->leftJoin('post_poll_user_answers AS ppa', 'posts.id', 'ppa.post_id')
            ->select(DB::RAW('posts.* ,GROUP_CONCAT(post_plans.access_level) as access_level, users.username, user_data.profile_image, user_data.display_name, post_poll_options.post_options, ppa.options as userselected, post_poll_options.end_date, post_poll_options.is_end_date, post_poll_options.is_single_option'))
            ->groupBy("id")
            ->with("uploads")
            ->where("posts.user_id", $id)
            ->where("posts.status", 1)
            ->orderBy("isPinned", "desc")
            ->orderBy("id", "desc")->get();

        // $qLog = DB::getQueryLog();
        
        // echo "<pre>"; 
        // print_r($qLog); die();


        foreach ($posts as $p) {
            $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;
            $p->images = PostUpload::where("post_id", $p->id)->pluck('file');
            foreach ($p->images as $key => $value) {
                $p->images[$key] = url('/') . '/public/storage/post-images/' . $value;
            }

            $p->ext = PostUpload::where("post_id", $p->id)->pluck('ext');
            foreach ($p->ext as $key => $value) {
                if ($p->ext[$key] == "mp4" || $p->ext[$key] == "ogv" || $p->ext[$key] == "webm") {
                    $p->ext[$key] = "video";
                } else if ($p->ext[$key] == "mp3") {
                    $p->ext[$key] = "audio";
                } else {
                    $p->ext[$key] = "image";
                }
            }
            $p->thumb = PostUpload::where("post_id", $p->id)->pluck('thumbnail');
            if ($p->thumb != '') {
                $p->thumb = url('/') . '/public/storage/post-images/' . $p->thumb;
            }
            foreach ($p->uploads as $up) {
                $up->file = url('/') . '/public/storage/post-images/' . $up->file;
                //$up->created = $up->created_at->diffForHumans();
                $up->created = date("d-M-Y", strtotime($up->created_at));
            }
            $p->comments = Comment::select("comments.*", "users.username", "user_data.profile_image")
                ->join("users", "users.id", "comments.user_id")
                ->join("user_data", "users.id", "user_data.user_id")
                ->where(["post_id" => $p->id])
                ->orderby("comments.id", "asc")
                ->get();
            foreach ($p->comments as $comment) {
                $comment->profile_image = url('/') . '/public/storage/profile-images/' . $comment->profile_image;
                $comment->created = $comment->created_at->diffForHumans();
                $comment->total_likes = count(CommentLikes::where("comment_id", $comment->id)->get());
                $comment->user_liked_comment = count(CommentLikes::where(["comment_id" => $comment->id, "user_id" => $id])->get());
            }
            $p->total_comments = count($p->comments);
            $p->total_likes = count(Like::where("post_id", $p->id)->get());
            $p->user_liked = count(Like::where(["post_id" => $p->id, "user_id" => $id])->get());
        }
        return response()->json(['status' => 'success', 'res' => $posts], 200);
    }
    // @home page
    public function get_posts(Request $request)
    {   
        
            $auth_user = JWTAuth::parseToken()->authenticate();
            if ($auth_user) {
                $auth_user_id = $auth_user->id;
            }
            $auth_user = JWTAuth::parseToken()->authenticate();
            $user = User::find($auth_user->id);
            $subscribed_creators = Subscription::where(["user_id" => $user->id, "status" => "active"])->pluck('creator_id');
            DB::enableQueryLog(); 
            $query = "select posts.* ,post_plans.access_level as access_level, users.username, user_data.profile_image, user_data.display_name,
                      ppua.options as userselected, ppo.end_date, ppo.is_end_date, ppo.is_single_option, ppo.post_options
                      from `posts` inner join `user_followers` on `user_followers`.`following` = `posts`.`user_id` 
                      left join `subscription` on `subscription`.`user_id` = $auth_user->id
                      left JOIN post_poll_options ppo on ppo.post_id = posts.id
                      left join post_poll_user_answers ppua ON ppua.post_id = posts.id and ppua.user_id = $auth_user->id 
                      inner join `user_data` on `posts`.`user_id` = `user_data`.`user_id` 
                      inner join `users` on `users`.`id` = `posts`.`user_id` 
                      inner join `post_plans` on `post_plans`.`post_id` = `posts`.`id` 
                      where `posts`.`status` = 1 AND (`posts`.`user_id` = ".$auth_user->id." or `user_followers`.`user_id` = ".$auth_user->id.") 
                      and (`post_plans`.`access_level` = subscription.plan_id or `post_plans`.`access_level` = 0 or `post_plans`.`access_level` = -1)";

            if (isset($request->search)) {

               $query .= "posts.message LIKE '%".$request->search."%'";
            }

            
            $query .= " group by `post_plans`.`post_id`  order by `id` desc";
            
            
            $posts = DB::select(DB::Raw($query));
            
            // $query = DB::getQueryLog();
            // $query = end($query);
            // print_r($query);  
            
            foreach ($posts as $p) {
                $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;
                $p->images = PostUpload::where("post_id", $p->id)->get();
                foreach ($p->images as $key => $value) {
                    $p->images[$key] = url('/') . '/public/storage/post-images/' . $value->file;
                    if(property_exists($value,'ext'))
                    {
                        $p->ext[$key] = $value->ext;
                    }
                    else
                    {
                        continue;
                    }
                }
                if(!property_exists($p,'ext'))
                    {
                        continue;
                    }
                foreach ($p->ext as $key => $value) {
                    if ($p->ext[$key] == "mp4" || $p->ext[$key] == "ogv" || $p->ext[$key] == "webm") {
                        $p->ext[$key] = "video";
                    } 
                    else if ($p->ext[$key] == "mp3" || $p->ext[$key] == "wav") {
                        $p->ext[$key] = "audio";
                    }
                    else {
                        $p->ext[$key] = "image";
                    }
                }

                // foreach ($p->images as $up) {
                //     print_r($up); die();
                //     $up->file = url('/') . '/public/storage/post-images/' . $up->file;
                //     $up->thumbnail = $up->thumbnail ? url('/') . '/public/storage/post-images/' . $up->thumbnail : null;
                //     $up->created = date("d-M-Y", strtotime($up->created_at));
                // }
                $p->comments = Comment::select("comments.*", "users.username", "user_data.profile_image")
                    ->join("users", "users.id", "comments.user_id")
                    ->join("user_data", "users.id", "user_data.user_id")
                    ->where(["post_id" => $p->id])
                    ->orderby("comments.id", "asc")
                    ->get();
                foreach ($p->comments as $comment) {
                    $comment->profile_image = url('/') . '/public/storage/profile-images/' . $comment->profile_image;
                    $comment->created = $comment->created_at->diffForHumans();
                    $comment->total_likes = count(CommentLikes::where("comment_id", $comment->id)->get());
                    $comment->user_liked_comment = count(CommentLikes::where(["comment_id" => $comment->id, "user_id" => $user->id])->get());
                }
                $p->total_comments = count($p->comments);
                $p->total_likes = count(Like::where("post_id", $p->id)->get());
                $p->user_liked = count(Like::where(["post_id" => $p->id, "user_id" => $user->id])->get());
            }
            return response()->json(['status' => 'success', 'res' => $posts], 200);
        

    }

    public function get_single_post($id)
    {
        $posts = Post::where("id", $id)
            ->first();

        $uploads = PostUpload::where("post_id", $id)->get();

        foreach ($uploads as $up) {

            $up->file = url('/') . '/public/storage/post-images/' . $up->file;
            $up->created = date("d-M-Y", strtotime($up->created_at));
        }

        /*customization*/
        $auth_user = JWTAuth::parseToken()->authenticate();
        if ($auth_user) {
            $auth_user_id = $auth_user->id;
        }
        $auth_user = JWTAuth::parseToken()->authenticate();
        $user = User::find($auth_user->id);
        $subscribed_creators = Subscription::where(["user_id" => $user->id, "status" => "active"])->pluck('creator_id');
        
        $posts = Post::join("user_followers", "user_followers.following", "posts.user_id")
        ->leftJoin('subscription',"subscription.creator_id" , "user_followers.following")
        ->join("user_data", "posts.user_id", "user_data.user_id")
        ->join("users", "users.id", "posts.user_id")
        ->join("post_plans", "post_plans.post_id", "posts.id")
        ->select(DB::RAW('posts.* ,GROUP_CONCAT(post_plans.access_level) as access_level, users.username, user_data.profile_image, user_data.display_name'))
        ->where(function($query) use ($auth_user){
            $query->where("posts.user_id",'=',$auth_user->id)
            ->orWhere("user_followers.user_id",'=',$auth_user->id);
        })
        ->where(function($query){
            $query->where('post_plans.access_level','=','subscription.plan_id')
            ->orWhere('post_plans.access_level','=','0')
            ->orWhere('post_plans.access_level','=','-1');
        })
        ->groupBy("post_plans.post_id")
        ->with('uploads');

    
    
        if (isset($request->search)) {
            $posts = $posts->where("posts.message", "like", "%$request->search%");
        }
    
        $posts = $posts->orderBy("id", "desc")->get();
        //  $query = DB::getQueryLog();
        // $query = end($query);
        // print_r($query);    die();
        echo '<pre>';
        print_r($posts); die();
        foreach ($posts as $p) {
            $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;
            $p->images = PostUpload::where("post_id", $p->id)->pluck('file');
            foreach ($p->images as $key => $value) {
                $p->images[$key] = url('/') . '/public/storage/post-images/' . $value;
            }

            $p->ext = PostUpload::where("post_id", $p->id)->pluck('ext');
            foreach ($p->ext as $key => $value) {
                if ($p->ext[$key] == "mp4" || $p->ext[$key] == "ogv" || $p->ext[$key] == "webm") {
                    $p->ext[$key] = "video";
                } else {
                    $p->ext[$key] = "image";
                }
            }

            foreach ($p->uploads as $up) {
                $up->file = url('/') . '/public/storage/post-images/' . $up->file;
                $up->thumbnail = $up->thumbnail ? url('/') . '/public/storage/post-images/' . $up->thumbnail : null;
                //$up->created = $up->created_at->diffForHumans();
                $up->created = date("d-M-Y", strtotime($up->created_at));
            }
            $p->comments = Comment::select("comments.*", "users.username", "user_data.profile_image")
                ->join("users", "users.id", "comments.user_id")
                ->join("user_data", "users.id", "user_data.user_id")
                ->where(["post_id" => $p->id])
                ->orderby("comments.id", "asc")
                ->get();
            foreach ($p->comments as $comment) {
                $comment->profile_image = url('/') . '/public/storage/profile-images/' . $comment->profile_image;
                $comment->created = $comment->created_at->diffForHumans();
                $comment->total_likes = count(CommentLikes::where("comment_id", $comment->id)->get());
                $comment->user_liked_comment = count(CommentLikes::where(["comment_id" => $comment->id, "user_id" => $user->id])->get());
            }
            $p->total_comments = count($p->comments);
            $p->total_likes = count(Like::where("post_id", $p->id)->get());
            $p->user_liked = count(Like::where(["post_id" => $p->id, "user_id" => $user->id])->get());
        }
        return response()->json(['status' => 'success', 'res' => $posts], 200);

      //  return response()->json(['status' => 'success', 'res' => $posts, 'uploads' => $uploads], 200);
    }

    public function get_single_post_details($id)
    {
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            
            DB::enableQueryLog(); 
            $auth_user = JWTAuth::parseToken()->authenticate();
            $posts = Post::join("users", "posts.user_id", "users.id")
                ->join("user_data", "posts.user_id", "user_data.user_id")
                ->select("posts.*", "users.username", "user_data.profile_image", "user_data.display_name")
                ->with("uploads")
                ->where("posts.id", $id)
                ->orderBy("id", "desc")->get();
            $query = DB::getQueryLog();
            $query = end($query);
            print_r($query);  
            die();
            foreach ($posts as $p) {
                $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;
                $p->images = PostUpload::where("post_id", $p->id)->pluck('file');
                foreach ($p->images as $key => $value) {
                    $p->images[$key] = url('/') . '/public/storage/post-images/' . $value;
                }

                $p->ext = PostUpload::where("post_id", $p->id)->pluck('ext');
                foreach ($p->ext as $key => $value) {
                    if ($p->ext[$key] == "mp4" || $p->ext[$key] == "ogv" || $p->ext[$key] == "webm") {
                        $p->ext[$key] = "video";
                    } else {
                        $p->ext[$key] = "image";
                    }
                }

                foreach ($p->uploads as $up) {
                    $up->file = url('/') . '/public/storage/post-images/' . $up->file;
                    //$up->created = $up->created_at->diffForHumans();
                    $up->created = date("d-M-Y", strtotime($up->created_at));
                }
                $p->comments = Comment::select("comments.*", "users.username", "user_data.profile_image")
                    ->join("users", "users.id", "comments.user_id")
                    ->join("user_data", "users.id", "user_data.user_id")
                    ->where(["post_id" => $p->id])
                    ->orderby("comments.id", "asc")
                    ->get();
                foreach ($p->comments as $comment) {
                    $comment->profile_image = url('/') . '/public/storage/profile-images/' . $comment->profile_image;
                    $comment->created = $comment->created_at->diffForHumans();
                    $comment->total_likes = count(CommentLikes::where("comment_id", $comment->id)->get());
                    $comment->user_liked_comment = count(CommentLikes::where(["comment_id" => $comment->id, "user_id" => $auth_user->id])->get());
                }
                $p->total_comments = count($p->comments);
                $p->total_likes = count(Like::where("post_id", $p->id)->get());
                $p->user_liked = count(Like::where(["post_id" => $p->id, "user_id" => $auth_user->id])->get());
            }
            return response()->json(['status' => 'success', 'res' => $posts], 200);
        }
        else
        {

        }    
    }

    public function update_post(Request $request)
    {
        $data = $request->all();
        $images = $request->file('newImage');
       
        if ($request->file('newImage')) {
            $validator = Validator::make($data, [
                'newImage' => 'required',
                'newImage.*' => ['mimes:jpeg,jpg,png,gif,JPG,JPEG,PNG,GIF,mp4,ogx,oga,ogv,ogg,webm'],
            ]);
            if ($validator->fails()) {
                $validation_msgs = $validator->getMessageBag()->all();
                if (isset($validation_msgs[0])) {
                    return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);
                }
            } else {
              
                if(isset($data['oldimage']) && count($data['oldimage'])>0){
                    $oldupdate = $data['oldUpdate'];
                    $oldimage  = $data['oldimage'];
                    foreach($oldimage as $key=>$value)
                    {
                        if(array_search($value,$oldupdate)==false)
                        { 
                            $imageArray = explode('/',$value);
                            $image = $imageArray[count($imageArray)-1];
                            PostUpload::where('file',$image)->delete();
                        
                        }
                    }
                }
                $p = Post::find($request->id);
                $p->message = $request->message;
                $p->save();

                foreach ($request->file('newImage') as $file) {
                    //   echo $file->getMimeType();die;
                    $ext = $file->getClientOriginalExtension();
                    $front_image = time() . "_" . $file->getClientOriginalName();
                    $path = $file->storeAs('public/post-images', $front_image);
                    $pu = new PostUpload();
                    $pu->post_id = $p->id;
                    $pu->file = $front_image;
                    $pu->ext = $ext;
                    $pu->save();
                }

                //event(new PostLike());

            }
        } else {
            $p = Post::find($request->id);
            $p->title = $request->title;
            $p->message = $request->message;

             if(isset($data['oldimage']) && count($data['oldimage'])>0){
                    $oldupdate = $data['oldUpdate'];
                    $oldimage  = $data['oldimage'];
                    foreach($oldimage as $key=>$value)
                    {
                        if(array_search($value,$oldupdate)==false)
                        { 
                            $imageArray = explode('/',$value);
                            $image = $imageArray[count($imageArray)-1];
                            PostUpload::where('file',$image)->delete();
                        
                        }
                    }
                }

            $p->save();
        }
        return response()->json(['status' => 'success', 'message' => $this->language[$request->lang]['MSG062']], 200);
    }

    public function add_comment(Request $request)
    {
        $c = new Comment();
        $c->user_id = $request->userId;
        $c->post_id = $request->postId;
        $c->comment = $request->comment;
        $c->save();

        $post = Post::find($request->postId);
        $c->rec_id = $post->user_id;

        event(new PostComment($c));

        $userSender = User::find($request->userId);

        if ($request->userId != $post->user_id) {
            $user = User::find($post->user_id);
            if ($user->settings() && $user->settings()->notifications->site->comment) {
                $n = new Notification();
                $n->sender_id = $request->userId;
                $n->rec_id = $post->user_id;
                $n->notification = $userSender->username . '{{COMMENTED}}';
                $n->date = time();
                $n->url = "user/post/" . $request->postId;
                $n->type = 1;
                $n->save();
                event(new SendNotification($n));
            }

            if($user->settings() && $user->settings()->email->comments){
                $email = $user->email;
                header("X-Node: localhost");
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;
                $sub = "";
                $contactpageLink = env("FRONT_URL").'/contact';
                $profilepage = env("FRONT_URL").'/user/profile'; 
                $username = $user->username;
                $sendername = $userSender->username;                                       
                $type = "";

                if($request->lang == 'en'){
                    $type = "commented on";
                    $sub = "Check your Equiconx account for recent new activities";
                    $template_file = "resources/views/notification_email.blade.php";
                }
                if($request->lang == 'de'){
                    $type = "kommentiert";
                    $sub = "Neue Aktivitäten auf deinem Equiconx Profil";
                    $template_file = "resources/views/german/notification_email.blade.php";
                    $contactpageLink = env("FRONT_URL").'/'.$request->lang.'/contact';
                    $profilepage = env("FRONT_URL").'/'.$request->lang.'/user/profile'; 
                }

                $msg = file_get_contents($template_file);
                $msg = str_replace("__username", ucfirst($username), $msg);
                $msg = str_replace("__personname", ucfirst($sendername), $msg);
                $msg = str_replace("__profilepage", $profilepage, $msg);
                $msg = str_replace("__type", $type, $msg);
                $msg = str_replace("__contactpage", $contactpageLink, $msg);

                mail($email, $sub, $msg, $headers);
            }
        }

        return response()->json(['status' => 'success', 'message' => $this->language[$request->lang]['MSG069']], 200);
    }

    public function delete_post($id)
    {
        $c = Post::find($id);
        $pu = PostUpload::where("post_id", $id)->get();
        foreach ($pu as $p) {
            $filepath = storage_path('app/public/post-images/' . $p->file);
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        $c->delete();
        return response()->json(['status' => 'success', 'message' => $this->language[request()->lang]['MSG070']], 200);
    }

    public function delete_post_image($id)
    {
        $c = PostUpload::find($id);
        $filepath = storage_path('app/public/post-images/' . $c->file);
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $c->delete();
        return response()->json(['status' => 'success', 'message' => $this->language[request()->lang]['MSG071']], 200);
    }


    public function delete_comment($id)
    {
        $c = Comment::find($id)->delete();
        event(new PostLike());
        return response()->json(['status' => 'success', 'message' => $this->language[request()->lang]['MSG072']], 200);
    }

    public function update_comment(Request $request)
    {
        $c = new Comment();

        if ($request->has('id') && $request->has('comment')) {
            $c->comment = $request->comment;
            DB::update('UPDATE comments SET comment = ? WHERE id = ? ;', [$c->comment, $request->id]);
            return response()->json(['status' => 'success', 'message' => $this->language[$request->lang]['MSG073']], 200);
        } else {
            return response()->json(['status' => 'failed', 'message' => $this->language[$request->lang]['MSG074']], 200);
        }
    }

    public function reply_comment(Request $request)
    {
        $c = new Comment();
        // if ($request->has('user_id') && $request->has('parent_id') && $request->has('comment')) {
        $c->user_id = $request->user_id;
        $c->parent_id = $request->parent_id;
        $c->comment = $request->comment;

        if ($c->user_id && $c->parent_id && $c->comment) {
            $c->save();

            return response()->json(['status' => 'success', 'message' => $this->language[$request->lang]['MSG075']], 200);
        } else {
            return response()->json(['status' => 'failed', 'message' => $this->language[$request->lang]['MSG076']], 200);
        }
    }


    public function like_unlike_post(Request $request)
    {
        $request->userId = JWTAuth::parseToken()->authenticate()->id;
        if ($request->status == 1) {
            $c = new Like();
            $c->user_id = $request->userId;
            $c->post_id = $request->postId;
            $c->save();

            $post = Post::find($request->postId);
            $c->rec_id = $post->user_id;
            $userSender = User::find($request->userId);
            if ($request->userId != $post->user_id) {
                $user = User::find($post->user_id);
                if ($user->settings() && $user->settings()->notifications->site->like) {
                    $n = new Notification();
                    $n->sender_id = $request->userId;
                    $n->rec_id = $post->user_id;
                    $n->notification = $userSender->username . " {{LIKED}}";
                    $n->date = time();
                    $n->url = "user/post/" . $request->postId;
                    $n->type = 2;
                    $n->save();
                    event(new SendNotification($n));
                }


                if($user->settings() && $user->settings()->email->likes){
                    
                    $email = $user->email;
                    header("X-Node: localhost");
                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;
                    $sub = "";
                    $contactpageLink = env("FRONT_URL").'/contact';
                    $profilepage = env("FRONT_URL").'/user/profile'; 
                    $username = $user->username;
                    $sendername = $userSender->username;                                       
                    $type="";
                    $template_file="";

                    if($request->lang == 'en'){
                        $type = "likes";
                        $sub = "Check your Equiconx account for recent new activities";
                        $template_file = "resources/views/notification_email.blade.php";
                    }
                    if($request->lang == 'de'){
                        $type = "mag";
                        $sub = "Neue Aktivitäten auf deinem Equiconx Profil";
                        $template_file = "resources/views/german/notification_email.blade.php";
                        $contactpageLink = env("FRONT_URL").'/'.$request->lang.'/contact';
                        $profilepage = env("FRONT_URL").'/'.$request->lang.'/user/profile'; 
                    }

                    $msg = file_get_contents($template_file);
                    $msg = str_replace("__username", ucfirst($username), $msg);
                    $msg = str_replace("__personname", ucfirst($sendername), $msg);
                    $msg = str_replace("__profilepage", $profilepage, $msg);
                    $msg = str_replace("__type", $type, $msg);
                    $msg = str_replace("__contactpage", $contactpageLink, $msg);

                    mail($email, $sub, $msg, $headers);
                }
            }
        } else {
            Like::where(["user_id" => $request->userId, "post_id" => $request->postId])->delete();
        }
        event(new PostLike());

        return response()->json(['status' => 'success'], 200);
    }

    public function get_post_likes($post_id)
    {
        $auth_user = JWTAuth::parseToken()->authenticate();
        $post = Post::find($post_id);
        if ($auth_user) {
            $auth_user_id = $auth_user->id;
        }
        $total_likes = Like::join("users", "likes.user_id", "users.id")
            ->join("user_data", "likes.user_id", "user_data.user_id")
            ->select("likes.*", "users.username", "user_data.profile_image", "user_data.display_name")
            ->where("likes.post_id", $post_id)
            ->orderBy("likes.id", "desc")->get();
        $user_liked = count(Like::where(["post_id" => $post_id, "user_id" => $auth_user_id])->get());

        foreach ($total_likes as $t) {
            $t->profile_image = url('/') . '/public/storage/profile-images/' . $t->profile_image;
        }

        $post->total_likes = $total_likes;
        $post->user_liked = $user_liked;
        return response()->json(['status' => 'success', 'res' => $post], 200);
    }

    public function get_comment_likes($id)
    {
        $post = Comment::find($id);
        $auth_user = JWTAuth::parseToken()->authenticate();


        if ($auth_user) {
            $auth_user_id = $auth_user->id;
        }
        $total_likes = count(CommentLikes::where("comment_id", $id)->get());
        $user_liked = count(CommentLikes::where(["comment_id" => $id, "user_id" => $auth_user_id])->get());
        $post->total_likes = $total_likes;
        $post->user_liked = $user_liked;
        return response()->json(['status' => 'success', 'res' => $post], 200);
    }

    public function like_unlike_comment(Request $request)
    {
        if ($request->status == 1) {
            $c = new CommentLikes();
            $c->user_id = $request->userId;
            $c->comment_id = $request->commentId;
            $c->save();
        } else {
            CommentLikes::where(["user_id" => $request->userId, "comment_id" => $request->commentId])->delete();
        }

        event(new PostLike());

        return response()->json(['status' => 'success', 'message' => $this->language[$request->lang]['MSG069']], 200);
    }
    public function pin_post()
    {
        $postId = request()->id;
        $post = Post::find($postId);
        $post->isPinned = $post->isPinned ? $post->isPinned + 1 : 1;
        $post->save();

        return response()->json(['status' => 'success', 'message' => $this->language[request()->lang]['MSG078']], 200);
    }
    public function unpin_post()
    {
        $postId = request()->id;
        $post = Post::find($postId);
        $post->isPinned = 0;
        $post->save();

        return response()->json(['status' => 'success', 'message' => $this->language[request()->lang]['MSG079']], 200);
    }

    public function validateLiveStream()
    {
        $auth_user = JWTAuth::parseToken()->authenticate();
        $starId = request()->starId;
        $eventId = request()->eventId;

        $isSubscribed = Subscription::where(["user_id" => $auth_user->id, "creator_id" => $starId, "status" => "active"])->pluck('creator_id');
        if (!$isSubscribed) {
            return response()->json(['message' => $this->language[request()->lang]['MSG080']], 400);
        }

        $liveStream = LiveStream::where(["star_id" => $starId, "streaming_id" => $eventId, "status" => "live"])->first();

        if (!$liveStream) {
            return response()->json(['message' => $this->language[request()->lang]['MSG081']], 400);
        }
        return response()->json(['status' => 'success', 'message' => $this->language[request()->lang]['MSG079'], "data" => $liveStream], 200);
    }

    public function get_post_comment($post_id)
    {
        $auth_user = JWTAuth::parseToken()->authenticate();
        if ($auth_user) {
            $auth_user_id = $auth_user->id;
        }
        $comments = Comment::select("comments.*", "users.username", "user_data.profile_image")
            ->join("users", "users.id", "comments.user_id")
            ->join("user_data", "users.id", "user_data.user_id")
            ->where(["post_id" => $post_id])
            ->orderby("comments.id", "asc")
            ->get();
          
        if (is_array($comments) || is_object($comments)) {
            foreach ($comments as $comment) {
                $comment->profile_image = url('/') . '/public/storage/profile-images/' . $comment->profile_image;
                $comment->created = $comment->created_at->diffForHumans();
                $comment->total_likes = count(CommentLikes::where("comment_id", $comment->id)->get());
                $comment->user_liked_comment = count(CommentLikes::where(["comment_id" => $comment->id, "user_id" => $auth_user->id])->get());
                $res = '';
               $res = Comment::select("comments.*", "users.username", "user_data.profile_image")
                ->join("users", "users.id", "comments.user_id")
                ->join("user_data", "users.id", "user_data.user_id")
                ->where(["parent_id" => $comment->id])
                ->orderby("comments.id", "asc")
                ->get();
                foreach ($res as $reply) {
                    $reply->profile_image = url('/') . '/public/storage/profile-images/' . $reply->profile_image;
                    $reply->created = $reply->created_at->diffForHumans();
                    $reply->total_likes = count(CommentLikes::where("comment_id", $reply->id)->get());
                    $reply->user_liked_comment = count(CommentLikes::where(["comment_id" => $reply->id, "user_id" => $auth_user->id])->get());
                }  
                $comment->reply =  $res; 
            }
        }
        return response()->json(['status' => 'success', 'res' => $comments], 200);
    }
   public function get_single_posts($id)
    {
        //isset($_SERVER['HTTP_AUTHORIZATION'])
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $query = DB::getQueryLog();
        
            $auth_user = JWTAuth::parseToken()->authenticate();

            //DB::enableQueryLog();
            $posts = Post::join("users", "posts.user_id", "users.id")
                ->join("user_data", "posts.user_id", "user_data.user_id")
                 ->leftJoin("post_plans" , "posts.id", "post_plans.post_id")
                ->select(DB::Raw("posts.*,GROUP_CONCAT(post_plans.access_level) as access_level, users.username,user_data.profile_image, user_data.display_name"))
                ->where("posts.id", $id)
                ->where("posts.status",1)
                ->get();

        
            $postCheck = current($posts)[0];

            $postaccessArray = explode(',',$postCheck->access_level);
            if(in_array('-1',$postaccessArray))
            {
                $hasAccess = 1;
            }
            else
            {
                if($postCheck->user_id==$auth_user->id)
                {
                    $hasAccess = 1;
                }
                else
                {
                    $hasAccess = Subscription::where('user_id','=',$auth_user->id)
                    ->whereRaw("plan_id IN (".$postCheck->access_level.")")->get()->first();
        
                }

            }

            if($hasAccess){
                foreach ($posts as $p) {
                    $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;
                    $p->images = PostUpload::where("post_id", $p->id)->pluck('file');
                    foreach ($p->images as $key => $value) {
                        $p->images[$key] = env('FRONT_URL') . '/api/public/storage/post-images/' . $value;
                    }
                    
                    $p->socialPreviewImages = $p->images;

                    $p->ext = PostUpload::where("post_id", $p->id)->pluck('ext');
                    foreach ($p->ext as $key => $value) {
                        if ($p->ext[$key] == "mp4" || $p->ext[$key] == "ogv" || $p->ext[$key] == "webm") {
                            $p->ext[$key] = "video";
                        } else {
                            $p->ext[$key] = "image";
                        }
                    }

                    foreach ($p->uploads as $up) {
                        $up->file = url('/') . '/public/storage/post-images/' . $up->file;
                        //$up->created = $up->created_at->diffForHumans();
                        $up->created = date("d-M-Y", strtotime($up->created_at));
                    }
                    $p->comments = Comment::select("comments.*", "users.username", "user_data.profile_image")
                        ->join("users", "users.id", "comments.user_id")
                        ->join("user_data", "users.id", "user_data.user_id")
                        ->where(["comments.post_id" => $p->id])
                        ->orderby("comments.id", "asc")
                        ->get();
                    foreach ($p->comments as $comment) {
                        $comment->profile_image = url('/') . '/public/storage/profile-images/' . $comment->profile_image;
                        $comment->created = $comment->created_at->diffForHumans();
                        $comment->total_likes = count(CommentLikes::where("comment_id", $comment->id)->get());
                        $comment->user_liked_comment = count(CommentLikes::where(["comment_id" => $comment->id, "user_id" => $auth_user->id])->get());
                    }

                    $p->total_comments = count($p->comments);
                    $p->total_likes = count(Like::where("post_id", $p->id)->get());
                    $p->user_liked = count(Like::where(["post_id" => $p->id, "user_id" => $auth_user->id])->get());
                }
                return response()->json(['status' => 'success', 'res' => $posts, 'hasAccess' => 1], 200);
            }
            else
            {   
                 foreach ($posts as $p) {
                    $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;
                    $p->images = PostUpload::where("post_id", $p->id)->pluck('file');
                    foreach ($p->images as $key => $value) {
                        $p->images[$key] = env('FRONT_URL') . '/api/public/storage/post-images/' . $value;
                    }
                 }  
                 $p->socialPreviewImages = $p->images;  
                return response()->json(['status' => 'success', 'res' => $posts, 'hasAccess' => 0], 200);
            }
        }
        else
        {   
            $posts = Post::join("users", "posts.user_id", "users.id")
                ->join("user_data", "posts.user_id", "user_data.user_id")
                ->select("posts.*", "users.username", "user_data.profile_image", "user_data.display_name")
                ->where("posts.id", $id)
                ->get();
             foreach ($posts as $p) {
                    $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;
                    $p->socialPreviewImages = PostUpload::where("post_id", $p->id)->pluck('file');
                    foreach ($p->socialPreviewImages as $key => $value) {
                        $p->socialPreviewImages[$key] = env('FRONT_URL') . '/api/public/storage/post-images/' . $value;
                    }
                }    
            return response()->json(['status' => 'success', 'res' => $posts], 200);
        }
    }    

    public function search_posts(){
        
        $auth_user = JWTAuth::parseToken()->authenticate();
        $keyword = request()->keyword;


        DB::enableQueryLog();

        $response = DB::select(DB::RAW("select DISTINCT posts.*, users.username, user_data.display_name,user_data.profile_image from posts INNER JOIN users ON users.id = posts.user_id INNER JOIN user_data ON user_data.user_id = posts.user_id  WHERE posts.status = 1 AND posts.title LIKE '%".$keyword."%'"));
        // $res->profile_image =  url('/') . '/public/storage/profile-images/'.$res->profile_image;

        foreach($response as $res){
            // echo "<pre>";
            // print_r($res);
            // die();
            $res->profile_image =  url('/') . '/public/storage/profile-images/'.$res->profile_image;
        }

        $query = DB::getQueryLog();

        // echo "<pre>";
        // print_r($query);
        // die();

        return response()->json(['status' => 'success', 'res' => $response], 200);
    }
    public function handle_twitter(){
        $nonce = (string) (md5(microtime() . mt_rand()));
        $time = (string) time();
        $apikey = env('TWITTER_CONSUMER_KEY');
        $url =  'https://api.twitter.com/oauth/request_token';
        $params = [];
        $params['oauth_consumer_key'] = env('TWITTER_CONSUMER_KEY');
        $params['oauth_nonce'] = $nonce;
        $params['oauth_signature_method'] = 'HMAC-SHA1';
        $params['oauth_timestamp'] = "$time";
        $params['oauth_token'] = env('TWITTER_TOKEN');
        $params['oauth_version'] = '1.0';

        $callback = 'https://equiconx-api.com/api/twitter-auth';
        
        $sigBase = rawurlencode("POST")."&". rawurlencode((string) $url) . "&"
                .rawurlencode("oauth_callback=" . rawurlencode((string) $callback)
                . "&oauth_consumer_key=" . rawurlencode((string) $params['oauth_consumer_key'])
                . "&oauth_nonce=" . rawurlencode((string) $params['oauth_nonce'])
                . "&oauth_signature_method=" . rawurlencode((string) $params['oauth_signature_method'])
                . "&oauth_timestamp=" .rawurlencode((string) $params['oauth_timestamp'])
                . "&oauth_version=" . rawurlencode((string) $params['oauth_version']));
       
        $sigKey = rawurlencode(env('TWITTER_CONSUMER_SECRET'))."&";
       
        $signature = base64_encode(hash_hmac( 'sha1' , $sigBase ,$sigKey, true )); 
       
        $str = 'OAuth oauth_callback="'.$callback.'", oauth_consumer_key="'.$apikey.'", oauth_nonce="'.$nonce.'", oauth_signature="'.rawurlencode($signature).'", oauth_signature_method="HMAC-SHA1", oauth_timestamp="'.$time.'", oauth_version="1.0"';
        echo '<br/>';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://api.twitter.com/oauth/request_token");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: '.$str,'content-type: application/json'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $server_output = curl_exec ($ch);
        print_r(curl_error($ch));
        curl_close ($ch);
        print_r($server_output); 
        
        die('2');
    }
    public function resolveScheduled(){
        $date = date('Y-m-d G:i:s');
        
        DB::table('posts')->where('is_scheduled', '=', '1')->where('scheduled_datetime','<=',$date)->update(array('status' => 1,'is_scheduled'=>0,'created_at'=>$date));
    
        $result = ['status'=>'success','message'=>'Resolved successfully!'];

        return response()->json($result,200);
    }
    public function reportDownload(Request $request){
        $user_id = 0;
        if($request->bearerToken())
        {
            $auth_user = JWTAuth::parseToken()->authenticate();
            $user_id   = $auth_user->id;
        }
        else
        {
            $result = ['status'=>'error','message'=>$this->language[$request->lang]['MSG094']];

            return response()->json($result,200);
        }
        $month = $request->month;
        $year  = $request->year;
        $plan  = $request->plan;
        if($month=='00')
        {
            $month = date('m',strtotime("-1 month"));

        }
        if($year=='00')
        {
            $year  = date('Y',strtotime("-1 Year"));

        }

        $payment = Payment::select('user_data.display_name','user_plans.plan_name','payments.description','payments.amount','payments.currency','payments.created_at');
        $payment->where('payments.user_id','=',$user_id);
        $payment->where('payments.created_at','>',$year.'-'.$month.'-'.'01');
        $payment->where('payments.status','=','success');
        $payment->where('payments.type','=','income');
        $payment->join('subscription','subscription.subscription_id','payments.charge_id');
        $payment->join('user_plans','user_plans.id','subscription.plan_id');
        $payment->join('user_data','user_data.user_id','subscription.user_id');
 
        if($request->plan!=-1)
        {
           $payment->where('subscription.plan_id','=',$request->plan);
        }
        $paymentReports = $payment->get();
        $data = [];
        foreach($paymentReports as $paymentR){
            $p = ['plan'=>$paymentR->plan_name,'description'=>$paymentR->description,'display_name'=>$paymentR->display_name,'amount'=>$paymentR->amount,'currency'=>$paymentR->currency,'created_at'=>$paymentR->created_at];
            array_push($data,$p);  
        }
        
        $result = ['status'=>'success','data'=>$data,'message'=>$this->language[$request->lang]['MSG090']];

        return response()->json($result,200);

    }

    public function masterReportPdf(Request $request){
        $dompdf = new Dompdf();
       
        // instantiate and use the dompdf class
        $dompdf = new Dompdf();

        $user_id = 0;
        if($request->bearerToken())
        {
            $auth_user = JWTAuth::parseToken()->authenticate();
            $user_id   = $auth_user->id;
        }
        else
        {
            $result = ['status'=>'error','message'=>$this->language[$request->lang]['MSG094']];

            return response()->json($result,200);
        }


        $username = DB::select("select username from users where id=?",[$user_id]);
        $username = $username[0]->username;

        $month = $request->month;
        $year  = $request->year;
        $plan  = $request->plan;
        if($month=='00')
        {
            $month = date('m',strtotime("-1 month"));

        }
        if($year=='00')
        {
            $year  = date('Y',strtotime("-1 Year"));

        }

        $payment = Payment::select('user_data.display_name','user_plans.plan_name','payments.description','payments.amount','payments.currency','payments.created_at');
        $payment->where('payments.user_id','=',$user_id);
        $payment->where('payments.created_at','>',$year.'-'.$month.'-'.'01');
        $payment->where('payments.status','=','success');
        $payment->where('payments.type','=','income');
        $payment->join('subscription','subscription.subscription_id','payments.charge_id');
        $payment->join('user_plans','user_plans.id','subscription.plan_id');
        $payment->join('user_data','user_data.user_id','subscription.user_id');
 
        if($request->plan!=-1)
        {
           $payment->where('subscription.plan_id','=',$request->plan);
        }
        $paymentReports = $payment->get();
        $data = [];
        foreach($paymentReports as $paymentR){
            $p = ['plan'=>$paymentR->plan_name,'description'=>$paymentR->description,'display_name'=>$paymentR->display_name,'amount'=>$paymentR->amount,'currency'=>$paymentR->currency,'created_at'=>$paymentR->created_at];
            array_push($data,$p);  
        }

        // echo "<pre>"; print_r($data); die();

        $view = "<html>
        <head>
        <style>
        table, th, td {
          border: 1px solid black;
        }
        </style>
        </head>
        <body>
        <table>
          <tr>
            <th>Pupil Name</th>
            <th>Tier Name</th>
            <th>Price</th> 
            <th>Currency</th>
            <th>Date</th>   
          </tr>
        ";

        if($data != '' && count($data) > 0){
            foreach($data as $d){
                $display_name = $d['display_name'];
                $plan = $d['plan'];
                $amount = $d['amount'];
                $currency = $d['currency'];
                $created_at = $d['created_at'];
                $view .= "<tr>
                           <td>$display_name</td>
                           <td>$plan</td> 
                           <td>$amount</td>
                           <td>$currency</td>
                           <td>$created_at</td>
                           </tr>
                        ";
            }
        }

        $view .= "</table>
        </body>
        </html>";

        $dompdf->loadHtml($view);

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4', 'landscape');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $pdfName = $username.'-'.time();
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/storage/app/public/post-images/'.$pdfName,$dompdf->output());

        return response()->json(['url'=>'https://equiconx-api.com/public/storage/post-images/'.$pdfName,'status'=>'success']);
        // $pdf = App::make('dompdf');

        // $pdf->loadView($template_file);
        // $pdf->render();
        // $pdf->stream();
    }

    public function pollOptionCsvGenerator(Request $request){

        // $user = JWTAuth::parseToken()->authenticate();
        // $userID = $user->id;

        $post_id = $request->post_id;

        $query =   "select ppa.*, ppo.post_options, posts.title as post_title, us.username , us.email 
                    from posts 
                    INNER JOIN post_poll_options ppo ON ppo.post_id = posts.id  
                    INNER JOIN post_poll_user_answers ppa ON  ppa.post_id = posts.id
                    LEFT JOIN users us ON us.id = ppa.user_id
                    WHERE posts.id = $post_id
                  ";

        $result = DB::select(DB::raw($query));

        if(count($result) < 1){
            $fileName = 'result-'.time().'.csv';
            $arr = array('Name', 'Email', 'Responded At', 'Answer');
            $file = fopen($_SERVER['DOCUMENT_ROOT'].'/storage/app/public/post-images/'.$fileName, 'w');
            fputcsv($file, $arr);
            fclose($file);
            return response()->json([
                'data' => 'https://equiconx-api.com/public/storage/post-images/'.$fileName,
                "message" =>  "Url for file ".$fileName,
                'status' => 1
            ], 200);
        }

        $fileName = $result[0]->post_title.'-'.time().'.csv';

        $arr = array('Name', 'Email', 'Responded At', 'Answer');

        $post_options = json_decode($result[0]->post_options);

        // foreach($post_options as $pt){
        //     array_push($arr, $pt->name);
        // }

        $file = fopen($_SERVER['DOCUMENT_ROOT'].'/storage/app/public/post-images/'.$fileName, 'w');

        fputcsv($file, $arr);

        foreach($result as $post){
            $answers = json_decode($post->options);
            $ans = '';
            if(gettype($answers) == 'array'){
                foreach($answers as $a){
                    $ans .= $a->name.', ';
                }
            }else{
                $ans = $answers->name;
            }
            $ans = trim($ans, ', ');

            fputcsv($file, array($post->username, $post->email, $post->created_at, $ans));
        }
       
        fclose($file);
        return response()->json([
                'data' => 'https://equiconx-api.com/public/storage/post-images/'.$fileName,
                "message" => "Url for file ".$fileName,
                'status' => 1
            ], 200);
    }
}
