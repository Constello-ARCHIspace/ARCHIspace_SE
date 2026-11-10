<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/portal.php';
$user=requireStaff();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
csrfCheck();
$project=0;
try{
    $action=textInput($_POST,'action');
    $auth->limit('staff-actions:'.$user['id'],80,900);
    if(in_array($action,['user_access','assign','invite'],true)){
        $service->staff((int)$user['id'],true);
        sensitiveAction($user,textInput($_POST,'password'));
    }
    switch($action){
        case 'user_access':
            $service->changeUser((int)$user['id'],integerInput($_POST,'user_id'),textInput($_POST,'role'),textInput($_POST,'status'),integerInput($_POST,'version'));
            $_SESSION['portal_notice']='Account access updated. Existing sessions for that account have been revoked.';break;
        case 'assign':
            $project=integerInput($_POST,'project_id');$reviewer=integerInput($_POST,'reviewer_id');
            $service->assign((int)$user['id'],$project,$reviewer?:null,integerInput($_POST,'version'));
            $_SESSION['portal_notice']='Reviewer assignment updated.';break;
        case 'feedback':
            $project=integerInput($_POST,'project_id');$intent=textInput($_POST,'intent');
            if(!in_array($intent,['draft','publish'],true))throw new InvalidArgumentException('Invalid feedback action.');
            $service->saveFeedback((int)$user['id'],$project,integerInput($_POST,'version'),textInput($_POST,'location'),textInput($_POST,'priority'),textInput($_POST,'body'),$intent==='publish',textInput($_POST,'next_status','awaiting_owner'));
            $_SESSION['portal_notice']=$intent==='publish'?'Feedback published to the owner’s submissions page.':'Private draft saved.';break;
        case 'invite':
            $auth->limit('invites:'.$user['id'],5,3600);
            $service->invite((int)$user['id'],textInput($_POST,'email'),function($email,$token)use($config,$base){sendAppMail($config,$email,'You are invited to ARCHIspace',"You have been invited to create a standard ARCHIspace account.\n\n".$base.'/invite.php?token='.rawurlencode($token)."\n\nThis invitation expires in 48 hours. Register and verify your email before accepting. Staff access is assigned separately by an Admin. Ignore this message if unexpected.");});
            $_SESSION['portal_notice']='Invitation sent. New accounts receive the standard User role.';break;
        default:throw new InvalidArgumentException('Unknown action.');
    }
    header('Location: '.accountHome($user).($project?'?project='.$project:''),true,303);exit;
}catch(AccessDenied $e){http_response_code(403);exit('Access denied.');}
catch(StaleChange $e){http_response_code(409);$message=$e->getMessage();}
catch(InvalidArgumentException|DomainException $e){http_response_code(422);$message=$e->getMessage();}
catch(Throwable $e){http_response_code(503);error_log('ARCHIspace staff action failed.');$message='The action could not be completed. Try again or contact the server administrator.';}
portalHeader('Action not completed',$user);echo '<section class="panel"><h1>Action not completed</h1><p>'.esc($message).'</p><a class="button" href="'.esc(accountHome($user)).'">Return to dashboard</a><p class="hint">If you were writing feedback, use your browser’s Back button to recover the form before reloading.</p></section>';portalFooter();
