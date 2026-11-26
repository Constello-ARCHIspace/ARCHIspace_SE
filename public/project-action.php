<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';
require dirname(__DIR__).'/app/Projects.php';

$user=requireUser();
$method=$_SERVER['REQUEST_METHOD'];
if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
$saving=$method==='POST';
if($saving)csrfCheck();
$input=$saving?$_POST:$_GET;
$actor=(int)$user['id'];
$fieldError='';
try{
    $id=integerInput($input,'project_id');
    $action=textInput($input,'action');
    if(!in_array($action,['edit','withdraw'],true))throw new InvalidArgumentException('Unknown submission action.');
    $project=$service->project($actor,$id);
    if((int)$project['owner_id']!==$actor)throw new AccessDenied();
    if(!ProjectSubmission::editableStatus($db,$project))throw new DomainException('Review has started. Open the plan in Studio to make a new submission.');
    $version=$saving?integerInput($input,'version'):(int)$project['version'];
    $name=$project['name'];$kind=$project['kind'];$request=$project['owner_request'];
    if($saving){
        $auth->limit('project-action:user:'.$actor,30,3600);
        $submission=new ProjectSubmission($db,getenv('ARCHISPACE_BLUEPRINT_DIR')?:dirname(__DIR__).'/storage/blueprints');
        if($action==='withdraw'){
            $submission->withdraw($actor,$id,$version);
            $_SESSION['portal_notice']='Submission deleted.';
            header('Location: projects.php',true,303);exit;
        }
        $name=textInput($input,'name');$kind=textInput($input,'kind');$request=textInput($input,'request');
        try{
            $submission->update($actor,$id,$version,$name,$kind,$request);
            $_SESSION['portal_notice']='Submission details updated.';
            header('Location: project.php?id='.$id,true,303);exit;
        }catch(InvalidArgumentException $e){http_response_code(422);$fieldError=$e->getMessage();}
    }
}catch(AccessDenied $e){http_response_code(403);$message='This submission is unavailable.';}
catch(StaleChange $e){http_response_code(409);$message=$e->getMessage();}
catch(InvalidArgumentException|DomainException $e){http_response_code(422);$message=$e->getMessage();}
catch(Throwable $e){http_response_code(503);error_log('ARCHIspace project action failed: '.get_class($e));$message='Service unavailable. Please try again.';}

if(isset($message)){
    portalHeader('Submission unavailable',$user);
    echo '<section class="panel submission-form"><h1>Submission unavailable</h1><p>'.esc($message).'</p><a class="button" href="projects.php">My submissions</a></section>';
    portalFooter();exit;
}
$editing=$action==='edit';
portalHeader($editing?'Edit submission':'Delete submission',$user);
?>
<section class="panel submission-form">
    <h1><?= $editing?'Edit submission':'Delete submission?' ?></h1>
    <?php if($editing): ?>
    <p>Edit the details shared with your reviewer. To change the floor plan, <a href="studio.php?submission=<?= $id ?>">open it in Studio</a>.</p>
    <?php else: ?>
    <p>This permanently deletes the submitted snapshot of <strong><?= esc($project['name']) ?></strong>. Any designs you saved separately stay in Studio.</p>
    <?php endif; ?>
    <form method="post" action="project-action.php" class="stack">
        <?php csrfField();hidden('action',$action);hidden('project_id',$id);hidden('version',$version); ?>
        <?php if($editing): ?>
        <?php if($fieldError!==''): ?><p role="alert" class="submission-error"><?= esc($fieldError) ?></p><?php endif; ?>
        <label>Project name<input name="name" value="<?= esc($name) ?>" maxlength="100" required></label>
        <label>Building type<select name="kind"><?php foreach(['House','Commercial','Mixed use'] as $type): ?><option<?= $type===$kind?' selected':'' ?>><?= esc($type) ?></option><?php endforeach; ?></select></label>
        <label>What would you like reviewed?<textarea name="request" rows="4" maxlength="2000"><?= esc($request??'') ?></textarea></label>
        <?php endif; ?>
        <div class="form-actions">
            <a class="button" href="projects.php"><?= $editing?'Cancel':'Keep submission' ?></a>
            <button class="<?= $editing?'primary':'confirm-delete' ?>"><?= $editing?'Save changes':'Delete submission' ?></button>
        </div>
    </form>
</section>
<?php portalFooter(); ?>
