<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';$user=requireUser();
try{
    $project=$service->project((int)$user['id'],integerInput($_GET,'id'));
    $asset=integerInput($_GET,'asset');$data=json_decode($project['state_json'],true);
    $bp=$data['state']['blueprints'][$asset]??null;
    if(!is_array($bp)||!is_string($bp['data']??null)||!preg_match('/^blueprint\.php\?id=([a-f0-9]{48})$/D',$bp['data'],$match))throw new InvalidArgumentException('Image unavailable.');
    $q=$db->prepare('SELECT public_id FROM users WHERE id=?');$q->execute([$project['owner_id']]);$owner=$q->fetchColumn();
    if(!$owner)throw new InvalidArgumentException('Image unavailable.');
    $root=getenv('ARCHISPACE_BLUEPRINT_DIR')?:dirname(__DIR__).'/storage/blueprints';$path=$root.'/'.$owner.'/'.$match[1].'.png';
    if(!is_file($path)){http_response_code(404);exit('Image unavailable.');}
}catch(AccessDenied $e){http_response_code(403);exit('Project unavailable.');}catch(InvalidArgumentException $e){http_response_code(400);exit('Invalid image.');}
header('Content-Type: image/png');header('Content-Length: '.filesize($path));readfile($path);
