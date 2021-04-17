<?php

namespace App\Http\Controllers;

use App\Models\Plan as ModelsPlan;
use App\Models\PlanDetail as PlanDetails;
use App\Models\Subscription;
use App\User as AppUser;
use Exception;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use stdClass;
use Stripe\Plan;
use Stripe\TaxRate;
use Tymon\JWTAuth\Facades\JWTAuth;
use DB;

class PlanController extends Controller
{
    private $language;

    private $user_meta;

    function __construct(){

        $this->language = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Translations/index.php";

        $this->user_meta = include $_SERVER['DOCUMENT_ROOT']."/app/Http/Meta/countycode.php";

    }

    public function getPlans(Request $request)
    {
        
        $user  = JWTAuth::parseToken()->authenticate();
        $plans = ModelsPlan::where('user_id','=',$user->id)->get();
        $userPlans = [];
        foreach ($plans as $key => $plan) {
                $userPlans[$key] = [];
                $userPlans[$key]['id'] = $plan->id;
                $userPlans[$key]['user_id'] = $plan->user_id;
                $userPlans[$key]['plan_name'] = $plan->plan_name;
                $userPlans[$key]['description'] = $plan->description;
                $userPlans[$key]['plan_interval'] = $plan->plan_interval;
                $userPlans[$key]['amount'] = $plan->amount;
                $userPlans[$key]['currency'] = $plan->currency;
                $userPlans[$key]['plan_id'] = $plan->plan_id;
                $userPlans[$key]['max_participents'] = $plan->max_participents;
                $details = PlanDetails::where('plan_id','=',$plan->id)->get()->first();
                if($details!='')
                {
                    $userPlans[$key]['plan_image'] =  url('/').'/public/storage/post-images/'.$details->plan_image;
                    $userPlans[$key]['benefits'] =  $details->benefits;
                }
                else
                {
                    $userPlans[$key]['plan_image'] =  '';
                    $userPlans[$key]['benefits'] =  '';
                }
        } 
        return response()->json(["message" => "Data fetched successfully.", "data" => $userPlans], 200);

      
    }
    private function getInterval($interval)
    {
        $response = new stdClass();
        switch ($interval) {
            case 'quarterly':
                $response->interval = "month";
                $response->interval_count = 3;
                break;
            case 'anually':
                $response->interval = "year";
                $response->interval_count = 1;
                break;
            default:
                $response->interval = "month";
                $response->interval_count = 1;
                break;
        }
        return $response;
    }
    public function savePlan(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user->isVerified()) {
            return response()->json(["status" => "error", "message" => "You need to complete your details in your profile section first."], 400);
        }
        // $existingPlan = ModelsPlan::where(["plan_interval" => $request->interval, "user_id" => $user->id])->first();
        // if ($existingPlan) {
        //     return response()->json(["status" => "error", "message" => "You can not have more than one $request->interval plans."], 400);
        // }

        $currency = $request->currency ? $request->currency : "GBP";
        $interval = $this->getInterval($request->inteval);
        $plan = new ModelsPlan();
        try
        {
            $planData = Plan::create([
                "amount" => $request->amount * 100,
                "currency" => $currency,
                "interval" => 'month',
                "interval_count" => '1',
                "nickname" => $request->description,
                "product" => $user->stripe_product_id,
            ], ["stripe_account" => $user->stripe_connected_account_id]);
        }
        catch(Exception $e)
        {
            return response()->json(["status"=>"error","message" => $e->getMessage()], 200);
        }
        
        $plan->plan_name = $request->plan_name;
        $plan->description = $request->description;
        $plan->amount = $request->amount;
        $plan->plan_interval = $request->interval;
        $plan->currency = $currency;
        $plan->plan_id = $planData->id;
        $plan->user_id = $user->id;
        $plan->max_participents = (isset($request->numberOfuser))?$request->numberOfuser:-1;

        $plan->save();
        $file = $request->file('image');
        $front_image = time() . "_" . $file->getClientOriginalName();
        $file->storeAs('public/post-images', $front_image);
        
        $plandetails = new PlanDetails();
        $plandetails->plan_id = $plan->id;
        $plandetails->benefits = $request->benefit;
        $plandetails->status = 1;
        $plandetails->plan_image = $front_image;
        
        $plandetails->save();

        return response()->json(["message" => $this->language[$request->lang]['MSG063']], 200);
    }
    public function updatePlans(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user->isVerified()) {
            return response()->json(["status" => "error", "message" => "You need to complete your details in your profile section first."], 400);
        }
         $currency = $request->currency ? $request->currency : "GBP";
        
        $plan = ModelsPlan::find($request->plan_id); 
        $plan->plan_name = $request->plan_name;
        $plan->description = $request->description;
        $plan->max_participents = (isset($request->numberOfuser))?$request->numberOfuser:-1;
        $plan->save();
        $plandetails = PlanDetails::where('plan_id','=',$request->plan_id)->get()->first();
        $plandetails->benefits = $request->benefit;
        $plandetails->status = 1;
        
        if(isset($_FILES['image']))
        {
            $file = $request->file('image');
            $front_image = time() . "_" . $file->getClientOriginalName();
            $file->storeAs('public/post-images', $front_image);
            $plandetails->plan_image = $front_image;
        }
        $plandetails->save();

        return response()->json(["message" => $this->language[$request->lang]['MSG063']], 200);
    }

    public function getUsersPlans(Request $request,$username)
    {
        $user = AppUser::where(["username" => $username])->first();
        $userId = false;
        if($request->bearerToken())
        {
            $userId = JWTAuth::parseToken()->authenticate()->id;

        }
        
        if (!$user || !$user->isVerified()) {
             return response()->json(["status"=>"error","message" => "Invalid User"], 200);
            }
        else{
            $plans = $user->plans;
            if($userId){
                foreach ($plans as $key => $plan) {
                    $details = PlanDetails::where('plan_id','=',$plan->id)->get()->first();
                        if($details!='')
                        {
                            $plans[$key]['plan_image'] =  url('/').'/public/storage/post-images/'.$details->plan_image;
                            $plans[$key]['benefits'] =  $details->benefits;
                        }
                        else
                        {
                            $userPlans[$key]['plan_image'] =  '';
                            $userPlans[$key]['benefits'] =  '';
                        }
                    $isSubscribed = Subscription::where(["plan_id" => $plan->id, "user_id" => $userId])->first();
                    if ($isSubscribed) {
                        if($isSubscribed->status == 'active'){
                            $plan->is_subscribed = true;    
                        }else{
                            $plan->is_subscribed = false;
                        }
                        
                    } else {
                        $plan->is_subscribed = false;
                    }
                }
                return response()->json(["message" => "Plans fetched successfully.", "data" => $user->plans], 200);
            }
            else
            {
                foreach ($plans as $key => $plan) {
                    $details = PlanDetails::where('plan_id','=',$plan->id)->get()->first();
                        if($details!='')
                        {
                            $plans[$key]['plan_image'] =  url('/').'/public/storage/post-images/'.$details->plan_image;
                            $plans[$key]['benefits'] =  $details->benefits;
                        }
                        else
                        {
                            $userPlans[$key]['plan_image'] =  '';
                            $userPlans[$key]['benefits'] =  '';
                        }
                   
                    $plan->is_subscribed = false;
                   
                }
                 return response()->json(["message" => "Plans fetched successfully.", "data" => $user->plans], 200);
            }
           
        }
    }
    public function tax_rates(){
      $results =  DB::select('select * from countries');
      foreach($results as $i=>$result)
      { if($i==0)
        continue;
          try
        {
            $tax = strtoupper($result->tax);    
            $planData = TaxRate::create([
                'display_name' => $tax,
                'description' => $tax." ".ucfirst($result->country),
                'percentage' => $result->percentage,
                'inclusive' => false,
            ],[

                'stripe_account' => $creator->stripe_connected_account_id,

            ]);
            echo '<pre>';
            print_r($planData); die();
        }
        catch(Exception $e)
        {
            return response()->json(["status"=>"error","message" => $e->getMessage()], 200);
        }
      }
    }
    public function getPlanWiseEarning(Request $request){
         
        if($request->bearerToken())
        {
            $userId = JWTAuth::parseToken()->authenticate()->id;
            
            $results =  DB::select("SELECT up.plan_name,sum(up.amount) as totalAmount FROM `users` u INNER JOIN user_plans up ON up.user_id = u.id INNER JOIN subscription s ON s.plan_id = up.id WHERE u.id = '$userId' GROUP BY up.id ORDER BY totalAmount LIMIT 0,5");
            
            return response()->json(["status"=>"success","data" => $results], 200);
        }
        else
        {
            return response()->json(["status"=>"error","message" =>$this->language[$request->lang]['MSG095']], 200);
        }
    }  
    public function getPlanWiseMonthlyTotal(Request $request){
        if($request->bearerToken())
        {
            $userId = JWTAuth::parseToken()->authenticate()->id;
            
            $mm = date('m');
            $yy = date('Y');
            if(property_exists($request,'mm'))
            {
                $mm = $request->mm;
            }
            if(property_exists($request,'yy'))
            {
                $yy = $request->yy;
            }
            $query = "select s.id as subscription_id, up.plan_name, sum(up.amount) as totalAmount, up.plan_id, DATE_FORMAT(p.created_at, '%Y-%m-%d') as payment_date from users u INNER JOIN user_plans up ON up.user_id = u.id INNER JOIN subscription s ON s.plan_id = up.id INNER JOIN payments p ON p.charge_id = s.subscription_id where u.id = $userId AND p.type = 'income' ";
            $query .= " AND p.created_at between '$yy-$mm-01' AND LAST_DAY('$yy-$mm-01') ";
            $query .= "GROUP BY up.plan_name, payment_date ORDER BY payment_date DESC";
            $results =  DB::select($query);
            return response()->json(["status"=>"success","data" => $results], 200);
        }    
    }

}
