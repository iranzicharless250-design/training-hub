<?php
require_once __DIR__.'/includes/auth.php'; requireLogin();
$lid=(int)($_GET['lesson_id']??0);
if($lid){ensureProgress($_SESSION['user_id'],$lid);getDB()->prepare("UPDATE student_progress SET screenshot_sent=1 WHERE user_id=? AND lesson_id=?")->execute([$_SESSION['user_id'],$lid]);echo json_encode(['ok'=>true]);}
else echo json_encode(['error'=>'invalid']);
