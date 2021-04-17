<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers:Access-Control-Allow-Origin, Authorization, Content-Type');

use App\Http\Controllers\PostController;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('artisan-migrate', function () {
    $exitCode = Artisan::call('migrate');
    echo $exitCode;
});

Route::get('/call-artisan', function () {
    $exitCode = Artisan::call('dump-autoload', []);
    echo $exitCode;
});
Route::get('/clear-cache', function () {
    $exitCode = Artisan::call('cache:clear');
    echo $exitCode;
});
Route::get('/clear-cache-view', function () {
    $exitCode = Artisan::call('view:clear');
    echo $exitCode;
});
Route::get('/clear-cache-route', function () {
    $exitCode = Artisan::call('route:cache');
    echo $exitCode;
});
Route::get('/clear-cache-config', function () {
    $exitCode = Artisan::call('config:cache');
    echo $exitCode;
});

Route::post('login', 'UserController@login');
Route::post('register', 'UserController@register');
Route::post('socialregister', 'UserController@socialregister');
Route::post('send-email', 'UserController@send_email');
Route::post('reset-password', 'UserController@reset_password');
Route::post('contact', 'ContactController@contact');


Route::middleware(['jwt-auth'])->group(function () {

/**************Profile related apis**************/
Route::get('/get-all-creator-profile', 'UserController@get_all_creator_profile');
Route::get('/delete-account/{id}', 'UserController@delete_account');
Route::get('/get-public-profile/{uname}', 'UserController@get_public_profile');
Route::get('/get-profile/{uid}', 'UserController@get_profile');
Route::get('/get-personal-details', 'UserController@get_personal_details');
Route::post('/update-personal-details', 'UserController@update_personal_details');
Route::get('/get-last-login/{id}', 'UserController@get_last_login');
Route::post('/change-password', 'UserController@change_password');
Route::post('/update-profile', 'UserController@update_profile');
Route::post('/become-master', 'UserController@become_master');
Route::post('/upload-image', 'UserController@upload_image');
Route::post('/enable-2fauth', 'UserController@enable_twofauth');
Route::post('/disable-2fauth', 'UserController@disable_twofauth');
Route::post('/verify-2fauth', 'UserController@verify_twofauth');
Route::post('/update-user-settings', 'UserController@update_user_settings');
Route::get('/subscribe-creator/{id}', 'UserController@subscribe_creator');
Route::post('/save-stripe-token', 'UserController@save_stripe_token');
Route::get("/bank-details", 'UserController@getBankDetails');
Route::post("/bank-details", 'UserController@saveBankDetails');
Route::delete("/bank-details/{id}", 'UserController@deleteBankDetails');
Route::put("/set-default-account/{id}", 'UserController@saveDefault');
Route::post("/bank-cards", 'UserController@saveCard');
Route::get("/bank-cards", 'UserController@getCards');
Route::delete("/bank-cards/{id}", 'UserController@deleteCard');
Route::post("/subscribe", 'UserController@subscribe');
Route::post("/cancel-subscription", 'UserController@cancelSubscription');
Route::get("/plans", 'PlanController@getPlans');
Route::post("/plans", 'PlanController@savePlan');
Route::get("/user-plans/{userId}", 'PlanController@getUsersPlans');
Route::post("/block", 'UserController@blockUser');
Route::post("/report", 'UserController@reportUser');
Route::get("/unblock", 'UserController@unblockUser');
Route::post("/unblock", 'UserController@unblockUser');
Route::get("/blocklist", 'UserController@blocklist');

// 
    // 

/**********POST RELATED APIS******************/

Route::get('/get-public-profile-posts/{uname}', 'PostController@get_public_profile_posts');
Route::get('/get-posts/{id}', 'PostController@get_posts');
Route::get('/get-profile-posts/{id}', 'PostController@get_profile_posts');
Route::get('/delete-post/{id}', 'PostController@delete_post');
Route::get('/get-single-post/{id}', 'PostController@get_single_post');
Route::get('/get-single-post-details/{id}', 'PostController@get_single_post_details');
Route::get('/delete-post-image/{id}', 'PostController@delete_post_image');
Route::post('/update-post', 'PostController@update_post');
Route::get('/delete-comment/{id}', 'PostController@delete_comment');
Route::post('/add-comment', 'PostController@add_comment');
Route::post('/like-unlike-post', 'PostController@like_unlike_post');
Route::post('/like-unlike-comment', 'PostController@like_unlike_comment');
Route::get('/get-post-likes/{id}', 'PostController@get_post_likes');
Route::get('/get-comment-likes/{id}', 'PostController@get_comment_likes');
Route::post('/pin-to-profile', 'PostController@pin_post');
Route::post('/unpin-from-profile', 'PostController@unpin_post');
Route::get('/get-post-comment/{id}','PostController@get_post_comment');
Route::get('/get-single-posts/{id}','PostController@get_single_posts' );
Route::get('/get-total-photos/{id}', 'UserController@get_total_photos');
Route::get('/get-total-videos/{id}', 'UserController@get_total_videos');
Route::get('/live-stream', 'PostController@validateLiveStream');
Route::post('/update-comment', 'PostController@update_comment');
Route::post('/reply-comment', 'PostController@reply_comment');

/**************Notifications related apis****************/

Route::get('/get-notifications/{id}', 'UserController@get_notifications');
Route::get('/read-notifications/{id}', 'UserController@read_notifications');
Route::get('/unread-notification-count', 'UserController@get_unread_notification_count');
Route::post('/rt-notification', 'UserController@emitSendNotification');

/*************Chat related apis******************/

Route::get('/get-subscribed-creators', 'ChatController@get_subscribed_creators');
Route::get('/get-chats', 'ChatController@get_user_chats');
Route::post('/send-message', 'ChatController@send_message');
Route::post('/mark-read', 'ChatController@mark_message_read');
Route::get('/unread-message-count', 'ChatController@get_unread_message_count');
// 
// verification documents
Route::get("/identity-docs", 'UserVerificationDocumentController@index');
Route::post("/identity-docs", 'UserVerificationDocumentController@store');

// Payment History
Route::post("/received-payment",'UserController@receive_payment');
Route::post("/paid-payment",'UserController@paid_payment');
});
Route::get("/active-membership", 'UserController@active_membership');
Route::post("/become-company", 'UserController@becomeCompany');
Route::post("/search-user", 'UserController@searchUser');
Route::post("/add-team-member", 'UserController@addTeamMember');
Route::post("/join-team", 'UserController@joinTeam');
Route::get("/join-team", 'UserController@joinTeam');
Route::get("/my-teams", 'UserController@myTeams');
Route::post("/delete-team-member", 'UserController@deleteTeamMember');

Route::post("/analytics", 'UserController@analytics');