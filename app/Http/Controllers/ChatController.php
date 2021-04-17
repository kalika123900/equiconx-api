<?php

namespace App\Http\Controllers;

use App\Events\ChatEvent;
use App\Models\Chat;
use App\Models\Subscription;
use App\Models\UserData;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use App\Models\TeamLog;
use stdClass;

class ChatController extends Controller
{

    private $language;
    private $user_meta;
    function __construct(){
        $this->language = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Translations/index.php";
        $this->user_meta = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Meta/countycode.php";
    }

    public function get_subscribed_creators()
    {
        $userId = JWTAuth::parseToken()->authenticate()->id;
        $offset = request()->offset;
        $limit = request()->limit;
        DB::statement("set session sql_mode=''");

        Db::enableQueryLog();
        $chats = Subscription::selectRaw("subscription.id as subscription_id, user_data.user_id as user_id, users.username, c.sender_id, c.rec_id, user_data.display_name, CONCAT('" . url('/') . "', '/public/storage/profile-images/', user_data.profile_image) as profile_image, c.message, c.created_at as last_message_at, c.type as message_type, IF(c.sender_id =" . $userId . ", 1, 2) as is_sender, c.read, user_plans.plan_name")
            ->join("users", function ($query) use($userId) {
                $query->on(DB::raw("IF(subscription.user_id = $userId,subscription.creator_id,subscription.user_id)"), DB::raw('='), DB::raw('users.id'));
            })
            ->join("user_data", function ($query) use($userId) {
                $query->on(DB::raw("IF(subscription.user_id = $userId,subscription.creator_id,subscription.user_id)"), DB::raw('='), DB::raw('user_data.user_id'));
            })
            ->join(DB::raw("(select c1.* from chats c1 LEFT JOIN chats c2 on c2.subscription_id = c1.subscription_id AND c1.id < c2.id WHERE c2.id IS NULL ) as c"), function ($query) use ($userId) {
                $query->on("c.subscription_id", "=", "subscription.id");
            })
            ->leftjoin("user_plans",'user_plans.id','=','subscription.plan_id')
            ->whereRaw("subscription.user_id = $userId OR subscription.creator_id = $userId")
            ->orderBy("c.created_at", "desc")
            ->offset($offset ? $offset : 0)
            ->limit($limit ? $limit : 1000)
            ->get();
      
        $response = ['status' => 'success', 'res' => $chats];

        
        return response()->json($response, 200);
    }
    // replacement for legacy get_chats code
    public function get_user_chats()
    {
      
        $subscriptionId = request()->id;
        $userId = JWTAuth::parseToken()->authenticate()->id;

        $subscription = Subscription::find($subscriptionId);
        

        if (!$subscription) {
            return response()->json(["message" => $this->language[request()->lang]['MSG057']], 503);
        }
        if ($userId != $subscription->user_id && $userId != $subscription->creator_id) {
            return response()->json(["message" => $this->language[request()->lang]['MSG059']], 503);
        }
        $otherUser = $userId == $subscription->user_id ? $subscription->creator_id : $subscription->user_id;
        $offset = request()->offset;
        $limit = request()->limit;
        $chatHistory = Chat::where(["subscription_id" => $subscriptionId])
            ->selectRaw("*, (CASE WHEN type = 0 then message else CONCAT('" . url('/') . "', '/public/storage/post-images/', message) END) as message")
            ->orderBy("created_at", "ASC")
            ->offset($offset ? $offset : 0)
            ->limit($limit ? $limit : 1000)
            ->get();
        $reciever = User::find($otherUser);
        $recData = new stdClass();
        $recData->id = $reciever->id;
        $recData->username = $reciever->username;
        $recData->display_name = $reciever->data->display_name;
        $recData->profile_image =  url('/') . '/public/storage/profile-images/' . $reciever->data->profile_image;
        return response()->json(['status' => 'success',"message" => $this->language[request()->lang]['MSG056'], "data" => $chatHistory, "user" => $recData], 200);
    }
    // @legacy code
    public function get_chats($creator_id, $type = null)
    {
        $user_id = JWTAuth::parseToken()->authenticate()->id;
        $offset = request()->offset;
        $limit = request()->limit;
        $subscriptionId = request()->id;
        $rec_details = UserData::select("user_data.display_name", "user_data.profile_image")
            ->where("user_id", $creator_id)
            ->first();

        $rec_details->profile_image = url('/') . '/public/storage/profile-images/' . $rec_details->profile_image;

        $res = Chat::select("chats.*", "user_data.profile_image")
            ->join("user_data", "user_data.user_id", "chats.rec_id")
            ->where(["sender_id" => $creator_id, 'rec_id' => $user_id])
            ->orwhere(["sender_id" => $user_id, 'rec_id' => $creator_id])
            ->offset($offset || 0)
            ->limit($limit || 1000)
            ->get();

        foreach ($res as $r) {
            $r->profile_image = url('/') . '/public/storage/profile-images/' . $r->profile_image;
        }

        $response = ['status' => 'success', 'res' => $res, 'rec_details' => $rec_details];
        if ($type) {
            return $response;
        } else {
            return response()->json($response, 200);
        }
    }

    public function send_message(Request $request)
    {
        $rec_id = $request->rec_id;
        if ($request->hasfile('attachment')) {
            $validator = Validator::make($request->all(), [
                'attachment' => ['mimes:jpeg,jpg,png,JPG,JPEG,PNG,mp4,mp4v,mpg4,mpeg,mpg,mpe,m1v,m2v,mov,jpm,jpgm,webm|required|max:1'],
            ]);
            if ($validator->fails()) {
                $validation_msgs = $validator->getMessageBag()->all();
                if (isset($validation_msgs[0])) {
                    return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);
                }
            }
        } else {
            $validator = Validator::make($request->all(), [
                'message' => "required|max:3000",
            ]);
            if ($validator->fails()) {
                $validation_msgs = $validator->getMessageBag()->all();
                if (isset($validation_msgs[0])) {
                    return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);
                }
            }
        }
        $subscriptionId = request()->subscriptionId;
        $userId = JWTAuth::parseToken()->authenticate()->id;

        $subscription = Subscription::find($subscriptionId);

        if (!$subscription) {
            return response()->json(["message" => $this->language[request()->lang]['MSG057']], 503);
        }
        if ($userId != $subscription->user_id && $userId != $subscription->creator_id) {
            return response()->json(["message" => $this->language[request()->lang]['MSG059']], 503);
        }


        $file = $request->file("attachment");
        $messageType = 0;
        $message = $request->message;
        if ($file) {
            $front_image = time() . "_" . $file->getClientOriginalName();
            $file->storeAs('public/post-images', $front_image);
            $messageType = 1;
            $message = $front_image;
        }

        $sender_id = JWTAuth::parseToken()->authenticate()->id;
        $c = new Chat();
        $c->sender_id = $sender_id;
        $c->rec_id = $rec_id;
        $c->message = $message;
        $c->type = $messageType;
        $c->read = 0;
        $c->subscription_id = $subscriptionId;
        $c->save();
        if ($messageType === 1) {
            $c->message = url('/') . '/public/storage/post-images/' . $c->message;
        }
        if($request->is_team_account)
        {
            $teamAccount = new TeamLog();
            $teamAccount->company_id    = $userId;
            $teamAccount->user_id       = $request->member_id;
            $teamAccount->action_type   = 'send-message';
            $teamAccount->action_id     = $c->id;
            $teamAccount->save();
        }
        event(new ChatEvent($c));

        return response()->json(['message' => $this->language[request()->lang]['MSG060'], "chat" => $c], 200);
    }
    public function mark_message_read()
    {
       
        $recId = request()->rec_id;
        $subscriptionId = request()->subscriptionId;
        $userId = JWTAuth::parseToken()->authenticate()->id;
        Chat::where([
            "rec_id" => $userId,
        ])->update([
            "seen" => 1
        ]);
        return response()->json(['message' => $this->language[request()->lang]['MSG061']], 200);
    }
    public function get_unread_message_count()
    {
        $userId = JWTAuth::parseToken()->authenticate()->id;
        $count = Chat::where(["rec_id" => $userId, "seen" => 0])->count();
        return response()->json(["count" => $count, 'message' => $this->language[request()->lang]['MSG061']], 200);
    }
    public function message_open(){
        $recId = request()->rec_id;
        $subscriptionId = request()->subscriptionId;
        $userId = JWTAuth::parseToken()->authenticate()->id;
        Chat::where([
            "sender_id" => $recId,
            "rec_id" => $userId,
            "subscription_id" => $subscriptionId
        ])->update([
            "read" => 1
        ]);
        return response()->json(['message' => $this->language[request()->lang]['MSG061']], 200);
    }
}
