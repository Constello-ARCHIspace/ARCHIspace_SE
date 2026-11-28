<?php
declare(strict_types=1);
// My submissions now lives on the profile page; keep old links and bookmarks working.
$page=is_string($_GET['page']??null)&&preg_match('/^[0-9]{1,10}$/D',$_GET['page'])?'?page='.$_GET['page']:'';
header('Location: profile.php'.$page.'#submissions',true,301);
