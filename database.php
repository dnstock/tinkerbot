<?php
/////////////////////////////////////////
// CONFIGURE DATABASE
///////////////////////////////////////
if($_SERVER['HTTP_HOST'] == 'localhost') {
  $db_host = 'localhost';
  $db_name = 'msn_tinker';
  $db_user = 'root';
  $db_pass = '';
  $db_table_prefix = '';  // prefix for tables in database (if applicable)
} else { // db login for production server
  $db_host = '';
  $db_name = '';
  $db_user = '';
  $db_pass = '';
  $db_table_prefix = '';
}

?>
