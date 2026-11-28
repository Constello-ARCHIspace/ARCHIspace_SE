<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';$user=requireUser();
try{$project=$service->project((int)$user['id'],integerInput($_GET,'id'));$feedback=$service->feedback((int)$user['id'],(int)$project['id']);}
catch(AccessDenied $e){http_response_code(403);exit('Project unavailable.');}catch(InvalidArgumentException $e){http_response_code(400);exit('Invalid project.');}
portalHeader('Submitted project',$user);
echo '<div class="page-heading"><div><p class="eyebrow">PROJECT / SUBMITTED SNAPSHOT</p><h1>'.esc($project['name']).'</h1><p>'.badge($project['status']).' · Reviewer: '.esc($project['reviewer_name']??'Awaiting assignment').'</p></div><a class="button" href="profile.php#submissions">My submissions</a></div><div class="stack">';
planPreview($project);
if((int)$project['owner_id']===(int)$user['id']){
    require_once dirname(__DIR__).'/app/Projects.php';$editable=ProjectSubmission::editableStatus($db,$project);
    echo '<section class="panel owner-actions"><h2>Change this submission</h2>';
    if($editable){
        $id=(int)$project['id'];
        echo '<p class="hint">You can change this submission until review begins.</p><div class="owner-buttons"><a class="button primary" href="project-action.php?action=edit&amp;project_id='.$id.'">Edit submission</a><a class="button" href="studio.php?submission='.$id.'">Open in studio</a><a class="delete-link" href="project-action.php?action=withdraw&amp;project_id='.$id.'">Delete submission</a></div>';
    }else{
        echo '<p class="hint">A reviewer is working on this submission, so it stays exactly as submitted and cannot be changed or deleted. To change the design, open it in the studio and submit it again as a new submission.</p><div class="owner-buttons"><a class="button primary" href="studio.php?submission='.(int)$project['id'].'">Open in studio</a></div>';
    }
    echo '</section>';
}
feedbackHistory($feedback,(int)$project['owner_id']===(int)$user['id']);
if($user['role']==='admin'||($user['role']==='sub_admin'&&(int)$project['assigned_to']===(int)$user['id']))echo '<a class="button" href="'.accountHome($user).'?project='.(int)$project['id'].'#composer">Open review workspace</a>';
echo '</div>';portalFooter();
