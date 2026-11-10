<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';
$user=requireUser();
try{$list=$service->projects((int)$user['id'],'','',integerInput($_GET,'page',1),true);}catch(InvalidArgumentException $e){http_response_code(400);exit('Invalid page.');}
portalHeader('My submissions',$user);
?>
<div class="page-heading"><div><p class="eyebrow">YOUR WORKSPACE / HUMAN REVIEW</p><h1>My submissions.</h1><p>Submit a design snapshot and read feedback from your assigned reviewer.</p></div><a class="button" href="index.php">Back to studio</a></div>
<div class="management-grid"><section class="panel"><h2>Submitted designs</h2><p class="hint">Each submission is a separate snapshot. Later studio changes do not overwrite it.</p>
<?php if(!$list['rows'])emptyState('No submissions yet','Use Submit for review in the studio, or upload an exported ARCHIspace JSON file.');foreach($list['rows'] as $project): ?>
<a class="queue-card" href="project.php?id=<?= (int)$project['id'] ?>"><?= badge($project['status']) ?><h3><?= esc($project['name']) ?></h3><p><?= esc($project['kind']) ?> · <?= dateLabel($project['created_at']) ?></p><small>View plan and feedback →</small></a>
<?php endforeach; ?><div class="pagination"><span><?= $list['total'] ?> submissions · Page <?= $list['page'] ?></span><?php if($list['page']>1): ?><a href="?page=<?= $list['page']-1 ?>">Previous</a><?php endif;if($list['total']>$list['page']*20): ?><a href="?page=<?= $list['page']+1 ?>">Next</a><?php endif; ?></div></section>
<section class="panel"><h2>Upload an exported project</h2><p class="hint">Maximum 4 MB. Blueprint images must already be saved to your account. Up to 100 submissions and 64 MB of project JSON per account.</p><form method="post" action="submit-project.php" enctype="multipart/form-data" class="stack"><?php csrfField(); ?><label>Project name<input name="name" maxlength="100" required></label><label>Building type<select name="kind"><option>House</option><option>Commercial</option><option>Mixed use</option></select></label><label>Exported project<input type="file" name="project" accept=".json,application/json" required></label><label>What would you like reviewed?<textarea name="request" rows="4" maxlength="2000"></textarea></label><p class="hint">Submitting shares the snapshot and its blueprint images with Admins and your assigned reviewer.</p><button class="primary">Submit for review</button></form></section></div>
<?php portalFooter(); ?>
