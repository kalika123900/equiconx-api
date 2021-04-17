<h3>{{$msg}}</h3>
<?php
    if(isset($link)) {
?>
    <a href="{{URL::to('/')}}/verify-email/{{$link}}">Click Here</a>
<?php
    }
?>
