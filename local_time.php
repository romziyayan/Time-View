<?php
  // Optionally set the desired timezone
  date_default_timezone_set('Asia/Jakarta');

  // Get and format the current server time
  $current_time = date('Y,m-1,d,H,i,s');
  echo "var servertimeOBJ = new Date($current_time);var TimeZone = 'WIB';";
?>
