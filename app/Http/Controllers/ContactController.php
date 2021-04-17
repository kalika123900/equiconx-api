<?php

namespace App\Http\Controllers;

use App\Models\Contact as ContactModel;
use Illuminate\Support\Facades\Validator;
use Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Faqsubject;

class ContactController extends Controller
{
    private $language;
    private $user_meta;
    function __construct(){
        $this->language = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Translations/index.php";
        $this->user_meta = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Meta/countycode.php";
    }

    public function contact(Request $request) {

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required',
            'subject' => 'required',
            'message' => 'required',
        ]);

        $contactSubject = Config::get('app.contactSubject');
        $contactEmail = Config::get('app.contactEmail');

        if ($validator->fails())
        {
            $validation_msgs = $validator->getMessageBag()->all();
            if (isset($validation_msgs[0])) {
                return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);
            }
        }
        send_email($contactEmail, $contactSubject[$request->subject], $request->message, null, 1);

        $saveData = ContactModel::create($request->all());
        $response = ['status' => 'success', 'message' => $this->language[$request->lang]['MSG084']];
        return response()->json($response, 200);
    }

    public function faq(Request $request){
        $lang = $request->lang;
        $query = DB::select("select fs.subject,fm.title,fm.slug from faq_subject fs INNER JOIN faq_master fm ON fm.subject_id = fs.id WHERE fm.language = '$lang'");
        $subject = [];
        foreach($query as $item)
        {
            if(!array_key_exists($item->subject,$subject))
            {
                $subject[$item->subject] = [];
            }
            array_push($subject[$item->subject],$item);
        }
        return response()->json(['status'=>"success", "data"=>$subject], 200);     
    }

    public function getFaq($slug){
        $query = current(DB::select("select * from faq_master where slug = ?", [$slug]));
        if($query){
            return response()->json(['status'=>"success", "data"=>$query], 200);
        }else{
            return response()->json(['status'=>"error", "data"=>"something went wrong"], 400);
        }
    }
}