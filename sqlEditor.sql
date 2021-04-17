  $posts = Post::join("users", "posts.user_id", "users.id")
            ->join("user_data", "posts.user_id", "user_data.user_id")
            ->leftJoin("post_plans" , "posts.id", "post_plans.post_id")
            // ->select("posts.*, JSON_ARRAYAGG(post_plans.access_level),users.username, user_data.profile_image, user_data.display_name")
            ->select("posts.*", "post_plans.access_level","users.username", "user_data.profile_image", "user_data.display_name")
            ->where("posts.user_id", $id)
            ->orderBy("id", "desc")
            // ->groupBy("id")
            ->get();



select posts.* ,post_plans.access_level, users.username, user_data.profile_image, user_data.display_name 
from posts inner join users on posts.user_id = users.id 
inner join user_data on  posts.user_id = user_data.user_id
left Join post_plans on posts.id = post_plans.post_id

where posts.user_id = 21

order by id desc