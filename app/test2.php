<?php 
$email = "rajthakur3619@gmail.com";
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$sub = "Activate your Equiconx Account";
$msg = "hello";

mail($email, $sub, $msg, $headers);

?>