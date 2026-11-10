<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';$user=requireUser();
try{$project=$service->project((int)$user['id'],integerInput($_GET,'id'));}
catch(AccessDenied $e){http_response_code(403);exit('Project unavailable.');}catch(InvalidArgumentException $e){http_response_code(400);exit('Invalid project.');}
header('Content-Type: application/json');header('Content-Disposition: attachment; filename="archispace-project-'.(int)$project['id'].'.json"');echo $project['state_json'];
