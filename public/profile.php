<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';
require_once dirname(__DIR__).'/app/Projects.php';
$user=requireUser();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfCheck();
    try{
        $auth->updateProfile((int)$user['id'],textInput($_POST,'first_name'),textInput($_POST,'last_name'),textInput($_POST,'phone'),textInput($_POST,'birthdate'),textInput($_POST,'middle_name'));
        $_SESSION['portal_notice']='Profile updated.';header('Location: profile.php',true,303);exit;
    }catch(InvalidArgumentException|PDOException $e){
        http_response_code(422);$error=$e instanceof PDOException?'Your profile could not be saved. Please try again.':$e->getMessage();
    }
}
$q=$db->prepare('SELECT first_name,middle_name,last_name,email,phone,birthdate,created_at FROM users WHERE id=?');$q->execute([$user['id']]);$me=$q->fetch(PDO::FETCH_ASSOC);
// Re-show what the person typed when the save failed.
if($error)foreach(['first_name','middle_name','last_name','phone','birthdate'] as $k)if(is_string($_POST[$k]??null))$me[$k]=$_POST[$k];
$owner=$user['role']==='user';
if($owner){try{$list=$service->projects((int)$user['id'],'','',integerInput($_GET,'page',1),true);}catch(InvalidArgumentException $e){http_response_code(400);exit('Invalid page.');}}
portalHeader('Profile',$user);
?>
<div class="page-heading"><div><p class="eyebrow">YOUR ACCOUNT / PROFILE</p><h1><?= esc($user['name']) ?></h1><p><?= esc($me['email']) ?> · <?= badge($user['role']) ?> · Member since <?= dateLabel($me['created_at']) ?></p></div><a class="button" href="<?= esc(accountHome($user)) ?>">Back to <?= $owner?'dashboard':'workspace' ?></a></div>
<div class="management-grid profile-grid">
<section class="panel" aria-labelledby="profile-title"><h2 id="profile-title">Personal information</h2><p class="hint">Your name appears to reviewers on submissions and feedback.</p>
<?php if($error): ?><div class="notice error" role="alert"><?= esc($error) ?></div><?php endif; ?>
<form method="post" action="profile.php" class="stack"><?php csrfField(); ?>
<div class="two-fields"><label>First name<input name="first_name" maxlength="80" autocomplete="given-name" value="<?= esc((string)$me['first_name']) ?>" required></label><label><span>Middle name <span class="hint">(optional)</span></span><input name="middle_name" maxlength="80" autocomplete="additional-name" value="<?= esc((string)$me['middle_name']) ?>"></label></div>
<label>Last name<input name="last_name" maxlength="80" autocomplete="family-name" value="<?= esc((string)$me['last_name']) ?>" required></label>
<div class="two-fields"><label>Phone<input name="phone" type="tel" maxlength="24" autocomplete="tel" value="<?= esc((string)$me['phone']) ?>" required></label><label>Date of birth<input name="birthdate" type="date" autocomplete="bday" value="<?= esc((string)$me['birthdate']) ?>" required></label></div>
<label>Email<input type="email" value="<?= esc($me['email']) ?>" disabled></label><p class="hint">Email is your sign-in and can't be changed here. To change your password, sign out and use Forgot password.</p>
<div class="form-actions"><button class="primary">Save changes</button></div></form></section>
<?php if($owner): ?>
<section class="panel"><h2>Upload an exported project</h2><p class="hint">Maximum 4 MB. Blueprint images must already be saved to your account. Up to 100 submissions and 64 MB of project JSON per account.</p><form method="post" action="submit-project.php" enctype="multipart/form-data" class="stack"><?php csrfField(); ?><label>Project name<input name="name" maxlength="100" required></label><label>Building type<select name="kind"><option>House</option><option>Commercial</option><option>Mixed use</option></select></label><label>Exported project<input type="file" name="project" accept=".json,application/json" required></label><label>What would you like reviewed?<textarea name="request" rows="4" maxlength="2000"></textarea></label><p class="hint">Submitting shares the snapshot and its blueprint images with Admins and your assigned reviewer.</p><button class="primary">Submit for review</button></form></section>
<?php endif; ?>
</div>
<?php if($owner): ?>
<section class="panel" id="submissions"><h2>My submissions</h2><p class="hint">Each submission is a separate snapshot. Later studio changes do not overwrite it.</p>
<?php if(!$list['rows'])emptyState('No submissions yet','Use Submit for review in the studio, or upload an exported ARCHIspace JSON file.');foreach($list['rows'] as $project): ?>
<article class="submission-row">
    <div class="submission-title"><h3><a href="project.php?id=<?= (int)$project['id'] ?>"><?= esc($project['name']) ?></a></h3><?= badge($project['status']) ?></div>
    <p class="hint"><?= esc($project['kind']) ?> · <?= dateLabel($project['created_at']) ?></p>
    <?php $pid=(int)$project['id'];$pname=esc($project['name']); ?>
    <div class="submission-actions">
        <a class="button" href="project.php?id=<?= $pid ?>" aria-label="View plan and feedback for <?= $pname ?>"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>View plan &amp; feedback</a>
        <?php if(ProjectSubmission::editableStatus($db,$project)): ?>
        <a class="button" href="project-action.php?action=edit&amp;project_id=<?= $pid ?>" aria-label="Edit details of <?= $pname ?>"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M14 6l4 4"/></svg>Edit details</a>
        <a class="button danger" href="project-action.php?action=withdraw&amp;project_id=<?= $pid ?>" aria-label="Delete <?= $pname ?>"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>Delete</a>
        <?php else: ?>
        <span class="hint action-note">Locked for editing while it is with a reviewer.</span>
        <?php endif; ?>
    </div>
</article>
<?php endforeach; ?><div class="pagination"><span><?= $list['total'] ?> submissions · Page <?= $list['page'] ?></span><?php if($list['page']>1): ?><a href="?page=<?= $list['page']-1 ?>#submissions">Previous</a><?php endif;if($list['total']>$list['page']*20): ?><a href="?page=<?= $list['page']+1 ?>#submissions">Next</a><?php endif; ?></div></section>
<?php endif; ?>
<?php portalFooter(); ?>
