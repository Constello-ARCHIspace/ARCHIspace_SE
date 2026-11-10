<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';
if(isset($_GET['token'])){
    $token=is_string($_GET['token'])?$_GET['token']:'';
    $_SESSION['invitation_token']=preg_match('/^[a-f0-9]{64}$/D',$token)?$token:'';
    header('Location: invite.php',true,303);exit;
}
if(!isset($_SESSION['user_id'])){
    header('Location: auth.php',true,303);exit;
}
$user=requireUser();$message='Sign in with the verified email address that received the invitation, then accept below. Invitations do not grant Admin or Sub-admin access.';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfCheck();
    try{$auth->limit('invite_accept:user:'.$user['id'],10,900);}catch(DomainException $e){http_response_code(429);header('Retry-After: 900');exit('Too many attempts. Please try again later.');}
    if($service->acceptInvite((int)$user['id'],$_SESSION['invitation_token']??'')){
        unset($_SESSION['invitation_token']);$_SESSION['portal_notice']='Invitation accepted. Welcome to ARCHIspace.';header('Location: projects.php',true,303);exit;
    }
    http_response_code(422);$message='Invitation expired, already accepted, invalid, or addressed to another email. Sign in with the invited email or request a new invitation.';
}
portalHeader('Workspace invitation',$user);echo '<section class="panel narrow"><p class="eyebrow">WORKSPACE INVITATION</p><h1>You’re invited.</h1><p>'.esc($message).'</p><p>Signed in as '.esc($user['email']).'</p><form action="invite.php" method="post">';csrfField();echo '<button class="primary">Accept invitation</button></form></section>';portalFooter();
