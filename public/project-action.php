<?php
// Owner actions on a submitted project. Updating goes through submit-project.php (it needs a new snapshot).
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';
require dirname(__DIR__).'/app/Projects.php';
$user=requireUser();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Use POST.');}
csrfCheck();
$id=0;
try{
    $id=integerInput($_POST,'project_id');
    if(textInput($_POST,'action')!=='withdraw')throw new InvalidArgumentException('Unknown action.');
    $auth->limit('project-action:user:'.$user['id'],30,3600);
    (new ProjectSubmission($db,getenv('ARCHISPACE_BLUEPRINT_DIR')?:dirname(__DIR__).'/storage/blueprints'))->withdraw((int)$user['id'],$id,integerInput($_POST,'version'));
    $_SESSION['portal_notice']='Submission deleted.';
    header('Location: projects.php',true,303);exit;
}catch(AccessDenied $e){http_response_code(403);$message='This submission is not available.';}
catch(StaleChange $e){http_response_code(409);$message=$e->getMessage();}
catch(InvalidArgumentException|DomainException $e){http_response_code(422);$message=$e->getMessage();}
catch(Throwable $e){http_response_code(503);error_log('ARCHIspace project action failed: '.get_class($e));$message='Service unavailable. Please try again.';}
portalHeader('Submission not changed',$user);echo '<section class="panel"><h1>Submission not changed.</h1><p>'.esc($message).'</p>'.($id?'<a class="button" href="project.php?id='.$id.'">Back to the submission</a> ':'').'<a class="button" href="projects.php">My submissions</a></section>';portalFooter();
