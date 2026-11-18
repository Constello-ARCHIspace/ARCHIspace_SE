<?php
declare(strict_types=1);
final class ProjectSubmission {
    public function __construct(private PDO $db,private string $blueprintRoot) {}
    private function fields(string &$name,string $kind,string &$request): void {
        $name=trim($name);$request=trim($request);
        if(mb_strlen($name)<1||mb_strlen($name)>100||mb_strlen($request)>2000)throw new InvalidArgumentException('Use a project name up to 100 characters and a request up to 2,000 characters.');
        if(!in_array($kind,['House','Commercial','Mixed use'],true))throw new InvalidArgumentException('Invalid building type.');
    }
    public function submit(int $actor,string $name,string $kind,string $request,string $json): int {
        $this->fields($name,$kind,$request);
        $canonical=$this->canonicalSnapshot($actor,$json);
        $service=new AdminService($this->db);
        $this->db->exec('BEGIN IMMEDIATE');
        try{
            $service->user($actor);
            $q=$this->db->prepare('SELECT count(*) AS n,COALESCE(sum(length(state_json)),0) AS bytes FROM projects WHERE owner_id=?');$q->execute([$actor]);$quota=$q->fetch(PDO::FETCH_ASSOC);
            if((int)$quota['n']>=100||(int)$quota['bytes']+strlen($canonical)>64*1024*1024)throw new DomainException('Submission quota reached. Contact your administrator.');
            $this->db->prepare('INSERT INTO projects(public_id,owner_id,name,kind,owner_request,state_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),$actor,$name,$kind,$request,$canonical,time(),time()]);
            $id=(int)$this->db->lastInsertId();$service->audit($actor,'project_submitted','project',$id);
            $this->db->exec('COMMIT');return $id;
        }catch(Throwable $e){$this->db->exec('ROLLBACK');throw $e;}
    }
    /**
     * The owner may change or delete a submission only before review starts: no reviewer assigned and no
     * feedback written (drafts included). After that the snapshot stays as submitted, so the review
     * always matches what the reviewer saw; changes go in as a new submission.
     */
    public static function editableStatus(PDO $db,array $project): bool {
        if($project['status']!=='unassigned'||$project['assigned_to']!==null)return false;
        $q=$db->prepare('SELECT count(*) FROM feedback WHERE project_id=?');$q->execute([(int)$project['id']]);return (int)$q->fetchColumn()===0;
    }
    private function editable(int $actor,int $id,int $expected): array {
        $q=$this->db->prepare('SELECT * FROM projects WHERE id=?');$q->execute([$id]);$p=$q->fetch(PDO::FETCH_ASSOC);
        if(!$p||(int)$p['owner_id']!==$actor)throw new AccessDenied('Project unavailable.');
        if((int)$p['version']!==$expected)throw new StaleChange('This submission changed since you opened it. Open it again and retry.');
        if(!self::editableStatus($this->db,$p))throw new DomainException('A reviewer is already working on this submission, so it can no longer be changed or deleted. Submit your changes as a new submission instead.');
        return $p;
    }
    /** Replace the snapshot, name, type and request of the owner's own submission before review starts. */
    public function update(int $actor,int $id,int $expected,string $name,string $kind,string $request,string $json): void {
        $this->fields($name,$kind,$request);
        $canonical=$this->canonicalSnapshot($actor,$json);
        $service=new AdminService($this->db);
        $this->db->exec('BEGIN IMMEDIATE');
        try{
            $service->user($actor);$p=$this->editable($actor,$id,$expected);
            $q=$this->db->prepare('SELECT COALESCE(sum(length(state_json)),0) FROM projects WHERE owner_id=?');$q->execute([$actor]);
            if((int)$q->fetchColumn()-strlen($p['state_json'])+strlen($canonical)>64*1024*1024)throw new DomainException('Submission quota reached. Contact your administrator.');
            $this->db->prepare('UPDATE projects SET name=?,kind=?,owner_request=?,state_json=?,version=version+1,updated_at=? WHERE id=?')->execute([$name,$kind,$request,$canonical,time(),$id]);
            $service->audit($actor,'project_updated','project',$id);
            $this->db->exec('COMMIT');
        }catch(Throwable $e){$this->db->exec('ROLLBACK');throw $e;}
    }
    /** Delete the owner's own submission before review starts. */
    public function withdraw(int $actor,int $id,int $expected): void {
        $service=new AdminService($this->db);
        $this->db->exec('BEGIN IMMEDIATE');
        try{
            $service->user($actor);$p=$this->editable($actor,$id,$expected);
            $this->db->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
            $service->audit($actor,'project_withdrawn','project',$id,['name'=>$p['name']]);
            $this->db->exec('COMMIT');
        }catch(Throwable $e){$this->db->exec('ROLLBACK');throw $e;}
    }
    /** Validate an exported studio snapshot for this account and return the canonical JSON that is stored. */
    public function canonicalSnapshot(int $actor,string $json): string {
        if(strlen($json)>4*1024*1024)throw new InvalidArgumentException('Project snapshot exceeds 4 MB.');
        try{$data=json_decode($json,true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new InvalidArgumentException('Invalid project JSON.');}
        if(!is_array($data))throw new InvalidArgumentException('Invalid project snapshot.');
        $state=$data['state']??$data;
        if(!is_array($state)||!isset($state['rooms'],$state['site'])||!is_array($state['rooms'])||!array_is_list($state['rooms'])||count($state['rooms'])>500||!is_array($state['site']))throw new InvalidArgumentException('Use an exported ARCHIspace project. Up to 500 rooms are supported.');
        foreach($state['rooms'] as $r){
            if(!is_array($r))throw new InvalidArgumentException('Invalid room.');
            foreach(['x','z','w','d'] as $key){if(!isset($r[$key])||!is_numeric($r[$key])||!is_finite((float)$r[$key])||abs((float)$r[$key])>10000)throw new InvalidArgumentException('Invalid room dimensions.');}
            if((float)$r['w']<=0||(float)$r['d']<=0)throw new InvalidArgumentException('Room dimensions must be positive.');
            if(!is_string($r['name']??'')||mb_strlen($r['name']??'')>100)throw new InvalidArgumentException('Invalid room name.');
            if(isset($r['level'])&&(!is_int($r['level'])||$r['level']<0||$r['level']>29))throw new InvalidArgumentException('Invalid room floor. Up to 30 floors are supported.');
        }
        $floors=$state['floors']??[];
        if(!is_array($floors)||!array_is_list($floors)||count($floors)>30)throw new InvalidArgumentException('Up to 30 floors are supported.');
        $bps=$state['blueprints']??[];
        if(!is_array($bps)||!array_is_list($bps)||count($bps)>20)throw new InvalidArgumentException('At most 20 saved blueprint images are supported.');
        $user=(new AdminService($this->db))->user($actor);
        foreach($bps as $bp){
            if(!is_array($bp)||!is_string($bp['data']??null)||!preg_match('/^blueprint\.php\?id=([a-f0-9]{48})$/D',$bp['data'],$match))throw new InvalidArgumentException('Save blueprint images to your account before submitting. Remote or embedded images are not accepted.');
            if(!is_file($this->blueprintRoot.'/'.$user['public_id'].'/'.$match[1].'.png'))throw new InvalidArgumentException('A blueprint does not belong to this account or is unavailable. Re-upload it, then save.');
        }
        // Persist only the snapshot, never trust a client-supplied owner, role or reviewer.
        return json_encode(['application'=>'ARCHIspace','version'=>'3.0','state'=>$state],JSON_THROW_ON_ERROR);
    }
}
