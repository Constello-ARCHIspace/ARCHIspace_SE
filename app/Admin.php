<?php
declare(strict_types=1);
final class AccessDenied extends RuntimeException {}
final class StaleChange extends RuntimeException {}
final class AdminService {
    public function __construct(private PDO $db) {}
    public function user(int $id): array {
        $q=$this->db->prepare("SELECT id,public_id,name,email,role,status,verified_at,session_version FROM users WHERE id=? AND status='active' AND verified_at IS NOT NULL");$q->execute([$id]);
        $u=$q->fetch(PDO::FETCH_ASSOC);if(!$u)throw new AccessDenied('Sign in with an active verified account.');return $u;
    }
    public function staff(int $id, bool $adminOnly=false): array {
        $u=$this->user($id);
        if(!in_array($u['role'],$adminOnly?['admin']:['admin','sub_admin'],true))throw new AccessDenied('You do not have access to this action.');return $u;
    }
    private function transaction(Closure $callback): mixed {
        $this->db->exec('BEGIN IMMEDIATE');
        try {$result=$callback();$this->db->exec('COMMIT');return $result;} catch(Throwable $e){$this->db->exec('ROLLBACK');throw $e;}
    }
    public function audit(?int $actor,string $action,string $type,?int $target,array $details=[]): void {
        $this->db->prepare('INSERT INTO audit_log(actor_id,action,target_type,target_id,details,created_at) VALUES(?,?,?,?,?,?)')->execute([$actor,$action,$type,$target,json_encode($details,JSON_THROW_ON_ERROR),time()]);
    }
    public function project(int $actor,int $id,bool $review=false): array {
        $u=$this->user($actor);
        $q=$this->db->prepare('SELECT p.*,u.name AS owner_name,u.email AS owner_email,r.name AS reviewer_name,a.name AS assigner_name FROM projects p JOIN users u ON u.id=p.owner_id LEFT JOIN users r ON r.id=p.assigned_to LEFT JOIN users a ON a.id=p.assigned_by WHERE p.id=?');$q->execute([$id]);$p=$q->fetch(PDO::FETCH_ASSOC);
        // Object authorization applies to direct URLs, downloads and form submissions.
        $allowed=$p && ($u['role']==='admin' || ($u['role']==='sub_admin' && (int)$p['assigned_to']===$actor) || (!$review && (int)$p['owner_id']===$actor));
        if(!$allowed)throw new AccessDenied('Project unavailable or not assigned to you.');
        if($review)$this->staff($actor);
        return $p;
    }
    public function projects(int $actor,string $search='',string $status='',int $page=1,bool $own=false): array {
        $u=$this->user($actor);$where=[];$args=[];
        if($own||$u['role']==='user'){$where[]='p.owner_id=?';$args[]=$actor;}
        elseif($u['role']==='sub_admin'){$where[]='p.assigned_to=?';$args[]=$actor;}
        elseif($u['role']!=='admin')throw new AccessDenied('Access denied.');
        if($search!==''){$where[]='(p.name LIKE ? ESCAPE \'!\' OR u.name LIKE ? ESCAPE \'!\')';$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_substr($search,0,100)).'%';$args[]=$needle;$args[]=$needle;}
        if($status!==''){if(!in_array($status,['unassigned','needs_feedback','in_review','awaiting_owner','completed'],true))throw new InvalidArgumentException('Invalid status.');$where[]='p.status=?';$args[]=$status;}
        $filter=$where?' WHERE '.implode(' AND ',$where):'';
        $q=$this->db->prepare('SELECT count(*) FROM projects p JOIN users u ON u.id=p.owner_id'.$filter);$q->execute($args);$total=(int)$q->fetchColumn();
        $page=max(1,min($page,max(1,(int)ceil($total/20))));$offset=($page-1)*20;
        $feedbackFlag=$own?',EXISTS(SELECT 1 FROM feedback f WHERE f.project_id=p.id) AS has_feedback':'';
        $q=$this->db->prepare('SELECT p.id,p.name,p.kind,p.owner_id,p.status,p.assigned_to,p.created_at,p.version,u.name AS owner_name,r.name AS reviewer_name'.$feedbackFlag.' FROM projects p JOIN users u ON u.id=p.owner_id LEFT JOIN users r ON r.id=p.assigned_to'.$filter.' ORDER BY p.created_at DESC,p.id DESC LIMIT 20 OFFSET '.$offset);$q->execute($args);
        return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page];
    }
    public function users(int $actor,string $search='',int $page=1): array {
        $this->staff($actor,true);$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_substr($search,0,100)).'%';
        $q=$this->db->prepare("SELECT count(*) FROM users WHERE name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!'");$q->execute([$needle,$needle]);$total=(int)$q->fetchColumn();$page=max(1,min($page,max(1,(int)ceil($total/20))));
        $q=$this->db->prepare("SELECT id,name,email,role,status,verified_at,session_version FROM users WHERE name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' ORDER BY id LIMIT 20 OFFSET ".(($page-1)*20));$q->execute([$needle,$needle]);return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page];
    }
    public function reviewers(int $actor): array {
        $this->staff($actor,true);return $this->db->query("SELECT id,name,role FROM users WHERE role IN ('admin','sub_admin') AND status='active' AND verified_at IS NOT NULL ORDER BY name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function changeUser(int $actor,int $target,string $role,string $status,int $expected): void {
        if(!in_array($role,['user','sub_admin','admin'],true)||!in_array($status,['active','suspended'],true))throw new InvalidArgumentException('Invalid role or account status.');
        $this->transaction(function()use($actor,$target,$role,$status,$expected){
            $this->staff($actor,true);
            $q=$this->db->prepare('SELECT * FROM users WHERE id=?');$q->execute([$target]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if(!$old)throw new InvalidArgumentException('User not found.');
            if((int)$old['session_version']!==$expected)throw new StaleChange('This account changed. Reload before editing.');
            if($target===$actor && ($role!=='admin'||$status!=='active'))throw new DomainException('You cannot remove your own Admin access.');
            if($role!=='user' && !$old['verified_at'])throw new DomainException('Verify the account email before granting staff access.');
            if($old['role']==='admin' && $old['status']==='active' && ($role!=='admin'||$status!=='active')){
                if((int)$this->db->query("SELECT count(*) FROM users WHERE role='admin' AND status='active' AND verified_at IS NOT NULL")->fetchColumn()<=1)throw new DomainException('Keep at least one active verified Admin.');
            }
            if($old['role']===$role && $old['status']===$status)return;
            $this->db->prepare('UPDATE users SET role=?,status=?,session_version=session_version+1 WHERE id=?')->execute([$role,$status,$target]);
            if($role==='user'||$status==='suspended'){
                $this->db->prepare("UPDATE projects SET assigned_to=NULL,assigned_by=NULL,assigned_at=NULL,status='unassigned',version=version+1,updated_at=? WHERE assigned_to=?")->execute([time(),$target]);
            }
            $this->audit($actor,'user_access_changed','user',$target,['from_role'=>$old['role'],'to_role'=>$role,'from_status'=>$old['status'],'to_status'=>$status]);
        });
    }
    public function assign(int $actor,int $project,?int $reviewer,int $expected): void {
        $this->transaction(function()use($actor,$project,$reviewer,$expected){
            $this->staff($actor,true);$p=$this->project($actor,$project,true);
            if((int)$p['version']!==$expected)throw new StaleChange('Project changed. Reload before assigning.');
            if($reviewer!==null)$this->staff($reviewer);
            $this->db->prepare('UPDATE projects SET assigned_to=?,assigned_by=?,assigned_at=?,status=?,version=version+1,updated_at=? WHERE id=?')->execute([$reviewer,$actor,time(),$reviewer?'needs_feedback':'unassigned',time(),$project]);
            $this->audit($actor,'reviewer_assigned','project',$project,['from'=>$p['assigned_to'],'to'=>$reviewer]);
        });
    }
    public function saveFeedback(int $actor,int $project,int $expected,string $location,string $priority,string $body,bool $publish,string $nextStatus='awaiting_owner'): void {
        $location=trim($location);$body=trim($body);
        if(mb_strlen($location)<1||mb_strlen($location)>160||mb_strlen($body)<10||mb_strlen($body)>5000)throw new InvalidArgumentException('Enter a location (1–160 characters) and feedback (10–5,000 characters).');
        if(!in_array($priority,['information','needs_attention','urgent'],true)||!in_array($nextStatus,['awaiting_owner','completed'],true))throw new InvalidArgumentException('Invalid review option.');
        $this->transaction(function()use($actor,$project,$expected,$location,$priority,$body,$publish,$nextStatus){
            $p=$this->project($actor,$project,true);
            if((int)$p['version']!==$expected)throw new StaleChange('Project or review changed. Reload before saving.');
            $q=$this->db->prepare("SELECT id FROM feedback WHERE project_id=? AND author_id=? AND status='draft'");$q->execute([$project,$actor]);$id=$q->fetchColumn();
            if($id){$this->db->prepare('UPDATE feedback SET location=?,priority=?,body=?,status=?,updated_at=?,published_at=? WHERE id=?')->execute([$location,$priority,$body,$publish?'published':'draft',time(),$publish?time():null,$id]);}
            else{$this->db->prepare('INSERT INTO feedback(project_id,author_id,location,priority,body,status,created_at,updated_at,published_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$project,$actor,$location,$priority,$body,$publish?'published':'draft',time(),time(),$publish?time():null]);$id=(int)$this->db->lastInsertId();}
            $this->db->prepare('UPDATE projects SET status=?,version=version+1,updated_at=? WHERE id=?')->execute([$publish?$nextStatus:'in_review',time(),$project]);
            $this->audit($actor,$publish?'feedback_published':'feedback_draft_saved','project',$project,['feedback_id'=>(int)$id,'priority'=>$priority]);
        });
    }
    public function feedback(int $actor,int $project): array {
        $this->project($actor,$project);$u=$this->user($actor);
        // Only the draft author can see their draft; owners get published feedback only.
        $q=$this->db->prepare("SELECT f.*,u.name AS author_name,u.role AS author_role FROM feedback f JOIN users u ON u.id=f.author_id WHERE project_id=? AND (f.status='published' OR (f.author_id=? AND CAST(? AS INTEGER)=1)) ORDER BY f.updated_at DESC,f.id DESC");
        $staff=in_array($u['role'],['admin','sub_admin'],true)?1:0;$q->execute([$project,$actor,$staff]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function stats(int $actor): array {
        $u=$this->staff($actor);$where=$u['role']==='admin'?'':' WHERE assigned_to=?';$args=$u['role']==='admin'?[]:[$actor];
        $q=$this->db->prepare("SELECT count(*) AS total,COALESCE(SUM(assigned_to IS NULL),0) AS unassigned,COALESCE(SUM(assigned_to IS NULL AND created_at>=".(time()-7*86400)."),0) AS unassigned_new,COALESCE(SUM(assigned_to IS NOT NULL),0) AS assigned,COALESCE(SUM(status='completed'),0) AS completed FROM projects".$where);$q->execute($args);$s=$q->fetch(PDO::FETCH_ASSOC);
        if($u['role']==='admin')$s['active_users']=(int)$this->db->query("SELECT count(*) FROM users WHERE status='active' AND verified_at IS NOT NULL")->fetchColumn();
        $q=$this->db->prepare("SELECT count(*) FROM feedback f JOIN projects p ON p.id=f.project_id WHERE f.author_id=? AND f.status='draft'".($u['role']==='sub_admin'?' AND p.assigned_to=?':''));$q->execute($u['role']==='sub_admin'?[$actor,$actor]:[$actor]);$s['drafts']=(int)$q->fetchColumn();return $s;
    }
    public function auditRows(int $actor): array {$this->staff($actor,true);return $this->db->query('SELECT a.*,u.name AS actor_name FROM audit_log a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);}
    public function invite(int $actor,string $email,Closure $send): void {
        $email=Auth::email($email);$raw=bin2hex(random_bytes(32));$hash=hash('sha256',$raw);
        $id=$this->transaction(function()use($actor,$email,$hash){$this->staff($actor,true);$this->db->prepare('INSERT INTO invitations(email,token_hash,created_by,expires_at,created_at) VALUES(?,?,?,?,?)')->execute([$email,$hash,$actor,time()+172800,time()]);$id=(int)$this->db->lastInsertId();$this->audit($actor,'invitation_created','invitation',$id);return $id;});
        try{$send($email,$raw);}catch(Throwable $e){$this->db->prepare('DELETE FROM invitations WHERE id=? AND accepted_at IS NULL')->execute([$id]);$this->audit($actor,'invitation_delivery_failed','invitation',$id);throw new DomainException('Email delivery failed. Check SMTP configuration and retry.');}
    }
    public function acceptInvite(int $actor,string $raw): bool {
        $u=$this->user($actor);if(!preg_match('/^[a-f0-9]{64}$/D',$raw))return false;
        $q=$this->db->prepare('UPDATE invitations SET accepted_at=? WHERE token_hash=? AND email=? COLLATE NOCASE AND expires_at>? AND accepted_at IS NULL');$q->execute([time(),hash('sha256',$raw),$u['email'],time()]);return $q->rowCount()===1;
    }
}
