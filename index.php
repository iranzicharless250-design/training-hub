<?php require_once __DIR__.'/includes/auth.php'; startSession();
if(isLoggedIn()){header('Location: '.(isAdmin()?'admin/index.php':'dashboard.php'));}
else{header('Location: home.php');} exit;
