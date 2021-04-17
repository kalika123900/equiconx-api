<?php

namespace App;

use Exception;
use Laravel\Cashier\Billable;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stripe\Account;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;
    use Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'auth_token', 'username', 'sm_id', 'signup_via', 'user_type', 'two_way_auth', 'stripe_connected_account_id', 'stripe_account_verified', 'stripe_product_id', 'user_preferences'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'secret_token'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    private $defaultSettings = [
        "security" => [
            "showComments" => "true",
            "showFansCount" => "true",
        ],
        "notifications" => [
            "site" => [
                "comment" => "true",
                "like" => "true",
                "subscriber" => "true"
            ]
        ],
        "chats" => [
            "welcomeMessage" => [
                "enabled" => "false",
                "message" => ""
            ],
            "hideMassMessage" => "true"
        ],
        "watermark" => [
            "photos" => false,
            "videos" => false,
            "text" => ""
        ],
        "email" => [
            "companyNews" => false,
            "newsletter"  => false,
            "comments"    => false,
            "likes"       => false,
        ]
    ];
    protected $attributes  = [
        "user_preferences" => '{
                    "security": {
                        "showComments": "true",
                        "showFansCount": "true"
                    },
                    "notifications": {
                        "site": {
                        "comment": "true",
                        "like": "true",
                        "subscriber": "true"
                        }
                    },
                    "chats": {
                        "welcomeMessage": {
                        "enabled":"false",
                        "message": ""
                        },
                        "hideMassMessage": "true"
                    },
                    "watermark": {
                        "photos": false,
                        "videos": false,
                        "text": ""
                    },
                    "email": {
                        "companyNews": false,
                        "newsletter": false,
                        "comments": false,
                        "like":false
                    }
                }'
    ];


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    public function data()
    {
        return $this->hasOne("App\Models\UserData");
    }
    public function meta(){
        
        return $this->hasMany("App\Models\UserMeta");
    }
    public function documents()
    {
        return $this->hasMany("App\Models\UserVerificationDocument");
    }
    public function plans()
    {
        return $this->hasMany("App\Models\Plan");
    }
    public function teams()
    {
        return $this->hasMany("App\Models\CompanyUser");
    }
    public function subscribers()
    {
        return $this->belongsToMany("App\Models\Subscription", "creator_id");
    }
    public function creator()
    {
        return $this->belongsToMany("App\Models\Subscription", "user_id");
    }

    public function isVerified()
    {
        $isVerified = false;
     
        if ($this->stripe_connected_account_id) {
            try {
                $accountDetails = Account::retrieve($this->stripe_connected_account_id);
               
                $isVerified = $accountDetails->individual->verification->status === "verified";
            } catch (Exception $th) {
            }
        }

        return $isVerified;
    }
    public function settings()
    { 
        $this->user_preferences = preg_replace('/[ \t]+/', '', preg_replace('/[\r\n]+/', "", $this->user_preferences));
        try {
            if (!$this->user_preferences) {
              
                throw new Exception("Error Processing Request", 1);
            }
            return json_decode($this->user_preferences);
        } catch (Exception $th) {
            return json_decode(json_encode($this->defaultSettings));
        }
    }
    public function scopeUserexists($query)
    {
        return $query->select(DB::raw('count(username) as newCount'));
    }
}
