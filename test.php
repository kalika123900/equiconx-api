<?php
header("Content-Type: text/html");
header("X-Node: localhost");
$headers = "MIME-Version: 1.0" . "\r\n";

$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    
$headers .= "From: Equiconx <contact@equiconx.com>".PHP_EOL ;
    
$headers .= "Reply-to: <$name> $email".PHP_EOL ;
   
$from = 'contact@equiconx.com';
echo mail('contact@equiconx.com','hello world','<h2>Hello World</h2>', $headers );
die();
echo phpinfo(); die();
$minutes = -330;

$date = strtotime($minutes.' minutes',strtotime('2020-12-29 00:00:00'));

echo date('Y-m-d G:i:s ',$date);

die();
