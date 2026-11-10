<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';
require dirname(__DIR__).'/app/Projects.php';
$user=requireUser();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Use POST.');}
csrfCheck();
try{
    $auth->limit('submit:user:'.$user['id'],10,3600);
    $json=textInput($_POST,'snapshot');
    if($json===''){
        $f=$_FILES['project']??null;
        if(!is_array($f)||!is_int($f['error']??null)||$f['error']!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Choose an exported project file. Check PHP upload limits if uploading fails.');
        if($f['size']>4*1024*1024)throw new InvalidArgumentException('Project exceeds 4 MB.');
        $json=file_get_contents($f['tmp_name']);
        if($json===false)throw new RuntimeException('Upload unavailable.');
    }
    $submission=new ProjectSubmission($db,getenv('ARCHISPACE_BLUEPRINT_DIR')?:dirname(__DIR__).'/storage/blueprints');
    $id=$submission->submit((int)$user['id'],textInput($_POST,'name'),textInput($_POST,'kind'),textInput($_POST,'request'),$json);
    $_SESSION['portal_notice']='Design submitted. An Admin can now assign a reviewer.';
    header('Location: project.php?id='.$id,true,303);exit;
}catch(AccessDenied $e){http_response_code(403);$message='Account access changed. Sign in again.';}
catch(InvalidArgumentException|DomainException $e){http_response_code(422);$message=$e->getMessage();}
catch(Throwable $e){http_response_code(503);error_log('ARCHIspace submission failed: '.get_class($e));$message='Submission service unavailable. Please try again.';}
portalHeader('Submission not saved',$user);echo '<section class="panel"><h1>Submission not saved.</h1><p>'.esc($message).'</p><p>Your studio design is unchanged. Return to the studio to retry, or select the file again on My submissions.</p><a class="button" href="projects.php">My submissions</a> <a class="button" href="index.php">Design studio</a></section>';portalFooter();
