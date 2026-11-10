<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';$user=requireUser();
try{$project=$service->project((int)$user['id'],integerInput($_GET,'id'));$feedback=$service->feedback((int)$user['id'],(int)$project['id']);}
catch(AccessDenied $e){http_response_code(403);exit('Project unavailable.');}catch(InvalidArgumentException $e){http_response_code(400);exit('Invalid project.');}
portalHeader('Submitted project',$user);
echo '<div class="page-heading"><div><p class="eyebrow">PROJECT / SUBMITTED SNAPSHOT</p><h1>'.esc($project['name']).'</h1><p>'.badge($project['status']).' · Reviewer: '.esc($project['reviewer_name']??'Awaiting assignment').'</p></div><a class="button" href="projects.php">My submissions</a></div><div class="stack">';
planPreview($project);feedbackHistory($feedback,(int)$project['owner_id']===(int)$user['id']);
if($user['role']==='admin'||($user['role']==='sub_admin'&&(int)$project['assigned_to']===(int)$user['id']))echo '<a class="button" href="'.accountHome($user).'?project='.(int)$project['id'].'#composer">Open review workspace</a>';
echo '</div>';portalFooter();
