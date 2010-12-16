<?php
////////////////////////////////////////////////////////////////////////////
// set the number of MINUTES before restarting script
$timeout = @intval($_GET['timeout']) > 0 ? intval($_GET['timeout']) : 15;
// (database is configured in tinker_frame.php)
////////////////////////////////////////////////////////////////////////////
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Tinker: The Tinkerbot Launcher (c)</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <script type="text/javascript">
      function restartTinkerbot() {
        var f = document.getElementById('tinkerframe');
        f.src = f.src;
      }
      setTimeout("restartTinkerbot()",<?php echo $timeout*60*1000 ?>);
    </script>
  </head>
  <body style="margin:0;padding:0;">
    <iframe id="tinkerframe" frameborder="0" src ="lib/tinker_loader.php" width="100%" height="100%" scrolling="auto" style="margin:0;padding:0;">
      <p>Your browser does not support iframes.</p>
    </iframe>
  </body>
</html>
