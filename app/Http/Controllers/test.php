<?php
$validation_error = [];
$employees_filter = [];
$userdata = $this->session->userdata();
if(isset($userdata['validation_v']) && $userdata['validation_v']!='')
{
  $validation_error = $userdata['validation_v'];
  
}
if(isset($userdata['employee_v']) && $userdata['employee_v']!='')
{
  $employees_filter = $userdata['employee_v'];
  
}
