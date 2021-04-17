<?php

class EmailTemplate
{

    protected $email;

    public function __construct($email)
    {
        $this->email = $email;
    }

    public function like()
    {

        $sub = "Check your Equiconx account for recent new activities";

        $template_file = "resources/views/template.blade.php";
        $msg = file_get_contents($template_file);
        // $msg = str_replace("__changeWithlink", $verifyLink, $msg);

        $sent = mail($this->email, $sub, $msg);
        if (!$sent) {
            return response()->json(["message" => "Mail couldn't send successfully."], 200);
        }
        return response()->json(["message" => "Mail send successfully."], 200);
    }

    public function comment()
    {
        $sub = "Check your Equiconx account for recent new activities";

        $template_file = "resources/views/template.blade.php";
        $msg = file_get_contents($template_file);
        // $msg = str_replace("__changeWithlink", $verifyLink, $msg);
        $msg = str_replace("liked", "commented", $msg);

        $ch = mail($this->email, $sub, $msg);
        if (!$ch) {
            return response()->json(["message" => "Mail couldn't send successfully."], 200);
        }
        return response()->json(["message" => "Mail send successfully."], 200);
    }

    public function shared()
    {
        $sub = "Check your Equiconx account for recent new activities";

        $template_file = "resources/views/template.blade.php";
        $msg = file_get_contents($template_file);
        // $msg = str_replace("__changeWithlink", $verifyLink, $msg);
        $msg = str_replace("liked", "shared", $msg);

        $ch = mail($this->email, $sub, $msg);
        if (!$ch) {
            return response()->json(["message" => "Mail couldn't send successfully."], 200);
        }
        return response()->json(["message" => "Mail send successfully."], 200);
    }


    public function send_email()
    {
        // $link = md5(uniqid());
        // $verifyLink = env("WEB_URL") . '/verify-email/' . $link;

        //$template_file = "resources/views/htmluitemplate.blade.php";
        //$msg = file_get_contents($template_file);
        // $msg = str_replace("__changeWithlink", $verifyLink, $msg);
        // $msg = str_replace("Reset Password", "Verify", $msg);

        $ch = mail($this->email, $this->sub, $this->msg, $this->headers);
        if (!$ch) {
            return response()->json(["message" => "Mail couldn't send successfully."], 200);
        }
        return response()->json(["message" => "Mail send successfully."], 200);
    }
}
