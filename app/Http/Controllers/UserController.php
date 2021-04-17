<?php



namespace App\Http\Controllers;



use App\Events\PostLike;

use App\Events\SendNotification;

use App\Models\ForgetPasswordLink;

use App\Models\Notification;

use App\Models\Payment;

use App\Models\Post;

use App\Models\Chat;

use App\Models\PostUpload;

use App\Models\PostPlan;

use App\Models\Subscription;

use App\Models\UserData;

use App\Models\UserMeta;

use App\Models\UserLastLogin;

use App\Models\UserPersonalDetails;

use App\Models\UserVerificationLink;

use App\Models\CompanyUser;

use App\Models\Block;

use App\Models\Report;

use App\Models\userFollower;

use App\Models\TeamLog;

use App\Models\Taxation;

use App\User;

use App\AudioFile;

use App\GoogleAuthenticator;

use App\Models\Comment;

use App\Models\Plan;

use App\Models\Polloptions;

use App\Models\Polluseranswer;

use Carbon\Carbon;

use Exception;

use Illuminate\Http\Request;

use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;

use Stripe\Charge;

use Stripe\Stripe;

use Tymon\JWTAuth\Facades\JWTAuth;

use Illuminate\Support\Facades\Validator;

use Stripe\Account;

use Stripe\CountrySpec;

use Stripe\Customer;

use Stripe\Plan as StripePlan;

use Stripe\Product;

use Stripe\Subscription as StripeSubscription;

use Stripe\Token;

use Image;

use Mail;

use Stripe\TaxRate;



class UserController extends Controller

{

    private $language;

    private $user_meta;

    function __construct(){

        $this->language = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Translations/index.php";

        $this->user_meta = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Meta/countycode.php";

    }



    public function insta_callback(Request $request)

    {

        if($request->error){

            return redirect(env('FRONT_URL').'/user/profile-setting');

        }else{

            echo '<pre>';

            $post = [

            'client_id' => env('INSTA_CLIENT_ID'),

            'client_secret' => env('INSTA_CLIENT_SECRET'),

            'grant_type'   => 'authorization_code',

            'redirect_uri' => env('APP_URL') . '/oauth/insta/callback',

            'code' => $request->code 

            ];

            // print_r($post);

            // echo $request->code; 

            $ch = curl_init();


            curl_setopt($ch, CURLOPT_URL,"https://api.instagram.com/oauth/access_token");

            curl_setopt($ch, CURLOPT_POST, 1);

            curl_setopt($ch, CURLOPT_POSTFIELDS,$post);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $server_output = json_decode(curl_exec($ch));
            
            curl_close($ch);

            

            $ch = curl_init();
         
            curl_setopt($ch, CURLOPT_URL,'https://graph.instagram.com/' . $server_output->user_id . '?fields=id,username&access_token=' . $server_output->access_token);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $user_details = curl_exec($ch);

            curl_close ($ch);
            
            $user_details = json_decode($user_details);
          
            $url = base64_encode($user_details->username); 

            // Further processing..
            return redirect('https://www.equiconx.com/user/profile-settings?i_user='.$url);
        
        }

        

    }

    protected function generateSignature($url, $timestamp, $nonce)

    {

        $base = "POST&" . rawurlencode($url) . "&"

            . rawurlencode("oauth_callback=". rawurlencode(env('APP_URL') . "/oauth/twitter/callback")
            
            . "&oauth_consumer_key=" . rawurlencode(env('TWITTER_CONSUMER_KEY'))

            . "&oauth_nonce=" . rawurlencode($nonce)

            . "&oauth_signature_method=" . rawurlencode('HMAC-SHA1')

            . "&oauth_timestamp=" . rawurlencode($timestamp)

            . "&oauth_version=" . rawurlencode('1.0'));



        $key = rawurlencode(env('TWITTER_CONSUMER_SECRET'))."&";

        $signature = base64_encode(hash_hmac('sha1', $base, $key, true));



        return $signature;

    }

    protected function generateSignatureWithToken($url, $timestamp, $nonce,$token, $verifier)

    {

        $base = "GET&" . rawurlencode($url) . "&"

            . rawurlencode("include_email=".rawurlencode('true')
            
            . "&oauth_consumer_key=" . rawurlencode(env('TWITTER_CONSUMER_KEY'))

            . "&oauth_nonce=" . rawurlencode($nonce)

            . "&oauth_signature_method=" . rawurlencode('HMAC-SHA1')

            . "&oauth_timestamp=" . rawurlencode($timestamp)

            . "&oauth_token=" . rawurlencode($token)

            . "&oauth_version=" . rawurlencode('1.0'));

        

        $key = rawurlencode(env('TWITTER_CONSUMER_SECRET'))."&".rawurlencode($verifier);
        
        $signature = base64_encode(hash_hmac('sha1', $base, $key, true));

        return $signature;

    }

    public function twitter_request_token(Request $request)

    {

        $url = 'https://api.twitter.com/oauth/request_token';



        $timestamp = floor(strtotime("now"));

        $nonce = md5(''.$timestamp.'');



        $params = array(

            'oauth_nonce'=> $nonce,

            'oauth_callback'=> env('APP_URL') . '/oauth/twitter/callback',

            'oauth_signature_method'=> 'HMAC-SHA1',

            'oauth_timestamp'=> $timestamp,

            'oauth_consumer_key'=> env('TWITTER_CONSUMER_KEY'),

            'oauth_version'=> '1.0'

        );  



        $params += ['oauth_signature' => $this->generateSignature($url , $timestamp, $nonce)];



        ksort($params);



        $auth = '';

        foreach($params as $key => $value){

            $auth = $auth . $key . '="' . rawurlencode($value) . '",';

        }

        $ch = curl_init();



        curl_setopt($ch, CURLOPT_URL, $url );

        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_HEADER, 1);

        curl_setopt($ch, CURLOPT_HTTPHEADER , array("Authorization: OAuth " . $auth));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);



        $server_output = curl_exec($ch);

        print_r(curl_error($ch));

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        $header = substr($server_output, 0, $header_size);
        
        $body = substr($server_output, $header_size);

        $responseTokens = explode('&',$body);

        $responseTokenArray = [];
        
        curl_close ($ch);

        foreach($responseTokens as $token):
           
         array_push($responseTokenArray,explode('=',$token));
        
        endforeach;

        $response = ['status' => 'success', 'res' => $responseTokenArray];

        return response()->json($response, 200);

    }

    public function twitter_callback(Request $request)

    {   
        $token = $request->oauth_token;
        
        $verifier = $request->oauth_verifier;

        $url = 'https://api.twitter.com/oauth/access_token?oauth_token='.$token.'&oauth_verifier='.$verifier;
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url );

        curl_setopt($ch, CURLOPT_POST, 1 );

        curl_setopt($ch, CURLOPT_HEADER, 1);

        // curl_setopt($ch, CURLOPT_HTTPHEADER , array("Authorization: OAuth " . $auth));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $server_output = curl_exec($ch);

        print_r(curl_error($ch));

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        $header = substr($server_output, 0, $header_size);
        
        $body = substr($server_output, $header_size);

        curl_close($ch);
        
        $oauthArray = explode('&',$body);


        
        $url = 'https://api.twitter.com/1.1/account/verify_credentials.json';

        $timestamp = floor(strtotime("now"));

        $nonce = md5(''.$timestamp.'');

        $token = explode('=',$oauthArray[0]);

        $secret = explode('=',$oauthArray[1]);
        if(count($token)>0)
        {
            $token = $token[1];
        }
        else
        {
           // redirect
        }
        if(count($secret)>0)
        {
            $secret = $secret[1];
        }
        else
        {
            //redirect;
        }
        
        $url = 'https://api.twitter.com/1.1/account/verify_credentials.json';

        $params = array(
            'include_email'=> true,
            
            'oauth_nonce'=> $nonce,

            'oauth_signature_method'=> 'HMAC-SHA1',

            'oauth_timestamp'=> $timestamp,

            'oauth_token' => $token,

            'oauth_consumer_key'=> env('TWITTER_CONSUMER_KEY'),

            'oauth_version'=> '1.0'

        );  

        $params += ['oauth_signature' => $this->generateSignatureWithToken($url , $timestamp, $nonce, $token , $secret)];



        ksort($params);



        $auth = '';

        foreach($params as $key => $value){

            $auth = $auth . $key . '="' . rawurlencode($value) . '",';

        }

       
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url."?include_email=true" );

        curl_setopt($ch, CURLOPT_HEADER, 1);

        curl_setopt($ch, CURLOPT_HTTPHEADER , array("Authorization: OAuth " . $auth));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $server_output = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        $header = substr($server_output, 0, $header_size);
        
        $body = substr($server_output, $header_size);

        $response = json_decode($body,true);
        
        return redirect('https://www.equiconx.com/user/profile-settings?t_user='.base64_encode($response['screen_name']));

    }

    public function get_profile_analytics(Request $request)

    {

        $user = JWTAuth::parseToken()->authenticate();

      

        $commentsSql = "SELECT count(id) as comment  from comments where  comments.post_id in (select id from posts where user_id =". $user->id .")";

        $likesSql = "SELECT count(id) as 'like'  from likes where likes.post_id in (select id from posts where user_id =". $user->id .")";

        $interactionsSql = "SELECT count(DISTINCT sender_id) as 'interaction' from chats where chats.rec_id =" . $user->id;

        $totalFansSql = "SELECT count(id) as fans  from user_followers where  following =". $user->id;

        $newFansSql = "select count(id) as new_fans from user_followers WHERE MONTH(created_at) = MONTH(CURRENT_DATE())

        AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND following =". $user->id;

       

        $comments = DB::select($commentsSql);

        $likes = DB::select($likesSql);

        $interactions = DB::select($interactionsSql);

        $totalFansSql = DB::select($totalFansSql);

        $newFansSql = DB::select($newFansSql);

        

        $result = ['comments' => $comments, 'likes' => $likes, 'interactions'=> $interactions , 'fans'=> $totalFansSql, 'new_fans'=>$newFansSql];

        $response = ['status' => 'success', 'res' => $result];

        return response()->json($response, 200);

    }

    public function get_follow_details(Request $request)

    {

        $user           = JWTAuth::parseToken()->authenticate();

        $urlProfile     = url('/') . '/public/storage/profile-images/';

        $urlCover       = url('/') . '/public/storage/cover-images/';

        

        $followersSql = "SELECT u.id, u.username, ud.display_name, CONCAT('$urlProfile',ud.profile_image) as profile_image, CONCAT('$urlCover', ud.cover_image) as cover_image,py.currency, py.created_at, uf.created_at as follow_date, ud.about, if(up.plan_name=null,0,1) as isSubscriber, up.plan_name, sum(py.amount) as liftime from user_followers uf INNER JOIN users u on u.id = uf.user_id INNER JOIN user_data ud on ud.user_id = uf.user_id  LEFT JOIN subscription sb on sb.user_id = u.id AND sb.creator_id = $user->id LEFT JOIN user_plans up ON up.id = sb.plan_id LEFT JOIN payments py on py.user_id = u.id AND py.charge_id IN (select charge_id from payments p WHERE p.user_id = $user->id AND p.type = 'income')";

        



        $followersSql .= " where uf.following = $user->id ";

        

        if($request->keyword)

        {

           $followersSql .= " AND (u.username LIKE '%".$request->keyword."%' OR  ud.display_name LIKE '%".$request->keyword."%')";

        }

        if($request->type)

        {   $type = explode(',',$request->type);

            if(in_array('1',$type) && !in_array('0',$type))

            {
                $followersSql .= " AND sb.id > 0 ";     
            }

        }

        if($request->plan && $request->plan!='' && strpos($request->plan,"-1")!=false){

            

            $followersSql .= " AND sb.plan_id IN ($request->plan)"; 

        }

        $followersSql .= " GROUP by u.id ORDER BY py.created_at DESC";

       

        $followingSql = "SELECT u.id, u.username, ud.display_name, CONCAT('$urlProfile',ud.profile_image) as profile_image, CONCAT('$urlCover', ud.cover_image) as cover_image, py.currency, py.created_at, uf.created_at as follow_date, ud.about, if(up.plan_name=null,0,1) as isSubscriber, up.plan_name, sum(py.amount) as liftime from user_followers uf INNER JOIN users u on u.id = uf.following INNER JOIN user_data ud on ud.user_id = uf.following  LEFT JOIN subscription sb on sb.user_id = u.id AND sb.creator_id = $user->id LEFT JOIN user_plans up ON up.id = sb.plan_id LEFT JOIN payments py on py.user_id = u.id AND py.charge_id IN (select charge_id from payments p WHERE p.user_id = $user->id AND p.type = 'income') ";



        $followingSql .= " where uf.user_id = $user->id ";

        

        if($request->keyword)

        {

            $followingSql .= " AND (u.username LIKE '%".$request->keyword."%' OR ud.display_name LIKE '%".$request->keyword."%')";

        }

        

        if($request->type)

        {   $type = explode(',',$request->type);

            if(in_array('1',$type) && !in_array('0',$type))

            {

                $followingSql .= " AND sb.id > 0 ";     

            }

        }

        if($request->plan && $request->plan!=''){

            

            $followingSql .= " AND sb.plan_id IN ($request->plan)"; 

        }

        $followingSql .= " GROUP BY u.id   ORDER BY py.created_at DESC";


     
        $followers    =  DB::select($followersSql);

       
        $following    =  DB::select($followingSql);



        $result       =  ['followers'=>$followers, 'following'=> $following];

        

        $response     =  ['status' => 'success', 'res' => $result];



        return response()->json($response, 200);

    }

    public function verify_email($link)

    {

        
        $res = UserVerificationLink::where(["link" => $link])->first();


        if ($res) {

            $u = User::find($res->user_id);

            $u->email_verified_at = date("Y-m-d h:i:s");

            $u->save();

            UserVerificationLink::find($res->id)->delete();

            $email = $u->email;
            header("X-Node: localhost");
            $headers = "MIME-Version: 1.0" . "\r\n";

            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;

            $sub = " Welcome to the Equiconx herd! ";

            $contactLink = env("FRONT_URL")."/contact";
            $loginLink =  env('FRONT_URL');
            $template_file = "resources/views/welcome.blade.php";
            if(isset(request()->lang) && request()->lang == "de" ){

                $sub = "Willkommen bei der Equiconx Herde!";

                $template_file = "resources/views/german/welcome.blade.php";

                $contactLink = env("FRONT_URL")."/".request()->lang."/contact";
                $loginLink =  env('FRONT_URL').request()->lang."/";

            }
            $msg = file_get_contents($template_file);

            $msg = str_replace("__changeWithlink", $loginLink, $msg);

            $msg = str_replace("__contactpage", $contactLink, $msg);

            mail($email, $sub, $msg, $headers);

            return redirect(env('FRONT_URL').'?status=emailverified');

        } else {

            $message = "Verification Link is Invalid";

            return redirect('/')->with('error', $message);

        }

    }

    public function generateUserName($username)

    {

        $username = str_replace(" ", "", strtolower($username));



        $u = DB::select("select username from users where username LIKE '{$username}%'");



        if (count($u) == 0) {

            return $username;

        } else {

            $done = false;

            $count = count($u) + 1;

            while ($done != true) {

                $newusername = $username . $count;

                $reset = false;

                foreach ($u as $user) {

                    if ($user->username == $newusername) {

                        $count++;

                        $reset = true;

                    }

                }

                if ($reset == false) {

                    $done = true;

                }

            }

            return $newusername;

        }

    }



    private function getToken($email, $password, $optionalAttempt = false)

    {

        $token = null;

        //$credentials = $request->only('email', 'password');

        try {



            if (!$token = JWTAuth::attempt(['email' => $email, 'password' => $password])) {

                return response()->json([

                    'response' => 'error',

                    'message' => 'Password or email is invalid',

                    'token' => $token

                ]);

            }

        } catch (JWTAuthException $e) {

            return response()->json([

                'response' => 'error',

                'message' => 'Token creation failed',

            ]);

        }

        

        return $token;

    }



    public function login(Request $request)

    {

        
     

        $user = User::where('email', $request->email)->get()->first();

        

        if ($user) {

            if ($user->email_verified_at) {

                if ($user && \Hash::check($request->password, $user->password)) // The passwords match...

                {

                    $token = $this->getToken($request->email, $request->password);

                    $user->auth_token = $token;

                    $user->save();



                    

                    $user_data = $this->get_updated_user($user->id);

                    $user->country = $user_data->country;



                    $ip = $_SERVER['REMOTE_ADDR'];



                    $ua = getBrowser();



                    $data = \Location::get($ip);

                    

                    if ($data) {

                        $country = $data->countryName;

                        $city = $data->cityName;

                    } else {

                        $country = $user->country; // ""

                        $city = "";

                    }



                    $ul = new UserLastLogin();

                    $ul->user_id = $user->id;

                    $ul->login_date = time();

                    $ul->ip = $ip;

                    $ul->browser = $ua['name'] . " " . $ua['version'] . "," . $ua['platform'];

                    $ul->location = $city . "," . $country;

                    $ul->save();





                    $response = ['status' => 'success', 'res' => $user];

                    return response()->json($response, 200);

                } else

                    $response = ['status' => 'error', 'message' => $this->language[$request->lang]['MSG004']];

                return response()->json($response, 400);

            } else {

                $response = ['status' => 'error', 'message' => $this->language[$request->lang]['MSG005']];

                return response()->json($response, 400);

            }

        } else {

            $response = ['status' => 'error', 'message' => $this->language[$request->lang]['MSG004']];

            return response()->json($response, 400);

        }

    }

    /**

     * Create customer and connected account on stripe for registered user

     * @author Sonu Bamniya

     */

    private function createAccount(User $user, $country)

    {

        $account = Account::create(array(

            "type" => "custom",

            "country" => $country,

            "email" => $user->email,

            "business_type" => "individual",

            "individual" => [

                "email" => $user->email

            ],

            'requested_capabilities' => ['transfers', 'card_payments'],

            "tos_acceptance" => [

                'date' => time(),

                'ip' => request()->ip(),

            ]

        ));

        return $account;

    }

    private function handleStripeAccountCreation(User $user, $country)

    {
      
          
        $user->createAsStripeCustomer();
        
        $account = $this->createAccount($user, $country);

        $user->stripe_connected_account_id = $account->id;

        $user->save();

    }

    public function register(Request $request)

    {

        $data = $request->all();

        $validator = Validator::make(

            $data,

            [

                'email' => ['required', 'string', 'max:255', 'unique:users'],

                'password' => ['required', 'string', 'max:255'],

                'username' => ['required', 'string', 'max:255', 'unique:users'],

                'usertype' => ['required', 'integer', 'max:3']

            ]

        );



        $payload = [

            'password' => \Hash::make($request->password),

            'email' => $request->email,

            'username' => $request->username,

            'user_type' => $request->usertype,

            'auth_token' => '',

            // to skip the email for now. @TODO: Fix the issues with email sending

            // 'email_verified_at' => date("Y-m-d h:i:s")

        ];

        if ($validator->fails()) {

            $validation_msgs = $validator->getMessageBag()->all();

            if (isset($validation_msgs[0])) {

                return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);

            }

        } else {

            $user = new User($payload);

            // $user->createAsStripeCustomer();

            

            if ($user->save()) {



                $token = $this->getToken($request->email, $request->password); // generate user token



                if (!is_string($token)) {

                    return response()->json(['status' => 'success', 'data' => 'Token generation failed'], 201);

                }



                $link = md5(uniqid());

                $uvl = new UserVerificationLink();

                $uvl->user_id = $user->id;

                $uvl->link = $link;

                $uvl->expiry = strtotime("+30 minutes");

                $uvl->save();

                header("X-Node: localhost");

                $email = $user->email;

                $headers = "MIME-Version: 1.0" . "\r\n";

                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;                
                $sub = "Activate your Equiconx Account ";

                $verifyLink = env("WEB_URL") . '/verify-email/' . $link."?lang=".$request->lang;
                $becomeMaster = env("FRONT_URL").'/master';
                $findMaster = env('FRONT_URL').'/user/find-master';
                $login = env('FRONT_URL');
                $faq = env('FRONT_URL').'/faq';
                $contactUs = env("FRONT_URL").'/contact';



                $template_file = "resources/views/htmluitemplate.blade.php";



                if(isset($request->lang) && $request->lang == "de" ){

                    $sub = "Aktiviere dein Equiconx Profil";

                    $template_file = "resources/views/german/htmluitemplate.blade.php";
                    $becomeMaster = env("FRONT_URL").'/'.$request->lang.'/master';
                    $findMaster = env('FRONT_URL').'/'.$request->lang.'/user/find-master';
                    $login = env('FRONT_URL').'/'.$request->lang.'/';
                    $faq = env('FRONT_URL').'/'.$request->lang.'/faq';
                    $contactUs = env("FRONT_URL").'/'.$request->lang.'/contact';

                }



                $msg = file_get_contents($template_file);

                $msg = str_replace("__changeWithlink", $verifyLink, $msg);
                $msg = str_replace("__contactpage", $contactUs, $msg);
                $msg = str_replace("__genericmaster", $becomeMaster, $msg);
                $msg = str_replace("__findmaster", $findMaster, $msg);
                $msg = str_replace("__login", $login, $msg);
                $msg = str_replace("__faqpage", $faq, $msg);

                mail($email, $sub, $msg, $headers);



                $response = ['status' => 'success', 'message' => $this->language[$request->lang]['MSG107']];

                return response()->json($response, 200);

            } else {

                $response = ['status' => 'error', 'message' => 'Error'];

                return response()->json($response, 400);

            }

        }

    }



    public function set_country(Request $request){

        $user  = JWTAuth::parseToken()->authenticate();

       
        $this->handleStripeAccountCreation($user, $request->country);

        $userdata = new UserData();

        $userdata->user_id = $user->id;

        $userdata->country = $request->country;

        $userdata->save();

        return response()->json(["status" => "success", "message" => $this->language[$request->lang]['MSG108']]);

    }



    public function get_updated_user($id){

        $user = User::where('id', $id)->get()->first();

        $userData = UserData::where('user_id', $user->id)->get()->first();

        if(isset($userData) && isset($userData) != ''){

            $user->country = $userData->country;

        }else{

            $user->country = "";

        }

        return $user;

    }



    public function get_updated_data(Request $request){

        $user  = JWTAuth::parseToken()->authenticate();

        $userData = UserData::where('user_id', $user->id)->get()->first();

        if(isset($userData) && isset($userData) != ''){

            $user->country = $userData->country;

        }else{

            $user->country = "";

        }

        return $response = ['status' => 'success', 'res' => $user];

        

    }



    public function send_email(Request $request)

    {

        $link = md5(uniqid());

        $email = $request->email;

        $u = User::where("email", $email)->first();

        if ($u) {

            $p = new ForgetPasswordLink();

            $p->email = $request->email;

            $p->link = $link;

            $p->expiry = strtotime("+10 minutes");
            $expiry = $p->expiry;

            $p->save();
            $vl = env("FRONT_URL").'/reset-password/' . $link;
            $contactUs = env("FRONT_URL").'/contact';
            if($request->lang == 'de'){
                $vl = env("FRONT_URL") .'/'.$request->lang.'/reset-password/' . $link;
                $contactUs = env("FRONT_URL").'/'.$request->lang.'/contact';
            }
            $username = $u->first_name.' '.$u->last_name;
            $verifyLink = $vl;
          
            $sub = "Out with the old (Email password), in with the new ";
            $template_file = "resources/views/reset_password.blade.php";
            $expiry_time =  date('H:i:s', $expiry);

            if(isset($request->lang) && $request->lang == "de" ){
                $sub = "Raus mit dem Alten (E-Mail-Passwort), rein mit dem Neuen";
                $template_file = "resources/views/german/reset_password.blade.php";
            }


            $msg = file_get_contents($template_file);

            $msg = str_replace("__username", $username, $msg);
            $msg = str_replace("__resetpasspage", $verifyLink, $msg);
            $msg = str_replace("__contactpage", $contactUs, $msg);
            // $msg = str_replace("__expiretime", $expiry_time, $msg);
            
            header("X-Node: localhost");
            
            $headers  = 'MIME-Version: 1.0' . "\r\n";

            $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
            $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;
            mail($email, $sub, $msg, $headers);
            
            $response = ['status' => 'success', 'message' => $this->language[$request->lang]['MSG007']];
            return response()->json($response, 200,);

        } else {

            $response = ['status' => 'error', 'message' => $this->language[$request->lang]['MSG008']];

            return response()->json($response, 400);

        }

    }







    public function get_public_profile($uname)

    {

        $isLogin = false;

        $is_subscribed = false;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {

            $auth_user = JWTAuth::parseToken()->authenticate();

            if ($auth_user) {

                $auth_user_id = $auth_user->id;

            }

            $isLogin = true;

        }



        $user = User::where("username", $uname)->first();



        $ip = $_SERVER['REMOTE_ADDR'];

        $ua = $_SERVER['HTTP_USER_AGENT'];





        $user_id = $user->id;

        if($isLogin==true):

            $res = User::select(DB::Raw("users.email, users.username,IF(IFNULL(user_followers.id,0)=0,0,1) as followstatus,  GROUP_CONCAT(subscription.plan_id) as plans, users.user_type, user_data.*"));

            $res = $res->leftJoin("user_followers",function($query) use ($auth_user_id) {

                $query->on("user_followers.following", '=', "users.id")

                    ->where("user_followers.user_id", '=', $auth_user_id);

            });

            $res = $res->where(function ($query) use ($auth_user) {
                    $query->where("users.id", "!=", $auth_user->id)
                        ->whereRaw("blocks.block_id  IS NULL")
                        ->whereNotNull("email_verified_at");
                });
            $res = $res->leftjoin("blocks", function ($query) use ($auth_user) {
                    $query->on("users.id", '=', "blocks.user_id")
                        ->where("blocks.block_id", '!=', $auth_user->id);
                });    
         

            $res = $res->leftJoin("subscription",function($query) use ($auth_user_id) {

                $query->on("subscription.creator_id", '=', "users.id")

                    ->where("subscription.user_id", '=', $auth_user_id)
                    
                    ->where("subscription.status", '=', 'active');

            });


        else:

            $res = User::select(DB::Raw("users.email, users.user_type, user_data.*"));   

        endif;    

        $res = $res->join("user_data", "users.id", "user_data.user_id")

            ->where("users.id", $user_id)

            ->first();
       

        $res->profile_image = url('/') . '/public/storage/profile-images/' . $res->profile_image;

        $res->cover_image = url('/') . '/public/storage/cover-images/' . $res->cover_image;

        $res->total_post = Post::where("user_id", $user_id)->count();

        $post_id = Post::where("user_id", $user_id)->pluck("id");

        $img_ext = ['jpeg', 'jpg', 'png', 'gif', 'JPG', 'JPEG', 'PNG', 'GIF', 'img'];

        $video_ext = ['mp4', 'ogx', 'oga', 'ogv', 'ogg', 'webm'];

        $res->image_count = PostUpload::whereIn("post_id", $post_id)

            ->whereIn("ext", $img_ext)

            ->count();

        $res->video_count = PostUpload::whereIn("post_id", $post_id)

            ->whereIn("ext", $video_ext)

            ->count();

        $res->ip = $ip;

        $res->ua = $ua;

        $res->totalFollower     = Subscription::where(["creator_id" => $user_id, "status" => "active"])->count();

        $res->totalSubscription = Subscription::where(["user_id" => $user_id, "status" => "active"])->count();

        $res->totalAmount       = Payment::where(["user_id" => $user_id, 'type' => 'income'])->sum('amount');

        if ($isLogin) {

            $is_subscribed = Subscription::where(["user_id" => $auth_user_id, "creator_id" => $user->id, "status" => "active"])->first();

            $is_block = Block::where(["user_id"=>$auth_user_id,"block_id"=>$user->id])->first();
            
          
            if($is_block)
            {
                $res->is_block = 1;
            }
            else
            {
                $res->is_block = 0;
            }

        }

        if ($is_subscribed) {

            $res->is_subscribed = 1;

            $res->subscription_id = $is_subscribed->id;

        } else {

            $res->is_subscribed = 0;

        }

        $res->has_plans = $user->isVerified();

        $response = ['status' => 'success', 'res' => $res];

        return response()->json($response, 200);

    }



    public function get_personal_details()

    {

        $user = JWTAuth::parseToken()->authenticate();



        $res = UserPersonalDetails::where("user_id", $user->id)->first();



        $response = ['status' => 'success', 'res' => $res];

        return response()->json($response, 200);

    }

    public function update_social_profile(Request $request)

    {

        $user = JWTAuth::parseToken()->authenticate();

        $sql = '';

        $userID = $user->id;



        $data = JSON_decode($request->data, true);



       

        if(isset($data['facebook'])){

            if($data['facebook'] == -1){

                $sql = "update users set facebook = '". null . "' where id = ". $userID;

            }

            else{

                $facebook = $data['facebook'];

                $sql = "update users set facebook = '". $facebook . "' where id = ". $userID;

            }

        }



          if(isset($data['twitter'])){

            if($data['twitter'] == -1){

                $sql = "update users set twitter = '". null . "' where id = ". $userID;

            }

            else{

                $twitter = $data['twitter'];

                $sql = "update users set twitter = '". $twitter . "' where id = ". $userID;

            }

        }



          if(isset($data['instagram'])){

            if($data['instagram'] == -1){

                $sql = "update users set instagram = '". null . "' where id = ". $userID;

            }

            else{

                $instagram = $data['instagram'];

                $sql = "update users set instagram = '". $instagram . "' where id = ". $userID;

            }

        }



          if(isset($data['youtube'])){

            if($data['youtube'] == -1){

                $sql = "update users set youtube = '". null . "' where id = ". $userID;

            }

            else{

                $youtube = $data['youtube'];

                $sql = "update users set youtube = '". $youtube . "' where id = ". $userID;

            }

        }



        $res = DB::update(DB::RAW($sql));

        

        $response = ['status' => 'success', 'res' => $res];

        return response()->json($response, 200);

    }

    public function follow_profile(Request $request)

    {

        $user = JWTAuth::parseToken()->authenticate();

        $userID = $user->id;



        $followerID = $request->follower_id;

        $isFollowing = $request->isfollowing;



        $sql = '';



        if($isFollowing==1)

        {

           $sql = "Insert into user_followers (user_id , following) values ($userID, $followerID)";

        }   else {

           $sql = "delete from user_followers where user_id = " . $userID . " and following = ". $followerID ;

        }    

        $res = DB::insert($sql);



        $response = ['status' => 'success', 'res' => $res];

        return response()->json($response, 200);

    }

    public function get_profile($uname)

    {

        try {



            if (!$user = JWTAuth::parseToken()->authenticate()) {

                return response()->json(['user_not_found'], 404);

            }

        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {



            return response()->json(['token_expired'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {



            return response()->json(['token_invalid'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {



            return response()->json(['token_absent'], $e->getStatusCode());

        }



      

        $ip = $_SERVER['REMOTE_ADDR'];

        $ua = $_SERVER['HTTP_USER_AGENT'];



        $user_id = $user->id;

        

        //$user = $user::find($user_id);

        //$teams = $user->teams()->get();

         DB::enableQueryLog();

        $res = User::select("users.email", "users.username", "users.user_type", "users.two_way_auth", "users.facebook" , "users.instagram" ,"users.twitter" ,"users.youtube" ,  "user_data.*")

            ->join("user_data", "users.id", "user_data.user_id")

            ->where("users.id", $user_id)

            ->first();

           
            // $query = DB::getQueryLog();
            // echo "<pre>";
            // print_r($query); die();
        

        $extra = UserMeta::select("user_meta.key_type", "user_meta.value")

                ->where("user_id", $user_id)

                ->get()

                ->toArray();

        



        if (!$res) {

            $res = User::find($user_id);

        }



        // echo "<pre>";

        // print_r($extra[0]['key_type']);

        // die();



        $newData = [];



        for($i = 0; $i < count($extra); $i++){

            for($j=0; $j < count($extra[$i]); $j++){

                // $newData['key_type'.$i] = $extra[$i]['key_type'];

                // $newData['value'.$i]    = $extra[$i]['value'];

                $res[$extra[$i]['key_type']] = $extra[$i]['value'];

            }

        }



        $user_country = $this->get_updated_user($user_id);



        $res->profile_image = url('/') . '/public/storage/profile-images/' . $res->profile_image;

        $res->cover_image = url('/') . '/public/storage/cover-images/' . $res->cover_image;

        $res->total_post = Post::where("user_id", $user_id)->count();

        $post_id = Post::where("user_id", $user_id)->pluck("id");

        $img_ext = ['jpeg', 'jpg', 'png', 'gif', 'JPG', 'JPEG', 'PNG', 'GIF', 'img'];

        $video_ext = ['mp4', 'ogx', 'oga', 'ogv', 'ogg', 'webm'];

        $res->image_count = PostUpload::whereIn("post_id", $post_id)->whereIn("ext", $img_ext)->count();

        $res->video_count = PostUpload::whereIn("post_id", $post_id)->whereIn("ext", $video_ext)->count();

        $res->totalFollower = Subscription::where(["creator_id" => $user_id, "status" => "active"])->count();

        $res->totalSubscription = Subscription::where(["user_id" => $user_id, "status" => "active"])->count();

        $res->totalAmount       = Payment::where(["user_id" => $user_id, 'type' => 'income'])->sum('amount');

        $res->ip = $ip;

        $res->ua = $ua;

        $res->country  = $user_country->country;

        $res->isVerified = $user->isVerified();

        $res->settings = $user->settings();

        $response = ['status' => 'success', 'res' => $res];

        return response()->json($response, 200);

    }







    public function get_total_photos($id)

    {

        $post_id = Post::where("user_id", $id)->pluck("id");

        $img_ext = ['jpeg', 'jpg', 'png', 'gif', 'JPG', 'JPEG', 'PNG', 'GIF'];

        $total_photos = PostUpload::whereIn("post_id", $post_id)->whereIn("ext", $img_ext)->get();

        foreach ($total_photos as $up) {

            $up->file = url('/') . '/public/storage/post-images/' . $up->file;

            //$up->created = $up->created_at->diffForHumans();

            $up->created = date("d-M-Y", strtotime($up->created_at));

        }



        $total_img = PostUpload::whereIn("post_id", $post_id)->whereIn("ext", $img_ext)->pluck('file');

        foreach ($total_img as $key => $value) {

            $total_img[$key] = url('/') . '/public/storage/post-images/' . $value;

        }

        $ext = PostUpload::whereIn("post_id", $post_id)->whereIn("ext", $img_ext)->pluck('ext');

        foreach ($ext as $key => $value) {

            if ($ext[$key] == "mp4" || $ext[$key] == "ogv" || $ext[$key] == "webm") {

                $ext[$key] = "video";

            } else {

                $ext[$key] = "image";

            }

        }



        $response = ['status' => 'success', 'res' => $total_photos, 'total_img' => $total_img, 'ext' => $ext];

        return response()->json($response, 200);

    }

    public function get_total_videos($id)

    {

        $post_id = Post::where("user_id", $id)->pluck("id");

        $video_ext = ['mp4', 'ogx', 'oga', 'ogv', 'ogg', 'webm'];

        $total_videos = PostUpload::whereIn("post_id", $post_id)->whereIn("ext", $video_ext)->get();

        foreach ($total_videos as $up) {

            $up->file = url('/') . '/public/storage/post-images/' . $up->file;

            //$up->created = $up->created_at->diffForHumans();

            $up->created = date("d-M-Y", strtotime($up->created_at));

        }

        $total_vid = PostUpload::whereIn("post_id", $post_id)->whereIn("ext", $video_ext)->pluck('file');

        foreach ($total_vid as $key => $value) {

            $total_vid[$key] = url('/') . '/public/storage/post-images/' . $value;

        }



        $ext = PostUpload::whereIn("post_id", $post_id)->whereIn("ext", $video_ext)->pluck('ext');

        foreach ($ext as $key => $value) {

            if ($ext[$key] == "mp4" || $ext[$key] == "ogv" || $ext[$key] == "webm") {

                $ext[$key] = "video";

            } else {

                $ext[$key] = "image";

            }

        }



        $response = ['status' => 'success', 'res' => $total_videos, 'total_vid' => $total_vid, 'ext' => $ext];

        return response()->json($response, 200);

    }

    public function subscribe_creator($creator_id)

    {

        $user_id = JWTAuth::parseToken()->authenticate()->id;

        $user = User::find($user_id);

        $s = new Subscription();

        $s->user_id = $user_id;

        $s->creator_id = $creator_id;

        $s->save();



        //        if ($request->userId != $post->user_id) {

        $n = new Notification();

        $n->sender_id = $user_id;

        $n->rec_id = $creator_id;

        $n->notification = "Your profile has been subscribed by " . $user->username;

        $n->date = time();

        $n->url = "#";

        $n->type = 3;

        $n->save();

        event(new SendNotification($n));

        //}



        $response = ['status' => 'success', 'message' => $this->language[request()->lang]['MSG082']];

        return response()->json($response, 200);

    }



    public function get_last_login($user_id)

    {

        $res = UserLastLogin::select("*", DB::raw("from_unixtime(login_date,'%d/%m/%Y %h:%i %p') as login_date"))->where("user_id", $user_id)->orderby("id", "desc")->limit(5)->get();

        $response = ['status' => 'success', 'res' => $res];

        return response()->json($response, 200);

    }

    private function updateAccountOnStripe(User $user)

    {

        if (!$user->stripe_id) {

            $user->createAsStripeCustomer();

        }

        if (!$user->stripe_connected_account_id) {

            $accountId = $this->createAccount($user)->id;

            $user->stripe_connected_account_id = $accountId;

            $user->save();

        } else {

            $accountId = $user->stripe_connected_account_id;

        }

        $userData = $user->data;

        $userMeta = $user->meta;

      

        $dob = Carbon::parse($userData->date_of_birth);

        $name = explode(" ", $userData->display_name);



        $userDetails = [

            "individual" => [

                "first_name" => $name[0],

                "last_name" => isset($name[1]) ? $name[1] : "",

                "dob" => [

                    "day" => $dob->day,

                    "month" => $dob->month,

                    "year" => $dob->year

                ],

                "address" => [

                    "city" => $userData->location,

                    "line1" => $userData->address_line1,

                    "line2" => $userData->address_line2,

                    "postal_code" => $userData->postal_code,

                    "country" => $userData->country

                ],

                "phone" => $userData->phone_number,

            ],

            "business_profile" => [

                "url" => $userData->url,

                "mcc" => "7311",

                "support_address" => [

                    "city" => $userData->location,

                    "line1" => $userData->address_line1,

                    "line2" => $userData->address_line2,

                    "postal_code" => $userData->postal_code,

                    "country" => $userData->country

                ]

            ]

                ];

        //Check if it belongs to US/Australia/Austria for Province

        if($user->country=='US'){

            foreach($userMeta as $meta)

            {

                if($meta->key_type=='state')

                {

                    $userDetails['individual']['address']['state'] = $meta->value;

                }

                if($meta->key_type=='ssn_last_4')

                {

                    $userDetails['individual']['ssn_last_4'] = $meta->value;     

                }

            }

        }  

        if($user->country=='AU'){

            foreach($userMeta as $meta)

            {

                if($meta->key_type=='state')

                {

                    $userDetails['individual']['address']['state'] = $meta->state;

                }

            }

        }  

   

        Account::update($accountId, $userDetails);

    }

    public function subscribedFeed(Request $request)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        $userId = $auth_user->id;

    }

    public function update_profile(Request $request)

    {

        $data = $request->all();

        if ($request->url && $request->username && $request->email) {

            $validator = Validator::make(

                $data,

                [

                    'username' => ['required', 'unique:users,username,' . $request->user_id],

                    'email' => ['required', 'unique:users,email,' . $request->user_id],

                    'url' => ['required', 'url'],

                ]

            );

        } else if ($request->username && $request->email) {

            $validator = Validator::make(

                $data,

                [

                    'username' => ['required', 'unique:users,username,' . $request->user_id],

                    'email' => ['required', 'unique:users,email,' . $request->user_id],

                ]

            );

        } else if ($request->username) {

            $validator = Validator::make(

                $data,

                [

                    'username' => ['required', 'unique:users,username,' . $request->user_id],

                ]

            );

        } else if ($request->email) {

            $validator = Validator::make(

                $data,

                [

                    'email' => ['required', 'unique:users,email,' . $request->user_id],

                ]

            );

        } else {

            $validator = "";

        }

        if ($validator && $validator->fails()) {

            $validation_msgs = $validator->getMessageBag()->all();

            if (isset($validation_msgs[0])) {

                return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);

            }

        } else {



            $user = User::find($request->user_id);

            $user->username = $request->username ? $request->username : $user->username;

            $user->email = $request->email ? $request->email : $user->email;

            if ($request->settings) {

                $user->user_preferences = json_encode($request->settings);

            }

            $user->save();

          
            $u = UserData::where("user_id", $request->user_id)->first();

            $u->display_name = $request->display_name ? $request->display_name : $u->display_name;

            $u->about = $request->about ? $request->about : $u->about;

            $u->location = $request->location ? $request->location : $u->location;

            $u->url = 'https://equiconx.com/?uid='.$user->id; 

            $u->phone_number = $request->phone_number ? $request->phone_number : $u->phone_number;

            $u->date_of_birth = $request->date_of_birth ? $request->date_of_birth : $u->date_of_birth;

            $u->address_line1 = $request->address_line1 ? $request->address_line1 : $u->address_line1;

            $u->address_line2 = $request->address_line2 ? $request->address_line2 : $u->address_line2;

            $u->city = $request->city ? $request->city : $u->city;

            $u->postal_code = $request->postal_code ? $request->postal_code : $u->postal_code;

            $u->analytics_code = $request->analytics_code ? $request->analytics_code : $u->analytics_code;

            $u->country = $u->country;

            $checkMeta = $this->extra_meta($request);

            if($checkMeta != 1){

                return response()->json(['status' => 'error', 'message' => "$checkMeta". " is required"], 200);

            }

            DB::update('UPDATE user_data SET country = ? WHERE id = ? ;', [$u->country, $request->id]);



            $u->save();

            try {

                $this->updateAccountOnStripe($user);

            } catch (Exception $e) {

                return response()->json(['status' => 'error', 'message' => $e->getMessage() . ','.$this->language[$request->lang]['MSG011']], 200);

            }

            if (!$user->stripe_product_id) {

                try {

                    $product = Product::create([

                        "name" => "Product Plan for $u->dispplay_name($user->username)."

                    ], ["stripe_account" => $user->stripe_connected_account_id]);

                } catch (Exception $e) {

                    return response()->json(['status' => 'error', 'message' => $e->getMessage() . $this->language[$request->lang]['MSG011']], 200);

                }

                $user->stripe_product_id = $product->id;

                $user->save();

            }



            return response()->json(['status' => 'success', 'message' => $this->language[$request->lang]['MSG013'], "settings" => $request->settings], 200);

        }

    }



    /* to check */

    function extra_meta($request){

        $d = $request->all();

        $u1 = User::where("user_id", $d['user_id']);

    

        if(!isset($u1->id)){

            $userdata =  $this->get_updated_user($d['user_id']);

            if(isset($this->user_meta[$userdata->country])){

            for($i = 0; $i < count($this->user_meta[$userdata->country]); $i++){

                if(isset($d[$this->user_meta[$userdata->country][$i]]) && $d[$this->user_meta[$userdata->country][$i]] != ''){

                    $details = new UserMeta();

                    $details->key_type = $this->user_meta[$userdata->country][$i];

                    $details->value = $d[$this->user_meta[$userdata->country][$i]];

                    $date = date('Y-m-d h:i:s');

                    $details->created_at = $date;

                    // $details->save();

                    DB::insert("

                        INSERT INTO user_meta (user_id, key_type, value ) 

                        values ($userdata->id, '$details->key_type', '$details->value') 

                        ON DUPLICATE KEY update value = '$details->value';

                    ");

                    // echo "<pre>";

                    // print_r($this->user_meta[$userdata->country][$i]);

                    // print_r($d[$this->user_meta[$userdata->country][$i]]);

                    // die();

                    // $newData =  new \stdClass();

                    // $newData->key = $this->user_meta[$userdata->country][$i];

                    // $newData->value = $d[$this->user_meta[$userdata->country][$i]];

                    // $s = $this->UpdateOrInsert($newData);

                }

                else{

                    return $d[$this->user_meta[$userdata->country][$i]];

                }

                }

                }

        }

        return 1;

    }



    function UpdateOrInsert($data){

        $updatedata = [];

        foreach($data as $key=>$value)

        {

            array_push($updatedata,' '.$key.' = "'.$value.'"');

        }

    

        $sql = $this->db->insert_string('user_meta', $data) . ' ON DUPLICATE KEY UPDATE '.implode(", ", $updatedata);

        $this->db->query($sql);

        $id = $this->db->insert_id();

        return $id;

    }



    // function check_meta(Request $request){

    //     $d = $request->all();

    //     $userdata = $this->get_updated_user($request->id);

    //     $details =  UserMeta::where("user_id", $request->id)->first();

    //     foreach($this->user_meta[$userdata->country] as $data){

    //         if(!isset($d[$data]) && $d[$data] == ''){

    //             return response()->json(['status' => 'error', 'message' => $data.' is needed', 200);

    //         }

    //     }

    // }



    public function update_personal_details(Request $request)

    {

        $data = $request->all();

        $user = $user = JWTAuth::parseToken()->authenticate();

        $u = UserPersonalDetails::where("user_id", $user->id)->first();

        if ($request->no_expiry_date && !$request->id_expiry_date) {

            $expiry_date = null;

        } else if (!$request->no_expiry_date && !$request->id_expiry_date) {

            $expiry_date = $u->id_expiry_date;

        } else {

            $expiry_date = $request->id_expiry_date;

        }

        if ($u) {

            $u->name = $request->name ? $request->name : $u->name;

            $u->address = $request->address ? $request->address : $u->address;

            $u->city = $request->city ? $request->city : $u->city;

            $u->zip_code = $request->zip_code ? $request->zip_code : $u->zip_code;

            $u->twitter_username = $request->twitter_username ? $request->twitter_username : $u->twitter_username;

            $u->dob = $request->dob ? $request->dob : $u->dob;

            $u->id_expiry_date = $expiry_date;

            $u->no_expiry_date = $request->no_expiry_date ? $request->no_expiry_date : 0;

            $u->explicit_content = $request->explicit_content ? $request->explicit_content : $u->explicit_content;

            $u->user_id = $user->id;

        } else {

            $u = new UserPersonalDetails();

            $u->name = $request->name ? $request->name : $u->name;

            $u->address = $request->address ? $request->address : $u->address;

            $u->city = $request->city ? $request->city : $u->city;

            $u->zip_code = $request->zip_code ? $request->zip_code : $u->zip_code;

            $u->twitter_username = $request->twitter_username ? $request->twitter_username : $u->twitter_username;

            $u->dob = $request->dob ? $request->dob : $u->dob;

            $u->no_expiry_date = $request->no_expiry_date ? $request->no_expiry_date : 0;

            $u->id_expiry_date = $expiry_date;

            $u->explicit_content = $request->explicit_content ? $request->explicit_content : $u->explicit_content;

            $u->user_id = $user->id;

        }





        $u->save();

        return response()->json(['status' => 'success', 'message' => $this->language[$request->lang]['MSG013']], 200);

        // }

    }



    public function change_password(Request $request)

    {

        $data = $request->all();



        $validator = Validator::make(

            $data,

            [

                'currentPassword' => ['required'],

                'newPassword' => ['required'],

            ]

        );



        if ($validator->fails()) {

            $validation_msgs = $validator->getMessageBag()->all();

            if (isset($validation_msgs[0])) {

                return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);

            }

        } else {



            $user = User::where("id", $request->user_id)->first();  

            if ($user) {

                if ($user && \Hash::check($request->currentPassword, $user->password)) // The passwords match...

                {

                    $user->password = \Hash::make($request->newPassword);

                    $user->save();

                    return response()->json(['status' => 'success', 'message' => $this->language[$request->lang]['MSG014']], 200);

                } else {

                    return response()->json(["status" => "error", "message" => $this->language[$request->lang]['MSG015']], 400);

                }

            }

        }

    }



    public function upload_image(Request $request)

    {

        $data = $request->all();



        $auth_user = JWTAuth::parseToken()->authenticate();

        $userId = $auth_user->id;



        if ($request->hasfile('image')) {

        $validator = Validator::make($data, [

            'image' => ['mimes:jpeg,jpg,png,JPG,JPEG,PNG|required|max:20000'],

        ]);

        if ($validator->fails()) {

            $validation_msgs = $validator->getMessageBag()->all();

            if (isset($validation_msgs[0])) {

                return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);

            }

        } else {

            $file = $request->file("image");



            $front_image = time() . "_" . str_replace(" ","_",$file->getClientOriginalName());

            $user = UserData::where("user_id", $userId)->first();

            if (!$user) {

                $user = new UserData();

                $user->user_id = $userId;

            }

            if ($request->type == 1) {

                if ($user->profile_image != "default.jpg" && file_exists(storage_path('public/profile-images/' . $user->profile_image))) {

                    unlink(storage_path('public/profile-images/' . $user->profile_image));

                }

                $path = $file->storeAs('public/profile-images', $front_image);

                $user->profile_image = $front_image;

                $user->save();

            } else if ($request->type == 2) {

                if ($user->cover_image != "default.jpg" && file_exists(storage_path('public/cover-images/' . $user->cover_image))) {

                    @unlink(storage_path('public/cover-images/' . $user->cover_image));

                }

                $path = $file->storeAs('public/cover-images', $front_image);

                $user->cover_image = $front_image;

                $user->save();

            } else if ($request->type == 4) {

                $user = $user = JWTAuth::parseToken()->authenticate();

                $up = UserPersonalDetails::where("user_id", $user->id)->first();



                if ($up && $up->photo_proof && file_exists(storage_path('public/photo-proof-images/' . $up->photo_proof))) {

                    unlink(storage_path('public/photo-proof-images/' . $up->photo_proof));

                }

                $path = $file->storeAs('public/photo-proof-images', $front_image);

                if ($up) {

                    $up->photo_proof = $front_image;

                    $up->save();

                } else {

                    $up = new UserPersonalDetails();

                    $up->user_id = $user->id;

                    $up->photo_proof = $front_image;

                    $up->save();

                }

            } else if ($request->type == 5) {

                $user = $user = JWTAuth::parseToken()->authenticate();

                $up = UserPersonalDetails::where("user_id", $user->id)->first();



                if ($up && $up->id_proof && file_exists(storage_path('public/id-proof-images/' . $up->id_proof))) {

                    unlink(storage_path('public/id-proof-images/' . $up->id_proof));

                }

                $path = $file->storeAs('public/id-proof-images', $front_image);



                if ($up) {

                    $up->id_proof = $front_image;

                    $up->save();

                } else {

                    $up = new UserPersonalDetails();

                    $up->user_id = $user->id;

                    $up->id_proof = $front_image;

                    $up->save();

                }

            }



            return response()->json(['status' => 'success', 'message' =>  $this->language[$request->lang]['MSG016']], 200);

        }

        }

        if ($request->post_type == 1) {

            $validator = Validator::make($data, [

                // 'images' => 'required',

                'images.*' => ['mimes:jpeg,jpg,png,gif,JPG,JPEG,PNG,GIF'],

            ]);

        } else if ($request->post_type == 2) {

            $validator = Validator::make($data, [

                // 'images' => 'required',

                'images.*' => ['mimes:mp4,ogx,oga,ogv,ogg,webm'],

            ]);

        } else if ($request->post_type == 4) {

            $validator = Validator::make($data, [

                // 'images' => 'required',

                'images.*' => ['mimes:mp3,wav'],

            ]);

        } else if ($request->post_type == 3){
            $validator = Validator::make($data, [

                // 'images' => 'required',
                'images.*' => ['mimes:mp3,wav,mp4,ogx,oga,ogv,ogg,webm,pdf,docx,jpeg,jpg,png,gif,JPG,JPEG,PNG,GIF'],

            ]);
        }



        if ($request->post_type != 0 && $validator->fails()) {

            $validation_msgs = $validator->getMessageBag()->all();

            if (isset($validation_msgs[0])) {

                return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);

            }

        } else {

            if(sizeof($request->access_level) === 0){

                return response()->json(['status' => 'error', 'message' => $this->language[$request->lang]['MSG083']], 400);

            }

            else{

            if ($request->type == 3) {

                $p = new Post();

                $p->user_id = $request->user_id;

                $p->title = $request->title;

                $p->message = $request->message;

                $p->post_type = $request->post_type;

                if(isset($request->is_scheduled) && $request->is_scheduled==1)
                {

                    $p->status = 0;

                    $minutes = $request->time_zone;

                    $date = strtotime($minutes.' minutes',strtotime($request->scheduled_datetime));

                    $p->is_scheduled = 1;

                    $p->scheduled_datetime = date('Y-m-d G:i:s',$date);
                    
                }

                $p->save();

                $postFiles = [];

                // Write here code

                $postPlans = array();



                foreach($request->access_level as $level){

                    array_push($postPlans, [

                        "post_id" => $p->id,

                        "access_level" => $level

                    ]);

                }

                try{

                    PostPlan::insert($postPlans);

                } catch (Exception $e) {

                    return response()->json(["status" => "error", "message" => $e->getMessage()], 200);

                }

                //Hande Polls & Options
                If($p->post_type == 3)
                {
                    $pollOption = new Polloptions();    
                    $pollOption->post_id = $p->id;
                    $pollOption->post_options = $request->pollOption;
                    $pollOption->post_options_answered = $request->pollOption;
                    $pollOption->is_end_date = $request->isEndDate;
                    $pollOption->end_date = $request->scheduleEndDate;
                    $pollOption->is_single_option = $request->isSingleAnswer;
                    $pollOption->save();
                }

                //Audio

                if ($p->post_type == 4) :



                    $validator = Validator::make(

                        $data,

                        [

                            'audio.*' => ['required', 'file'],

                        ]

                    );



                    if ($validator->fails()) {

                        $validation_msgs = $validator->getMessageBag()->all();

                        if (isset($validation_msgs[0])) {

                            return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);

                        }

                    } else {

                        $audio         = $request->file('audio');

                        $audio         = current($audio);

                        $audio_file    = time() . "_" . $audio->getClientOriginalName();

                        $audio->storeAs('public/post-images', $audio_file);

                        $isAudio = substr($audio->getMimeType(), 0, strlen("audio")) === "audio";



                        if ($request->hasfile('thumb')) :

                            $image         = $request->file('thumb');

                            $thumb_file    = time() . "_" . "thumb" . "_" . $image->getClientOriginalName();

                            $audio->storeAs('public/post-images', $thumb_file);

                        else :

                            $thumb_file = 0;

                        endif;



                        $postFiles[] = [

                            "post_id" => $p->id,

                            "file" => $audio_file,

                            "ext" => "mp3",

                            "thumbnail" => $thumb_file

                        ];

                        PostUpload::insert($postFiles);

                        event(new PostLike());

                        return response()->json(['status' => 'success', 'message' =>  $this->language[$request->lang]['MSG017']], 200);

                    }

                else :

                    if ($request->hasfile('images')) :

                        foreach ($request->file('images') as $file) {

                            $front_image = time() . "_" . str_replace(" ","_",$file->getClientOriginalName());

                            $file->storeAs('public/post-images', $front_image);

                            $isImage = substr($file->getMimeType(), 0, strlen("image")) === "image";

                            $thumbnail = "";

                            try {

                                if ($auth_user->settings() && $auth_user->settings()->watermark->photos && $isImage) {

                                    $img = Image::make(storage_path("app/public/post-images/$front_image"));

                                    $img->resize(null, 600, function ($constraint) {

                                        $constraint->aspectRatio();

                                        $constraint->upsize();

                                    });

                                    $orientation = $img->exif("Orientation");

                                    $rotateAngle = 0;

                                    switch ($orientation) {

                                        case 3:

                                        case 4:

                                            $rotateAngle = -180;

                                            break;

                                        case 5:

                                        case 6:

                                            $rotateAngle = -90;

                                            break;

                                        case 7:

                                        case 8:

                                            $rotateAngle = -270;

                                            break;

                                        default:

                                            break;

                                    }

                                    if ($rotateAngle !== 0) {

                                        $img->rotate($rotateAngle);

                                    }

                                    $text = "@$auth_user->username";

                                    if ($auth_user->settings()->watermark->text) {

                                        $text = $auth_user->settings()->watermark->text;

                                    }

                                    $img->text($text, $img->width() - (strlen($text) * 12), $img->height() - 20, function ($font) {

                                        $font->file(storage_path("app/public/fonts/Roboto/Roboto-Regular.ttf"));

                                        $font->size(20);

                                    });

                                    $img->save(storage_path("app/public/post-images/$front_image"));

                                } else if ($auth_user->settings() && $auth_user->settings()->watermark->videos && !$isImage) {

                                    $text = "@$auth_user->username";

                                    if ($auth_user->settings()->watermark->text) {

                                        $text = $auth_user->settings()->watermark->text;

                                    }

                                    $original = storage_path("app/public/post-images/$front_image");

                                    $front_image = "watermarked_$front_image";

                                    $cmd = 'ffmpeg -i "' . $original . '" -vf "drawtext=text=\'' . $text . '\':x=10:y=H-th-10:fontfile=' . storage_path("app/public/fonts/Roboto/Roboto-Regular.ttf") . ':fontsize=20:fontcolor=white" "' . storage_path("app/public/post-images/$front_image") . '"';

                                    exec($cmd);

                                    unlink($original);

                                }



                                if (!$isImage) {

                                    $thumbnail = uniqid() . '_' . pathinfo($front_image, PATHINFO_FILENAME) . '.jpg';

                                    /* $cmd = "ffmpeg -i \"" . storage_path("app/public/post-images/$front_image") . "\" -vcodec mjpeg -vframes 1 -an -f rawvideo -ss `ffmpeg -i input.mp4 2>&1 | grep Duration | awk '{print $2}' | tr -d , | awk -F ':' '{print ($3+$2*60+$1*3600)/2}'` \"" . storage_path("app/public/post-images/$thumbnail") . "\""; */

                                    $cmd = "ffmpeg -i \"" . storage_path("app/public/post-images/$front_image") . "\" -vf scale=-1:250 -vframes 1 -ss 00:00:10.000 \"" . storage_path("app/public/post-images/$thumbnail") . "\"";

                                    exec($cmd);

                                    // dump($cmd);

                                }

                            } catch (Exception $th) {

                                dump($th);

                            }



                            $postFiles[] = [

                                "post_id" => $p->id,

                                "file" => $front_image,

                                "ext" => $isImage ? "img" : "mp4",

                                "thumbnail" => $thumbnail

                            ];

                        }

                        PostUpload::insert($postFiles);


                        if ($request->streamingId) {

                            Comment::where(["streaming_id" => $request->streamingId])->update(["post_id" => $p->id]);

                        }

                    endif;

                endif;

                if($request->is_team_account)

                {

                    $teamAccount = new TeamLog();

                    $teamAccount->company_id    = $userId;

                    $teamAccount->user_id       = $request->member_id;

                    $teamAccount->action_type   = 'post';

                    $teamAccount->action_id     = $p->id;

                    $teamAccount->save();

                }

                event(new PostLike());

                return response()->json(['status' => 'success', 'message' =>  $this->language[$request->lang]['MSG017']], 200);

            }

        }

        }

    }





    public function reset_password(Request $request)

    {

        $res = ForgetPasswordLink::where(["link" => $request->token])

            ->where("expiry", ">", time())->first();

        if ($res) {

            $u = User::where("email", $res->email)->with('data')->first();

            $u->password = \Hash::make($request->password);

            $u->save();

            $token = $this->getToken($res->email, $request->password); // generate user token

            $u->auth_token = $token; // update user token

            $u->save();

            $uJson = [];
            $uJson['id'] = $u->id;
            $uJson['email'] = $u->email;
            $uJson['username'] = $u->username;
            $uJson['usertype'] = $u->user_type;
            $uJson['token'] = $token;
            $uJson['country'] = $u->data->country;

            ForgetPasswordLink::where(["link" => $request->token])->delete();

            $email = $u->email; 
            $username = $u->first_name.' '.$u->last_name;
            $contactUs = env("FRONT_URL").'/contact';
            $sub = "You have got yourself a new password";
            $template_file = "resources/views/reset_password_success.blade.php";

            if(isset($request->lang) && $request->lang == "de" ){
                $sub = "Dein Equiconx Passwort wurde erfolgreich zurückgesetzt.";
                $template_file = "resources/views/german/reset_password_success.blade.php";
                $contactUs = env("FRONT_URL").'/'.$request->lang.'/contact';
            }


            $msg = file_get_contents($template_file);

            $msg = str_replace("__username", $username, $msg);
            $msg = str_replace("__contactpage", $contactUs, $msg);

            header("X-Node: localhost");
            $headers  = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
            $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;
            mail($email, $sub, $msg, $headers, 2);

            $response = ['status' => 'success', 'message' =>  $this->language[$request->lang]['MSG018'], 'res' => $uJson];

            return response()->json($response, 200);

        } else {

            return response()->json(['status' => 'error', 'message' =>  $this->language[$request->lang]['MSG019']], 400);

        }

    }

    public function socialregister(Request $request)

    {

        $data = $request->all();



        $validator = Validator::make(

            $data,

            [

                'user_name'     => ['required', 'string', 'max:40'],

                'signup_via'    => ['required', 'string', 'max:3'],

                'sm_id'         => ['required', 'string'],

                'usertype'      => ['required', 'integer', 'max:3'],

                'email'         => ['required', 'string']

            ]

        );



        if ($validator->fails()) {

            $validation_msgs = $validator->getMessageBag()->all();

            if (isset($validation_msgs[0])) {

                return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);

            }

        } else {



            $uE = User::select('*')->where([['email', '=', $request->email]])->get()->first();

            if ($uE != '') {

                if ($uE->sm_id != $request->sm_id && $uE->signup_via != $request->signup_via) {

                    return response()->json(["status" => "error", "message" =>  $this->language[$request->lang]['MSG020']], 400);

                }



                $user = $uE;

   

                $token = $this->getToken($uE->email, $request->sm_id);

                $user->auth_token = $token;

                $user->save();



                $ip = $_SERVER['REMOTE_ADDR'];

                $user_data = $this->get_updated_user($user->id);

                $user->country = $user_data->country;

                $ua = getBrowser();
               
                $data = \Location::get($ip);

                if ($data) {

                    $country = $data->countryName;

                    $city = $data->cityName;

                } else {

                    $country = $user->country; // ""

                    $city = "";

                }


                $ul = new UserLastLogin();

                $ul->user_id = $user->id;

                $ul->login_date = time();

                $ul->ip = $ip;

                $ul->browser = $ua['name'] . " " . $ua['version'] . "," . $ua['platform'];

                $ul->location = $city . "," . $country;

                $ul->save();

                $response = ['status' => 'success', 'res' => $user];

                return response()->json($response, 200);

            } else {

                $username = $this->generateUserName($request->user_name);

                $sm_id = $request->sm_id;

                $date = date('Y-m-d h:i:s');

                $payload = [

                    'username' => $username,

                    'user_type' => $request->usertype,

                    'signup_via' => $request->signup_via,

                    'sm_id' => $request->sm_id,

                    'email' => $request->email,

                    'auth_token' => '',

                    'password' => \Hash::make($sm_id),

                    'email_verified_at' => $date

                ];

                $user = new User($payload);

                $user->email_verified_at = date("Y-m-d h:i:s");

                // $user->createAsStripeCustomer();

                if ($user->save()) {
                  
                    $token = $this->getToken($request->email, $request->sm_id);

                    $user->auth_token = $token;

                    $user->save();

                    $user_data = $this->get_updated_user($user->id);

                    $user->country = $user_data->country;

                    $ip = $_SERVER['REMOTE_ADDR'];



                    $ua = getBrowser();



                    $data = \Location::get($ip);
                    
                   

                    if ($data) {

                        $country = $data->countryName;

                        $city = $data->cityName;

                    } else {

                        $country = $user_country; // ""

                        $city = "";

                    }



                    $ul = new UserLastLogin();

                    $ul->user_id = $user->id;

                    $ul->login_date = time();

                    $ul->ip = $ip;

                    $ul->browser = $ua['name'] . " " . $ua['version'] . "," . $ua['platform'];

                    $ul->location = $city . "," . $country;

                    $ul->save();



                    $link = md5(uniqid());

                    $uvl = new UserVerificationLink();

                    $uvl->user_id = $user->id;

                    $uvl->link = $link;

                    $uvl->expiry = strtotime("+30 minutes");

                    $uvl->save();



                    $email = $user->email;
                    header("X-Node: localhost");
                    $headers = "MIME-Version: 1.0" . "\r\n";

                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;                
                    $sub = "Activate your Equiconx Account ";

                    $verifyLink = env("WEB_URL") . '/verify-email/' . $link;
                    $becomeMaster = env("FRONT_URL").'/master';
                    $findMaster = env('FRONT_URL').'/user/find-master';
                    $login = env('FRONT_URL');
                    $faq = env('FRONT_URL').'/faq';
                    $contactUs = env("FRONT_URL").'/contact';



                    $template_file = "resources/views/htmluitemplate.blade.php";



                    if(isset($request->lang) && $request->lang == "de" ){

                        $sub = "Aktiviere dein Equiconx Konto";

                        $template_file = "resources/views/german/htmluitemplate.blade.php";

                        $becomeMaster = env("FRONT_URL").'/'.$request->lang.'/master';
                        $findMaster = env('FRONT_URL').'/'.$request->lang.'/user/find-master';
                        $login = env('FRONT_URL').'/'.$request->lang.'/';
                        $faq = env('FRONT_URL').'/'.$request->lang.'/faq';
                        $contactUs = env("FRONT_URL").'/'.$request->lang.'/contact';


                    }



                    $msg = file_get_contents($template_file);

                    $msg = str_replace("__changeWithlink", $verifyLink, $msg);
                    $msg = str_replace("__contactpage", $contactUs, $msg);
                    $msg = str_replace("__genericmaster", $becomeMaster, $msg);
                    $msg = str_replace("__findmaster", $findMaster, $msg);
                    $msg = str_replace("__login", $login, $msg);
                    $msg = str_replace("__faqpage", $faq, $msg);

                    mail($email, $sub, $msg, $headers);



                    $response = ['status' => 'success', 'res' => $user];

                    return response()->json($response, 200);

                }else {

                    $response = ['status' => 'error', 'message' => 'Error'];

                    return response()->json($response, 400);

                }

            }

        }

    }

    public function get_notifications($user_id, Request $request)

    {

        $type = $request->type;

        $offset = $request->offset ? $request->offset : 0;

        $limit = $request->limit ? $request->limit : 1000;

        if ($type == 0) {

            $whr = ["rec_id" => $user_id];

        } else {

            $whr = ["rec_id" => $user_id, "type" => $type];

        }



        $res = Notification::select("notifications.*", "users.username", "user_data.profile_image")

            ->join("users", "users.id", "notifications.sender_id")

            ->join("user_data", "user_data.user_id", "notifications.sender_id")

            ->where($whr)

            ->whereRaw('notifications.sender_id != notifications.rec_id')->orderby("id", "desc")->offset($offset)->limit($limit)->get();



        foreach ($res as $r) {

            $r->profile_image = url('/') . '/public/storage/profile-images/' . $r->profile_image;

            $r->date = $r->created_at->diffForHumans();

        }



        $unread_count = Notification::where(["rec_id" => $user_id, "read" => 0])->count();

        $response = ['status' => 'success', 'res' => $res, 'unread_count' => $unread_count];

        return response()->json($response, 200);

    }



    public function read_notifications($user_id, Request $request)

    {

        $whr = ["rec_id" => $user_id];

        $res = Notification::where($whr)->update(["read" => 1]);

        $response = ['status' => 'success'];

        return response()->json($response, 200);

    }



    public function delete_account($user_id, Request $request)

    {
        $user = User::find($user_id);
        if($user){
        }else{
            $response = ['status' => 'failed', "message" => $this->language[$request->lang]['MSG098']];
        }
        $stripe_account_id = $user->stripe_connected_account_id;
        $s_delete = Account::retrieve($stripe_account_id);
        $s_delete->delete();
        $user = User::find($user_id)->delete();
        $response = ['status' => 'success', "message" =>  $this->language[$request->lang]['MSG021']];

        return response()->json($response, 200);

    }


    public function get_all_creator_profile(Request $request)

    {
        $keyword = request()->keyword;
         
        if(isset(request()->status) && request()->status != ''){
            $query = DB::select(DB::RAW("SELECT us.*, ud.* FROM users us INNER JOIN user_data ud ON us.id = ud.user_id WHERE username LIKE '%$keyword%' ORDER BY ud.display_name ASC"));
            foreach ($query as $q) {
                $q->profile_image = url('/') . '/public/storage/profile-images/' . $q->profile_image;
                $q->cover_image = url('/') . '/public/storage/cover-images/' . $q->cover_image;
            }
            $response = ['status' => 'success', "data" => $query];
            return response()->json($response, 200);
        }
        $auth_user = false;
        if($request->bearerToken())
        {
            $auth_user = JWTAuth::parseToken()->authenticate();

        }
        $data = request()->all();
        DB::enableQueryLog();
        $q = User::join("user_data", "users.id", "user_data.user_id")
            ->leftjoin("user_followers","user_followers.following","users.id");
        $q = $q->select(DB::Raw("users.username, user_data.display_name, user_data.country, count(user_followers.id) as followers, user_data.profile_image, user_data.cover_image, user_data.about"));
        
        if($auth_user!=false){
          $q = $q->where(function ($query) use ($keyword, $auth_user) {
                $query->where("users.id", "!=", $auth_user->id)
                    ->whereRaw("blocks.block_id  IS NULL")
                    ->whereNotNull("email_verified_at");
            });
          $q = $q->leftjoin("blocks", function ($query) use ($auth_user) {
                $query->on("users.id", '=', "blocks.user_id")
                    ->where("blocks.block_id", '!=', $auth_user->id);
            });    
        }    
        
        if ($keyword) {
            $q=  $q->where(function ($subQuery) use ($keyword) {
                $subQuery->where("users.username", "like", "%" . $keyword . "%")
                    ->orWhere("user_data.display_name", "like", "%" . $keyword . "%");
            });
        }
            
        if(isset($data['isCompany']) && $data['isCompany']==1)
        {
         $q = $q->where("users.user_type",'=','company');
        }    
        if(isset($data['location']) && $data['location']!='')
        {
            $location = $data['location'];
            $location = explode(',',$location);
            $q = $q->whereIn("user_data.country", $location);
            
        }
        $res = $q
            ->groupBy('users.id')
            ->groupBy('user_followers.following')
            ->limit(100)
            ->orderByRaw("IF(user_data.display_name IS NULL, users.username, user_data.display_name) asc")
            ->get();
        // $query = DB::getQueryLog();

        // $query = end($query);

        // print_r($query);    die();
        foreach ($res as $r) {
            $r->profile_image = url('/') . '/public/storage/profile-images/' . $r->profile_image;
            $r->cover_image = url('/') . '/public/storage/cover-images/' . $r->cover_image;
        }
        $response = ['status' => 'success', "data" => $res, "totalCount" => $q->count()];
        return response()->json($response, 200);
    }

    public function save_stripe_token(Request $request)
    {



        $creator_id =  $request->creatorId;

        $user_id = JWTAuth::parseToken()->authenticate()->id;

        $user = User::find($user_id);

        Stripe::setApiKey(env('STRIPE_SECRET'));



        $charge = Charge::create([



            "amount" => $request->amount * 100,



            "currency" => "inr",



            "source" => $request->id,

        ]);

        



        $p = new Payment();

        $p->user_id = $user_id;

        $p->charge_id = $charge->id;

        $p->amount = $request->amount;

        $p->status = "Success";

        $p->save();





        $s = new Subscription();

        $s->user_id = $user_id;

        $s->creator_id = $creator_id;

        $s->payment_id = $p->id;

        $s->save();



        $n = new Notification();

        $n->sender_id = $user_id;

        $n->rec_id = $creator_id;

        $n->notification =  $this->language[$request->lang]['MSG022'] . $user->username;

        $n->date = time();

        $n->url = "#";

        $n->type = 3;

        $n->save();

        event(new SendNotification($n));



        $response = ['status' => 'success', 'message' => $this->language[$request->lang]['MSG023']];

        return response()->json($response, 200);

    }

    public function enable_twofauth(Request $request)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $secret = '';

        $authenticator = new GoogleAuthenticator();

        $u = User::find($auth_user_id);

        if ($u->secret_token == '') {

            $secret = $authenticator->generateSecret();

            $u1 = User::where("id", $auth_user_id)->first();

            $u1->secret_token = $secret;

            $u1->save();

        } else {

            $secret =  $u->secret_token;

        }

        $qrCodeUrl = $authenticator->getUrl($auth_user->username, 'equiconx.com', $secret);

        $response = ['status' => 'success', 'data' => ['url' => $qrCodeUrl], 'message' => $this->language[$request->lang]['MSG024']];

        return response()->json($response, 200);

    }

    public function disable_twofauth()

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        $auth_user->two_way_auth = 0;

        $auth_user->save();

        return response()->json(["message" => "Two factor authentication disabled successfully."], 200);

    }

    public function verify_twofauth(Request $request)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }



        $authenticator = new GoogleAuthenticator();

        $u = User::find($auth_user_id);

        $secret = $u->secret_token;



        $res = $authenticator->checkCode($secret, $request->code);

        if ($res) {

            $u1 = User::where("id", $auth_user_id)->first();

            $u1->two_way_auth = 1;

            $u1->save();

            $response = ['status' => 'success', 'message' => $this->language[$request->lang]['MSG026']];

            return response()->json($response, 200);

        } else {

            $response = ['status' => 'failure', 'message' => $this->language[$request->lang]['MSG028']];

            return response()->json($response, 401);

        }

    }



    public function getBankDetails()

    {

        $user = JWTAuth::parseToken()->authenticate();

        $accountId = $user->stripe_connected_account_id;

        $data = [];

        if ($accountId) {

            $bankAccounts = Account::allExternalAccounts(

                $accountId,

                ['object' => 'bank_account']

            );

            $data = $bankAccounts->data;

            foreach ($data as $d) {

                $d->subscription_price = $user->data->subscription_price;

            }

        }

        $countries = CountrySpec::all(["limit" => 1000]);

        return response()->json(["accounts" => $data, "countries" => $countries->data], 200);

    }



    public function saveBankDetails(Request $request)

    {

        $user = JWTAuth::parseToken()->authenticate();

        $accountId = $user->stripe_connected_account_id;

        if ($accountId) {

            $bankData = [

                "object" => "bank_account",

                "country" => $request->country,

                "currency" => $request->currency,

                "account_holder_name" => $request->account_holder_name,

                "account_holder_type" => "individual",

                "account_number" => $request->account_number

            ];

            if ($request->routing_number) {

                $bankData["routing_number"] = $request->routing_number;

            }

            try {

                Account::createExternalAccount($accountId, [

                    "external_account" => $bankData

                ]);

            } catch (Exception $e) {

                return response()->json(["status" => "error", "message" => $e->getMessage()], 200);

            }

            // 

            $u = $user->data;



            $prevSubscription = $u->subscription_price;

            $u->subscription_price = $request->subscription_price ? $request->subscription_price : $u->subscription_price;

            $u->save();

            if ($prevSubscription !== $request->subscription_price) {

                $plan = new Plan();

                try {



                    $planData = StripePlan::create([

                        "amount" => $request->subscription_price * 100,

                        "currency" => $request->currency,

                        "interval" => "month",

                        "product" => $user->stripe_product_id,

                    ], ["stripe_account" => $user->stripe_connected_account_id]);

                } catch (Exception $e) {

                    return response()->json(["status" => "error", "message" => $e->getMessage()], 200);

                }



                // check if existsting plan existing

                if (count($user->plans) > 0) {

                    foreach ($user->plans as $key => $uplan) {



                        try {

                            $subscriptions = StripeSubscription::all([

                                'plan' => $uplan->plan_id,

                            ], ["stripe_account" => $user->stripe_connected_account_id]);

                        } catch (Exception $e) {

                            return response()->json(["status" => "error", "message" => $e->getMessage()], 200);

                        }



                        foreach ($subscriptions->data as $k => $subscription) {

                            try {

                                StripeSubscription::update($subscription->id, [

                                    'cancel_at_period_end' => false,

                                    'proration_behavior' => 'create_prorations',

                                    'items' => [

                                        [

                                            'id' => $subscription->items->data[0]->id,

                                            'plan' => $planData->id,

                                        ],

                                    ],

                                ], ["stripe_account" => $user->stripe_connected_account_id]);

                            } catch (Exception $e) {

                                return response()->json(["status" => "error", "message" => $e->getMessage()], 200);

                            }

                        }

                        try {

                            $planData = StripePlan::update(

                                $uplan->plan_id,

                                ["active" => false],

                                ["stripe_account" => $user->stripe_connected_account_id]

                            );

                        } catch (Exception $e) {

                            return response()->json(["status" => "error", "message" => $e->getMessage()], 200);

                        }

                        $uplan->delete();

                    }

                }

                // update in database

                $plan->plan_name = "$u->display_name($user->username)";

                $plan->plan_id = $planData->id;

                $plan->user_id = $user->id;

                $plan->currency = $request->currency;

                $plan->plan_interval = "monthly";

                $plan->amount = $request->subscription_price;

                $plan->save();

            }

            // 

            return response()->json(["message" => $this->language[$request->lang]['MSG029']], 200);

        } else {

            return response()->json(["message" => $this->language[$request->lang]['MSG030']], 400);

        }

    }

    public function deleteBankDetails(String $id)

    {

        $user = JWTAuth::parseToken()->authenticate();

        $accountId = $user->stripe_connected_account_id;

        if ($accountId) {

            Account::deleteExternalAccount($accountId, $id);

            return response()->json(["message" =>  $this->language[request()->lang]['MSG031']], 200);

        } else {

            return response()->json(["message" =>  $this->language[request()->lang]['MSG030']], 400);

        }

    }

    public function saveDefault(String $id)

    {

        $user = JWTAuth::parseToken()->authenticate();

        $accountId = $user->stripe_connected_account_id;

        if ($accountId) {

            Account::updateExternalAccount($accountId, $id, [

                "default_for_currency" => true

            ]);

            return response()->json(["message" => "Account set as default successfully."], 200);

        } else {

            return response()->json(["message" => "You haven't completed your profile details. Please complete your profile details first."], 400);

        }

    }



    public function saveCard(Request $request)

    {

        $user = JWTAuth::parseToken()->authenticate();

        $accountId = $user->stripe_id;

        if (!$accountId) {

            $user->createAsStripeCustomer();

            $accountId = $user->stripe_id;

        }

        $token = Token::create([

            "card" => [

                "number" => $request->number,

                "exp_month" => $request->expiryMonth,

                "exp_year" => $request->expiryYear,

                "cvc" => $request->cvv,

                "name" => $request->name,

            ]

        ]);

        Customer::createSource(

            $accountId,

            ['source' => $token->id]

        );

        return response()->json(["message" => $this->language[$request->lang]['MSG033']], 200);

    }



    public function getCards()

    {

        $user = JWTAuth::parseToken()->authenticate();

        $accountId = $user->stripe_id;

        if (!$accountId) {

            $user->createAsStripeCustomer();

            $accountId = $user->stripe_id;

        }

        $cards = Customer::allSources(

            $accountId,

            ['object' => 'card']

        );



        return response()->json($cards->data, 200);

    }

    public function deleteCard(String $id)

    {

        $user = JWTAuth::parseToken()->authenticate();
        
        $accountId = $user->stripe_id;

        Customer::deleteSource($accountId, $id);

        return response()->json(["message" => $this->language[request()->lang]['MSG034']], 200);

    }

    public function subscribe()

    {

        $creatorId = request()->user;

        $cardId = request()->card;

        $planId = request()->planId;

        $planDetails = Plan::where(["id" => $planId, "user_id" => $creatorId])->first();


        if (!$planDetails) {

            return response()->json(["status"=> "error", "message" => $this->language[request()->lang]['MSG035']], 400);

        }


        
        $subcriber = JWTAuth::parseToken()->authenticate();
        
        $creator = User::find($creatorId);


        $customerId = null;

        try {

            $plan = StripePlan::retrieve(

                $planDetails->plan_id,

                [

                    'stripe_account' => $creator->stripe_connected_account_id,

                ]

            );

            $userPlanId = $plan->id;

        } catch (Exception $th) {

            return response()->json(["message" => $this->language[request()->lang]['MSG035']], 400);

        }

        $prevDetails = Subscription::where(["user_id" => $subcriber->id, "creator_id" => $creator->id])->first();

        if ($prevDetails) {

            // 

            if ($prevDetails->status === "active") {

                $subscription = StripeSubscription::retrieve(

                    $prevDetails->subscription_id,

                    [

                        'stripe_account' => $creator->stripe_connected_account_id,

                    ]

                );

                @$subscription->delete();



                // return response()->json(["message" => "You are already subscribed to this user."], 400);

            }

            $s = $prevDetails;

        } else {

            $s = new Subscription();

            $s->user_id = $subcriber->id;

            $s->creator_id = $creator->id;

        }

        if (!$subcriber->stripe_id) {

            $subcriber->createAsStripeCustomer();

        }

        $customerSource = Customer::retrieve($subcriber->stripe_id);

        $customerSource->default_source = $cardId;

        $customerSource->save();



        $token = Token::create(

            [

                "customer" => $subcriber->stripe_id,

            ],

            [

                'stripe_account' => $creator->stripe_connected_account_id,

            ]

        );

        $customer = Customer::create(

            [

                "source" => $token->id

            ],

            [

                'stripe_account' => $creator->stripe_connected_account_id,

            ]

        );

        $customerId = $customer->id;
        
        $sub_details  = current(DB::select("SELECT users.username, user_data.country FROM users INNER JOIN user_data on users.id = user_data.user_id  WHERE users.email = '$subcriber->email'"));
        if($sub_details->country == 'US'){
            $subcriber_detail = current(DB::select("SELECT users.username, user_data.country, countries.country_code, countries.percentage, countries.tax FROM users INNER JOIN user_data on users.id = user_data.user_id INNER JOIN user_meta ON user_meta.user_id = users.id  INNER JOIN  countries on countries.country_code = user_meta.value WHERE users.email = '$subcriber->email'"));
        }else{
            $subcriber_detail =  current(DB::select("SELECT users.username, user_data.country, countries.country_code, countries.percentage, countries.tax FROM users INNER JOIN user_data on users.id = user_data.user_id INNER JOIN countries on countries.country_code = user_data.country WHERE users.email = '$subcriber->email' "));
        }   
       
        
        $tax_percentage = $planDetails->amount * ($subcriber_detail->percentage / 100);
        $platform_fee   = $planDetails->amount * (7.5 / 100);
        $total_tax = $tax_percentage + $platform_fee;
        $total_amount = $planDetails->amount + $tax_percentage;
        $x = ($total_tax * 100) / $total_amount;
        $x = number_format($x, 2, '.', '');
        $masterFees = $planDetails->amount - $platform_fee;       

        $d_name = $subcriber_detail->country_code;
        $des = $subcriber_detail->tax." ".$d_name;
        $tax_per = number_format((float)$subcriber_detail->percentage, 2, '.', '');

        try{
        
            $taxObject = TaxRate::create([
                    'display_name' => $d_name,
                    'description' => $des,
                    'percentage' => $tax_per,
                    'inclusive' => false,
                ],[

                    'stripe_account' => $creator->stripe_connected_account_id,

                ]);
            
            //$taxObject = TaxRate::Retrieve(['id'=>'txr_1HwQjCEvN8KBirpn62LVHX8E']);

            $subscription = StripeSubscription::create(

                [

                    "customer" => $customerId,

                    "items" => [

                        ["plan" => $userPlanId,   "tax_rates" => [$taxObject],]
                        

                    ],
                

                    "collection_method" => "charge_automatically",

                    "application_fee_percent" => $x,

                    "payment_behavior" => "error_if_incomplete",

                ],

                [

                    'stripe_account' => $creator->stripe_connected_account_id,

                ]

            );
        }catch (Exception $th) {
            return response()->json(["message" => $th], 400);
        }


        $s->subscription_id = $subscription->id;

        $s->subscriber_id = $customerId;

        $s->payment_intent_id = $cardId;

        $s->plan_id = $planId;

        $s->status = "active";

        $s->save();

        // if ($creator->settings() && $creator->settings()->notifications->site->subscriber) {

        $n = new Notification();

        $n->sender_id = $subcriber->id;

        $n->rec_id = $creator->id;

        $n->notification = '{{SUBSCRIBED}} ' . $subcriber->username;

        $n->date = time();

        $n->url = env("WEB_URL") . "/public-profile/" . $subcriber->username;

        $n->type = 3;

        $n->save();

        event(new SendNotification($n));

        // }

        // create payment for subscriber

        $payment = new Payment();

        $payment->user_id = $subcriber->id;

        $payment->charge_id = $subscription->id;

        $payment->plan_id = $planId;

        $payment->description = "Paid for monthly subscription for <a href='/$creator->username'>{$creator->data->display_name}</a>";

        $payment->amount = $planDetails->amount;

        $payment->currency = $planDetails->currency;

        $payment->type = "expense";

        $payment->status = $this->language[request()->lang]['MSG096'];

        $payment->save();

        // create payment for creator

        $payment = new Payment();

        $payment->user_id = $creator->id;

        $payment->charge_id = $subscription->id;

        $payment->plan_id = $planId;

        $payment->description = "Recieved for monthly subscription from <a href='/$subcriber->username'>{$subcriber->data->display_name}</a>";

        $payment->amount = $planDetails->amount;

        $payment->currency = $planDetails->currency;

        $payment->type = "income";

        $payment->status = "success";

        $payment->save(); 



        $check_follow =  DB::select(DB::RAW("SELECT * FROM user_followers um WHERE um.user_id =".$subcriber->id." and um.following =".$creator->id.""));

        

        if(!$check_follow){

            $follow = new UserFollower();

            $follow->user_id = $subcriber->id;

            $follow->following = $creator->id;

            $follow->save();  

        }else{

        }


        $taxation = new Taxation();
        $taxation->customer_id  = $subcriber->id;
        $taxation->master_id    = $creator->id;
        $taxation->platform_txn_amount = $platform_fee;
        $taxation->country_txn_amount = $tax_percentage;
        $taxation->total_txn_amount   = $total_tax;
        $taxation->country_code = $sub_details->country;
        $taxation->save();
         
        $email = $subcriber->email;
        header("X-Node: localhost");
        $headers = "MIME-Version: 1.0" . "\r\n";

        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;
        $sub = "Your Equiconx membership subscription is confirmed";
        $master_name = $creator->data->display_name;
       
        $login_redirect = env('FRONT_URL').'/user/profile';
        $contactUs = env("FRONT_URL").'/contact'; 
        
        if(isset(request()->lang) && request()->lang == 'de'){
            $login_redirect = env('FRONT_URL').'/'.request()->lang.'/user/profile';
            $contactUs = env("FRONT_URL").'/'.request()->lang.'/contact'; 
            
        }


        $template_file = "resources/views/subscribed_confirmed.blade.php";



        if(isset(request()->lang) && request()->lang == "de" ){

            $sub = "Du bist jetzt Teil der Equiconx Familie";

            $template_file = "resources/views/german/subscribed_confirmed.blade.php";

        }



        $msg = file_get_contents($template_file);

        $msg = str_replace("__mastername", $creator->username, $msg);

        $msg = str_replace("__changeWithlink",$login_redirect , $msg);
        $msg = str_replace("__contactpage",$contactUs , $msg);

        mail($email, $sub, $msg, $headers);

        // email for master

        $emailM = $creator->email;
        $pupilname = $subcriber->username;
        $planname = $planDetails->plan_name;
        header("X-Node: localhost");
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;
        $sub = "A new pupil has subscribed to your membership";
        $login_redirect = env('FRONT_URL').'/user/profile';
        $contactUs = env("FRONT_URL").'/contact'; 

        if(isset(request()->lang) && request()->lang == 'de'){
            $login_redirect = env('FRONT_URL').'/'.request()->lang.'/user/profile';
            $contactUs = env("FRONT_URL").'/'.request()->lang.'/contact'; 
            
        }

        $template_file = "resources/views/subscribed_confirmed_master.blade.php";

        if(isset(request()->lang) && request()->lang == "de" ){
            $sub = "Ein neuer Schüler hat deine Mitgliedschaft abboniert";
            $template_file = "resources/views/german/subscribed_confirmed_master.blade.php";
        }

        $msg = file_get_contents($template_file);

        $msg = str_replace("__mastername", $creator->username, $msg);
        $msg = str_replace("__pupilname", $pupilname, $msg);
        $msg = str_replace("__planname", $planname, $msg);
        $msg = str_replace("__changeWithlink",$login_redirect , $msg);
        $msg = str_replace("__contactpage",$contactUs , $msg);

        mail($emailM, $sub, $msg, $headers);

        // Master Invoice mail 
        $totalAmount = number_format($total_amount, 2, '.', '');
        $sub = 'A new pupil has subscribed to your membership';
        
        // Pupil Invoice mail
        $tax_percentage = number_format($tax_percentage, 2, '.', '');
        $total_amount = number_format(request()->totalAmount, 2, '.', '');
        $basicFees = number_format($planDetails->amount, 2, '.', '');
        $countryTaxType = $subcriber_detail->tax;
        $contactEmail = "contact@equiconx.com";
        $sub = "Your Equiconx receipt ";
        $template_file = "resources/views/invoice/pupil_invoice.blade.php";

        if(isset(request()->lang) && request()->lang == "de" ){
            $tax_percentage = explode('.', $tax_percentage);
            $tax_percentage = $tax_percentage[0].','.$tax_percentage[1];
            $total_amount = explode('.', $total_amount);
            $total_amount = $total_amount[0].','.$total_amount[1];
            $basicFees = explode('.', $basicFees);
            $basicFees = $basicFees[0].','.$basicFees[1];
            $sub = "Deine Equiconx Rechnung";
            $template_file = "resources/views/invoice/pupil_invoice_german.blade.php";
        }

        $msg = file_get_contents($template_file);

        $msg = str_replace("__masterfees", $basicFees, $msg);
        $msg = str_replace("__countrytaxtype", $countryTaxType, $msg);
        $msg = str_replace("__countrytax", $tax_percentage, $msg);
        $msg = str_replace("__totalamount", $total_amount , $msg);
        $msg = str_replace("__currencytype", $planDetails->currency , $msg);
        $msg = str_replace("__contactemail",$contactEmail , $msg);
        $msg = str_replace("__contactpage",$contactUs , $msg);

        mail($email, $sub, $msg, $headers);

        // Admin Invoice mail

        $template_file = "resources/views/invoice/admin_invoice.blade.php";

        $msg = file_get_contents($template_file);

        $msg = str_replace("__mastername", $creator->username, $msg);
        $msg = str_replace("__username", $pupilname, $msg);
        $msg = str_replace("__masterfees", $basicFees, $msg);
        $msg = str_replace("__platformfees",$platform_fee , $msg);
        $msg = str_replace("__countrytax",$tax_percentage , $msg);
        $msg = str_replace("__totalamount", $basicFees , $msg);
        $msg = str_replace("__currencytype", $planDetails->currency, $msg);

        // mail("contact@equiconx.com", $sub, $msg, $headers);

        return response()->json(["message" => $this->language[request()->lang]['MSG036'], "data" => $subscription], 200);

    }



    public function cancelSubscription(Request $request)

    {

        $creatorId = request()->user;

        $subcriber = JWTAuth::parseToken()->authenticate();

        $creator = User::find($creatorId);
        $plan_id = $request->plan_id;

        

        $userSubscriptionDetails = Subscription::where(["user_id" => $subcriber->id, "creator_id" => $creator->id, "status" => "active"])->first();

        if (!$userSubscriptionDetails) {

            return response()->json(["message" => $this->language[$request->lang]['MSG037']], 400);

        }

        $subscription = StripeSubscription::retrieve(

            $userSubscriptionDetails->subscription_id,

            [

                'stripe_account' => $creator->stripe_connected_account_id,

            ]

        );

        $plan = Subscription::where(["plan_id" => $plan_id])->first();  

        if($plan->plan_id == $plan_id)

        {

        $subscription->delete();
        $userSubscriptionDetails->status = "cancelled";
        $userSubscriptionDetails->save();

        $email = $subcriber->email;
        header("X-Node: localhost");
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;
        $sub = "Your membership subscription is cancelled ";     
        $contactLink = env("FRONT_URL")."/contact";
        $findMaster = env("FRONT_URL")."/search-master";
        
        $master_name = $creator->username;

        if($request->lang == 'de'){
            $contactLink = env("FRONT_URL").'/'.$request->lang."/contact";
            $findmaster = env("FRONT_URL").'/'.$request->lang."/search-master";
        }

        $template_file = "resources/views/cancel_subscription.blade.php";
        if(isset(request()->lang) && request()->lang == "de" ){
            $sub = "Deine Mitgliedschaft wurde erfolgreich gekündigt.";
            $template_file = "resources/views/german/cancel_subscription.blade.php";
        }
        $msg = file_get_contents($template_file);
        $msg = str_replace("__mastername", $master_name, $msg);
        $msg = str_replace("__contactpage", $contactLink, $msg);
        $msg = str_replace("__findmaster", $findMaster, $msg);
        
        mail($email, $sub, $msg, $headers);
        return response()->json(["message" => $this->language[$request->lang]['MSG038']], 200);

        }



        return response()->json(["message" => $this->language[$request->lang]['MSG039']], 200);

    }

    public function update_user_settings(Request $request)

    {

        $user =  JWTAuth::parseToken()->authenticate();

       

        $user->user_preferences = $request->settings;

        $user->save();

        return response()->json(["message" => $this->language[$request->lang]['MSG040']], 200);

    }

    public function get_unread_notification_count()

    {

        $user =  JWTAuth::parseToken()->authenticate();

        $count = Notification::where(["rec_id" => $user->id, "read" => 0])->count();

        return response()->json(["count" => $count], 200);

    }

    public function emitSendNotification()

    {

        $notification = new Notification(request()->all());

        $notification->save();

        event(new SendNotification($notification));

        return response()->json(["message" => "Event triggered successfully."], 200);

    }

    public function become_master(Request $req)

    {

        $data = $req->all();

        $validator = Validator::make(

            $data,

            [

                'user_id' =>  ['required', 'string', 'max:255'],

                // 'country' =>  ['required', 'string', 'max:10'],

                'no_offence' => ['required', 'integer', 'max:1'],

                'agree_terms' => ['required', 'integer', 'max:1'],

                'content_type' => ['required']

            ]

        );

        if ($validator->fails()) {

            $validation_msgs = $validator->getMessageBag()->all();

            if (isset($validation_msgs[0])) {

                return response()->json(["status" => "error", "message" => $validation_msgs[0]], 400);

            }

        } else {



            $u = User::find($req->user_id);

            $preferences = $u->settings();

            $preferences->content_type = json_encode($req->content_type);

            $preferences->no_offencive_content = $req->no_offence;

            $preferences->agree_terms = $req->agree_terms;

            $u->user_preferences = json_encode($preferences);

            $u->user_type = 'master';

            $u->save();

            // $uD = UserData::where("user_id", $req->user_id)->first();

            // // $uD->country = $req->country;

            // $uD->save();

            // $user = User::find($uD->user_id);

            $email = $u->email;
            $username = $u->username;
            header("X-Node: localhost");
            $headers = "MIME-Version: 1.0" . "\r\n";

            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;                

            $sub = "10 Tips to get you started with Equiconx";
            $contactUs = env("FRONT_URL").'/contact';
            $blog = 'https://www.equiconx.com/blog/';


            $template_file = "resources/views/master_started.blade.php";



            if(isset($req->lang) && $req->lang == "de" ){

                $sub = "10 Top Tipps um das Meiste aus der Equiconx Plattform zu holen ";

                $template_file = "resources/views/german/master_started.blade.php";

                $blog = 'https://www.equiconx.com/blog/de/';

                $contactUs = env("FRONT_URL").'/'.$req->lang.'/contact';
            }



            $msg = file_get_contents($template_file);

            $msg = str_replace("__username", $username, $msg);
            $msg = str_replace("__contactpage", $contactUs, $msg);
            $msg = str_replace("__changeWithlink",$blog,$msg);

            mail($email, $sub, $msg, $headers);



            $user = $this->get_updated_user($req->user_id);

            $response = ['status' => 'success', 'message' => $this->language[$req->lang]['MSG041'], 'res' => $user];
            

            return response()->json($response, 200);

        }

    }



    public function receive_payment(Request $req)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }



        //$result=Payment::where(['user_id'=>$auth_user_id,'type'=>'income']);

        $payments = Payment::join("payments as ps2", "ps2.charge_id", "payments.charge_id")

            ->join("users", "users.id", "ps2.user_id")

            ->join("user_data", "ps2.user_id", "user_data.user_id")

            ->select("payments.id", "ps2.user_id", "payments.charge_id", "payments.description", "payments.amount", "payments.currency", "payments.type", "payments.created_at" , "payments.status", "users.username", "user_data.profile_image", "user_data.display_name")

            ->where(['payments.user_id' => $auth_user_id, 'payments.type' => 'income'])

            ->whereRaw('payments.user_id != ps2.user_id')

            ->orderBy("payments.id", "desc")->get();



        foreach ($payments as $p) {

            $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;

        }





        return response()->json(['status' => 'success', 'res' => $payments], 200);

    }

    public function paid_payment(Request $req)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        // DB::enableQueryLog();    

        // $query = DB::getQueryLog();

        //$query = end($query);

        //print_r($query);    



        $payments = Payment::join("payments as ps2", "ps2.charge_id", "payments.charge_id")

            ->join("users", "users.id", "ps2.user_id")

            ->join("user_data", "user_data.user_id", "ps2.user_id")

            ->select("payments.id", "ps2.user_id", "payments.charge_id", "payments.description", "payments.amount", "payments.currency", "payments.type", "payments.status", "users.username", "user_data.profile_image", "user_data.display_name")

            ->where(['payments.user_id' => $auth_user_id, 'payments.type' => 'expense'])

            ->whereRaw('payments.user_id != ps2.user_id')

            ->orderBy("ps2.id", "desc")->get();



        foreach ($payments as $p) {



            $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;

        }



        return response()->json(['status' => 'success', 'res' => $payments], 200);

    }

    public function active_membership()

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;



            DB::enableQueryLog();

            

            $payments = DB::select("SELECT subscription.creator_id, subscription.plan_id,user_plans.currency, user_plans.plan_name, user_plans.description, user_plans.amount, user_plans_details.benefits, subscription.status, users.username,user_data.display_name, user_data.profile_image FROM subscription, user_plans,user_plans_details,users, user_data WHERE subscription.user_id = ? AND subscription.status = 'active' AND subscription.plan_id = user_plans.id AND subscription.plan_id = user_plans_details.plan_id AND subscription.creator_id = users.id AND subscription.creator_id = user_data.user_id", [$auth_user_id]);



            

            foreach ($payments as $p) {



                $p->profile_image = url('/') . '/public/storage/profile-images/' . $p->profile_image;

            }



            return response()->json(['status' => 'success', 'res' => $payments], 200);

        }

    }

    public function blockUser(Request $req)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }



        $isBlock = Block::where(['user_id' => $auth_user_id, 'block_id' => $req->block_user_id])->first();



        if ($isBlock != '') {

            return response()->json(['status' => 'success', 'res' => 'User is blocked already!'], 200);

        }

        $blockUser = new Block();

        $blockUser->user_id = $auth_user_id;

        $blockUser->block_id = $req->block_user_id;

        $blockUser->reason = $req->reason;

        $blockUser->save();

        return response()->json(['status' => 'success', 'res' => $this->language[$req->lang]['MSG042']], 200);

    }

    public function blocklist(Request $req)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $url = url('/') . '/public/storage/profile-images/';

        $users = DB::select("SELECT user_data.user_id, display_name, CONCAT('$url' ,profile_image) as profile_image, reason, blocks.created_at as blockdate FROM user_data, blocks WHERE user_data.user_id=blocks.block_id AND blocks.user_id=?;", [$auth_user_id]);



        if ($users == '') {

            return response()->json(['status' => 'error', 'users' => 'Empty!'], 200);

        }

        return response()->json(['status' => 'success', 'users' => $users], 200);

    }

    public function unblockUser(Request $req)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }
       
        DB::delete('DELETE FROM blocks WHERE blocks.user_id=? AND blocks.block_id=?', [$auth_user_id, $req->block_user_id]);

        return response()->json(['status' => 'success', 'res' => $this->language[$req->lang]['MSG043']], 200);

    }

    public function reportUser(Request $req)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $reportUser = new Report();

        $reportUser->user_id = $auth_user_id;

        $reportUser->report_id = $req->report_user_id;

        $reportUser->reason = $req->reason;

        $reportUser->save();

        return response()->json(['status' => 'success', 'res' => $this->language[$req->lang]['MSG044']], 200);

    }

    public function becomeCompany()

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $user = User::find($auth_user_id);

        if ($user->user_type == 'user' || $user->user_type == 'company') {

            return response()->json(['status' => 'failure', 'res' => $this->language[request()->lang]['MSG045']], 200);

        }

        $user->user_type = 'company';

        $user->save();

        $company_teamlead = new CompanyUser();

        $company_teamlead->company_id = $auth_user_id;

        $company_teamlead->user_id = $auth_user_id;

        $company_teamlead->role = 'teamlead';

        $company_teamlead->status = 1;

        $company_teamlead->save();

        return response()->json(['status' => 'success', 'res' => $this->language[request()->lang]['MSG046']], 200);

    }

    public function addTeamMember(Request $request)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $user_id = request()->user_id;

        $ub = User::find($user_id);



        $u = UserData::where("user_id", $request->user_id)->first();

        $company_name = 'Anonymous';

        if ($u->display_name != '') {

            $company_name = $u->display_name;

        }

        $company_teamlead = new CompanyUser();

        $company_teamlead->company_id = $auth_user_id;

        $company_teamlead->user_id = $user_id;

        $company_teamlead->role = 'teamlead';

        $company_teamlead->enable_token = $token = Str::random(32);

        $company_teamlead->status = 0;

        $company_teamlead->save();

        $email = $ub->email;
        header("X-Node: localhost");
        $headers = "MIME-Version: 1.0" . "\r\n";

        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;

        $sub = "Equiconx | Team Invitation";

        $verifyLink = env("FRONT_URL") .'/'.$request->lang. '/join-team/' . $token;

        $msg = "$company_name invited you to join as team mate to manage their account, To accept the invitation <a href='$verifyLink'>Click Here</a>";

        mail($email, $sub, $msg, $headers);



        return response()->json(['status' => 'success', 'res' => $this->language[$request->lang]['MSG091']], 200);

    }

    public function deleteTeamMember(Request $request)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $u_id = $request->user_id;

        //    CompanyUser::delete()->where('user_id','=',$u_id)->first();

        $n = DB::select("SELECT * FROM company_user WHERE user_id = ?", [$u_id]);

        if (!$n) {

            return response()->json(['status' => 'failed', 'res' => $this->language[$request->lang]['MSG047']]);

        }

        DB::delete("DELETE FROM company_user WHERE user_id = ?", [$u_id]);

        return response()->json(['status' => 'success', 'res' => $this->language[$request->lang]['MSG048']]);

    }



    public function joinTeam()

    {

        $token = request()->token;

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $CU = CompanyUser::where('enable_token', '=', $token)->where('user_id', '=', $auth_user_id)->first();



        if ($CU == '') {

            $response = ['status' => 'failure', "res" => $this->language[request()->lang]['MSG049']];

        } else {

            $CU->status = 1;

            $CU->save();

            $response = ['status' => 'success', "res" => $this->language[request()->lang]['MSG050']];

        }

        return response()->json($response);

    }

    public function myTeams(Request $request)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        // $CU = CompanyUser::JOIN('users','users.id','company_user.company_id')

        // ->JOIN('user_data','user_data.user_id','users.id')

        // ->where('company_user.user_id','=',$auth_user_id)

        // ->where('company_user.status','=','1')->get();


        DB::enableQueryLog();

        $url = url('/') . '/public/storage/profile-images/';

        $CU = DB::select("SELECT DISTINCT users.id, users.username, CONCAT('https://equiconx-api.com/public/storage/profile-images/',user_data.profile_image) as profile_image  FROM users, company_user, user_data WHERE users.id = company_user.user_id  AND users.id = user_data.user_id AND company_user.company_id != company_user.user_id AND company_user.company_id = $auth_user_id");

        // echo "<pre>"; print_r(DB::getQueryLog()); die();

        if (!$CU) {

            $response = ['status' => 'failure', "res" => $this->language[request()->lang]['MSG049']];

        } else {

            $response = ['status' => 'success', "res" => $this->language[$request->lang]['MSG090'], "data" => $CU];

        }

        return response()->json($response);

    }

    public function searchUser(Request $request)

    {

        $keyword = request()->keyword;

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $q = User::join("user_data", "users.id", "user_data.user_id")

            ->leftjoin("blocks", function ($query) use ($auth_user) {

                $query->on("users.id", '=', "blocks.user_id")

                    ->where("blocks.block_id", '=', $auth_user->id);

            })

            ->select("users.username", "users.id", "users.email", "user_data.display_name", "user_data.profile_image", "user_data.about")

            ->where(function ($query) use ($keyword, $auth_user) {

                $query->where("users.id", "!=", $auth_user->id)

                    ->whereRaw("blocks.block_id  IS NULL")

                    ->whereNotNull("email_verified_at");

                if ($keyword) {

                    $query->where(function ($subQuery) use ($keyword) {

                        $subQuery->where("users.username", "like", "%" . $keyword . "%")

                            ->orWhere("user_data.display_name", "like", "%" . $keyword . "%");

                    });

                }

            })

            ->where("users.user_type", '=', 'user');

        $res = $q

            ->limit(100)

            ->orderByRaw("IF(user_data.display_name IS NULL, users.username, user_data.display_name) asc")

            ->get();



        foreach ($res as $r) {

            $r->profile_image = url('/') . '/public/storage/profile-images/' . $r->profile_image;

        }

        $response = ['status' => 'success', "data" => $res, "totalCount" => $q->count()];

        return response()->json($response, 200);

    }

    public function analytics(Request $request)

    {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $p = $request->plan_id;
        $m = $request->month;
        $y = $request->year;

        $query = "select payments.amount, payments.currency,SUM(payments.amount) as total, user_plans.plan_name, user_plans.id, payments.type, payments.status, payments.created_at FROM payments INNER JOIN subscription on subscription.subscription_id = payments.charge_id LEFT JOIN user_plans on user_plans.id = subscription.plan_id where payments.user_id = $auth_user_id AND payments.type = 'income'";
        
        $query_end = " GROUP BY user_plans.id";

        if($p != -1){
            $query .= " AND user_plans.id = $p";
        }

        if($y != 00){
            $query .= " AND year(payments.created_at) = $y";
        }


        if($m != 00){
            $query .= " AND month(payments.created_at) = $m";
        }

        $query .= $query_end;

        $result = DB::select($query);

        if($result){
            return response()->json(['status' => 'success', "data" => $result], 200);
        }else{
            return response()->json(['status' => 'failed', "data" =>  $this->language[$request->lang]['MSG089']], 200);
        }

    }

    public function getAnalytics(Request $request) {

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        
        $query = "SELECT up.id, up.plan_name,IF(ISNULL(p.amount),0, SUM(p.amount)) as total,p.currency,
                  YEAR(p.created_at) as year , DATE_FORMAT(p.created_at,'%b') as month 
                  FROM `user_plans` up 
                  INNER JOIN payments p on p.plan_id=up.id AND p.type ='income' 
                  where up.user_id = $auth_user_id group by up.plan_id, YEAR(p.created_at), 
                  MONTH(p.created_at),p.currency ORDER BY year DESC, month ASC";

        $result = DB::select($query);

        if($result){
            return response()->json(['status' => 'success', "data" => $result], 200);
        }else{
            return response()->json(['status' => 'failed', "data" =>  $this->language[$request->lang]['MSG089']], 200);
        }

    }

    

    public function removeMessage(Request $request){

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            

            $auth_user_id = $auth_user->id;

        }

        Chat::where('id','=',$request->message)->where('sender_id','=',$auth_user_id)->delete();

        if($request->is_team_account)

        {

            $teamAccount = new TeamLog();

            $teamAccount->company_id    = $auth_user_id;

            $teamAccount->user_id       = $request->member_id;

            $teamAccount->action_type   = 'post';

            $teamAccount->action_id     = $request->message;

            $teamAccount->save();

        }

        return response()->json(['status' => 'success', $this->language[$request->lang]['MSG051']], 200);

    }

    public function login_as_team(Request $request){

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            $auth_user_id = $auth_user->id;

        }

        $user = User::find($auth_user_id);

        $teams = $user->teams()->get();



        $isValid = false;

        foreach($teams as $team)

        {

            if($team->company_id==$request->teamId)

            {

                $isValid = true;

            }

        }

        if($isValid==true)

        {

            $newUser = User::find($request->teamId);

            if (!$userToken=JWTAuth::fromUser($newUser)) {

                return response()->json(['error' => $this->language[$request->lang]['MSG089']], 401);

            }

            $newUser->auth_token = $userToken;

            $newUser->save();



            $response = ['status' => 'success', 'res' => $newUser];

            return response()->json($response, 200);

        }

        else

        {

            $response = ['status' => 'failure', 'message' => $this->language[$request->lang]['MSG053']];

            return response()->json($response, 200);

        }

    }

    public function get_user_team(Request $request){

        $auth_user = JWTAuth::parseToken()->authenticate();

        if ($auth_user) {

            

            $auth_user_id = $auth_user->id;

        }

        $urlProfile     = url('/') . '/public/storage/profile-images/';

        $user = CompanyUser::where('company_user.user_id','=',$auth_user_id)

                ->join('users','users.id','company_user.company_id')

                ->join('user_data','user_data.user_id','users.id')

                ->select(DB::Raw('company_user.id as association_id, company_user.role,company_id as team_id, company_user.created_at as member_since, user_data.display_name as team_name, CONCAT("'.$urlProfile.'",user_data.profile_image) as profile_image'))

                ->get();

        $response = ['status' => 'success', 'message' => $this->language[$_GET['lang']]['MSG090'],'data'=>$user];

        return response()->json($response, 200);

        

    }

    public function get_country_wise_tax(){
        $data = [];
		if(isset($_GET['country']) && $_GET['country'] != ''){
            $s_country = $_GET['country'];
            if($s_country == 'US'){
                $code = $_GET['state'];
                $query = DB::select("select country, tax, percentage from countries where country_code = '$code' AND tax = 'SST'");
            }else{
			    $query = DB::select("select country, tax, percentage from countries where country_code = '$s_country'");
            }
              		
            $data['tax'] = current($query);
		}else{
			return response()->json(['response' => 'error','message' => $this->language[$_GET['lang']]['MSG087']], 400);
		}
		
		return response()->json($data, 200);
    }
    
    public function welcome_email()
    {
            $email = "kalika01@mailinator.com";

            header("X-Node: localhost");
            $headers  = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
            $headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;  

            $sub = "Welcome";

            $template_file = "resources/views/welcome_email.blade.php";

            $msg = file_get_contents($template_file);
            $sent = mail($email, $sub, $msg, $headers);

            print_r($sent);
            // print_r($msg);
            if($sent){
                echo "mail is sent successfully ";
            }else{
                echo "unable to send mail";
            }
            echo "<pre>";
            print_r("mail send");
            die("end");

    }

    public function subscribe_newsletter(Request $request){
        

        $first_name = $request->first_name;
        $last_name  = $request->last_name;
        $email =      $request->email;

        $params['apikey']= env('MC_API_KEY');
    
        $data = array(
            'apikey'        => '4393f05c3fb923493a2b7cb42a743f49-us17',
            'email_address' => $email,
            'status'        => 'subscribed',
            'merge_fields'  => ['FNAME'=> $first_name ,'LNAME'=>$last_name]
      );
    $mch_api = curl_init();
    curl_setopt($mch_api, CURLOPT_URL, 'https://us2.api.mailchimp.com/3.0/lists/'.env('MC_LIST_ID').'/members/'. md5(strtolower($data['email_address'])));
    curl_setopt($mch_api, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Basic '.base64_encode( 'user:'.$params['apikey'])));
    curl_setopt($mch_api, CURLOPT_USERAGENT, 'PHP-MCAPI/2.0');
    curl_setopt($mch_api, CURLOPT_RETURNTRANSFER, true); // return the API response
    curl_setopt($mch_api, CURLOPT_CUSTOMREQUEST, 'PUT'); // method PUT
    curl_setopt($mch_api, CURLOPT_TIMEOUT, 10);
    curl_setopt($mch_api, CURLOPT_POST, true);
    curl_setopt($mch_api, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($mch_api, CURLOPT_POSTFIELDS, json_encode($data) ); 
    $response_body = curl_exec($mch_api);
    curl_close ($mch_api);
    $response_body = json_decode($response_body);
    
    if($response_body->status=='subscribed')
    {
      $response['status'] = true;
      $response['message'] = $this->language[$request->lang]['MSG086'];
      $response['data'] = [];
      return response()->json($response,200);
    }
    else if($response_body->status==400)
    {
      $response['status'] = false;
      $response['message'] = $this->language[$request->lang]['MSG092'];
      $response['data'] = [];
      return response()->json($response,200);
    }
    else
    {
      $response['status'] = false;
      $response['message'] = $this->language[$request->lang]['MSG087'];
      $response['data'] = [];
      return response()->json($response,200);
    }
 }

 public function contactUs(Request $request){
    
    header("X-Node: localhost");

    $name = $request->name;

    $email = $request->email;
    
    $message = $request->message;
    header("X-Node: localhost");
    $headers = "MIME-Version: 1.0" . "\r\n";

    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    
    $headers .= "From: Equiconx <contact@equiconx.com>"."\r\n" ;

    $headers .= "Reply-to: $name <$email>" ;
    
    $subject = "Contact Request from Equiconx.com";
    
    mail('amit002@mailinator.com', $subject, $message, $headers);

    $response['status'] = true;
    $response['message'] = $this->language[$request->lang]['MSG093'];
    $response['data'] = [];
    
    return response()->json($response,200);
   
 }

 function pollAnswer(Request $request){
    $user = JWTAuth::parseToken()->authenticate();
    $user_id = $user->id;

    $deleteQuery = "DELETE FROM post_poll_user_answers ppa WHERE ppa.post_id = $request->post_id and user_id = $user_id";
    $pollData = DB::select(DB::raw($deleteQuery));

    $data = new Polluseranswer();
    $data->user_id = $user_id;
    $data->post_id = $request->post_id;
    $data->options = $request->option;

    $data->save();

    if($data){
        $response['status'] = true;
        $response['message'] = $this->language[$request->lang]['MSG097'];
        $response['data'] = [];
        return response()->json($response,200);
    }else{
        $response['status'] = true;
        $response['message'] = $this->language[$request->lang]['MSG097'];
        $response['data'] = [];
        return response()->json($response,200);
    }
 }

}

