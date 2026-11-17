<?php
declare(strict_types=1);
/**
 * "My designs": working designs that a user saves on the server from the studio.
 * Every save is a new version; the newest KEEP_VERSIONS versions of each design are kept.
 * Only the owner can list, open, save, rename or delete a design.
 */
final class DesignLibrary {
    public const MAX_DESIGNS = 50;
    public const KEEP_VERSIONS = 20;
    public const MAX_BYTES = 64 * 1024 * 1024;  // per account, current copies plus versions
    private const KINDS = ['House', 'Commercial', 'Mixed use'];

    public function __construct(private PDO $db, private ProjectSubmission $snapshots) {}

    /** The account's designs, newest first, without the design data. */
    public function list(int $actor): array {
        (new AdminService($this->db))->user($actor);
        $q = $this->db->prepare('SELECT d.public_id AS id,d.name,d.kind,d.version,d.created_at,d.updated_at,length(d.state_json) AS bytes,
            (SELECT count(*) FROM design_versions v WHERE v.design_id=d.id) AS versions
            FROM designs d WHERE d.owner_id=? ORDER BY d.updated_at DESC,d.id DESC');
        $q->execute([$actor]);
        return array_map(fn(array $r) => ['id'=>$r['id'],'name'=>$r['name'],'kind'=>$r['kind'],'version'=>(int)$r['version'],
            'versions'=>(int)$r['versions'],'created_at'=>(int)$r['created_at'],'updated_at'=>(int)$r['updated_at'],'bytes'=>(int)$r['bytes']], $q->fetchAll(PDO::FETCH_ASSOC));
    }

    /** One design (latest data, or a kept version when $version is given). */
    public function get(int $actor, string $id, ?int $version = null): array {
        $d = $this->own($actor, $id);
        $state = $d['state_json']; $at = (int)$d['updated_at']; $v = (int)$d['version'];
        if ($version !== null && $version !== $v) {
            $q = $this->db->prepare('SELECT state_json,created_at FROM design_versions WHERE design_id=? AND version=?');
            $q->execute([$d['id'], $version]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new DomainException('That version is no longer kept. Only the newest ' . self::KEEP_VERSIONS . ' versions are stored.');
            $state = $row['state_json']; $at = (int)$row['created_at']; $v = $version;
        }
        return ['id'=>$d['public_id'],'name'=>$d['name'],'kind'=>$d['kind'],'version'=>$v,'latest'=>(int)$d['version'],
            'saved_at'=>$at,'snapshot'=>json_decode($state, true, 512, JSON_THROW_ON_ERROR)];
    }

    /** Kept versions of a design, newest first. */
    public function versions(int $actor, string $id): array {
        $d = $this->own($actor, $id);
        $q = $this->db->prepare('SELECT version,name,created_at,length(state_json) AS bytes FROM design_versions WHERE design_id=? ORDER BY version DESC');
        $q->execute([$d['id']]);
        return array_map(fn(array $r) => ['version'=>(int)$r['version'],'name'=>$r['name'],'saved_at'=>(int)$r['created_at'],'bytes'=>(int)$r['bytes']], $q->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Save a design. Without $id a new design is created (version 1). With $id a new version is added;
     * $baseVersion must match the latest version, so a save from an old tab cannot overwrite newer work.
     */
    public function save(int $actor, ?string $id, string $name, string $kind, string $json, ?int $baseVersion = null): array {
        $name = $this->cleanName($name);
        if (!in_array($kind, self::KINDS, true)) throw new InvalidArgumentException('Invalid building type.');
        $canonical = $this->snapshots->canonicalSnapshot($actor, $json);
        $now = time();
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            (new AdminService($this->db))->user($actor);
            if ($id === null) {
                $count = $this->db->prepare('SELECT count(*) FROM designs WHERE owner_id=?'); $count->execute([$actor]);
                if ((int)$count->fetchColumn() >= self::MAX_DESIGNS) throw new DomainException('You can keep up to ' . self::MAX_DESIGNS . ' designs. Delete one you no longer need, then save again.');
                $this->checkQuota($actor, strlen($canonical) * 2);
                $publicId = bin2hex(random_bytes(16));
                $this->db->prepare('INSERT INTO designs(public_id,owner_id,name,kind,state_json,version,created_at,updated_at) VALUES(?,?,?,?,?,1,?,?)')
                    ->execute([$publicId, $actor, $name, $kind, $canonical, $now, $now]);
                $designId = (int)$this->db->lastInsertId(); $version = 1;
            } else {
                $d = $this->own($actor, $id);
                if ($baseVersion !== null && $baseVersion !== (int)$d['version']) {
                    throw new StaleChange('This design was saved from another tab or device (now version ' . $d['version'] . '). Open it again from My designs, or save your work as a new design.');
                }
                $this->checkQuota($actor, strlen($canonical) * 2 - strlen($d['state_json']));
                $designId = (int)$d['id']; $version = (int)$d['version'] + 1; $publicId = $d['public_id'];
                $this->db->prepare('UPDATE designs SET name=?,kind=?,state_json=?,version=?,updated_at=? WHERE id=?')
                    ->execute([$name, $kind, $canonical, $version, $now, $designId]);
            }
            $this->db->prepare('INSERT INTO design_versions(design_id,version,name,state_json,created_at) VALUES(?,?,?,?,?)')
                ->execute([$designId, $version, $name, $canonical, $now]);
            // Keep only the newest versions.
            $this->db->prepare('DELETE FROM design_versions WHERE design_id=? AND version<=?')->execute([$designId, $version - self::KEEP_VERSIONS]);
            $this->db->exec('COMMIT');
        } catch (Throwable $e) { $this->db->exec('ROLLBACK'); throw $e; }
        return ['id'=>$publicId, 'version'=>$version, 'name'=>$name, 'saved_at'=>$now];
    }

    public function rename(int $actor, string $id, string $name): void {
        $d = $this->own($actor, $id);
        $this->db->prepare('UPDATE designs SET name=?,updated_at=? WHERE id=?')->execute([$this->cleanName($name), time(), $d['id']]);
    }

    public function delete(int $actor, string $id): void {
        $d = $this->own($actor, $id);
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $this->db->prepare('DELETE FROM design_versions WHERE design_id=?')->execute([$d['id']]);
            $this->db->prepare('DELETE FROM designs WHERE id=?')->execute([$d['id']]);
            $this->db->exec('COMMIT');
        } catch (Throwable $e) { $this->db->exec('ROLLBACK'); throw $e; }
    }

    private function own(int $actor, string $id): array {
        (new AdminService($this->db))->user($actor);
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new InvalidArgumentException('Invalid design ID.');
        $q = $this->db->prepare('SELECT id,public_id,name,kind,state_json,version,updated_at FROM designs WHERE public_id=? AND owner_id=?');
        $q->execute([$id, $actor]);
        $d = $q->fetch(PDO::FETCH_ASSOC);
        // Same answer for "missing" and "someone else's", so IDs cannot be probed.
        if (!$d) throw new AccessDenied('Design not found in your account.');
        return $d;
    }

    private function cleanName(string $name): string {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name) < 1 || mb_strlen($name) > 100) throw new InvalidArgumentException('Use a design name of 1 to 100 characters.');
        return $name;
    }

    private function checkQuota(int $actor, int $extra): void {
        $q = $this->db->prepare('SELECT (SELECT COALESCE(sum(length(state_json)),0) FROM designs WHERE owner_id=?)
            + (SELECT COALESCE(sum(length(v.state_json)),0) FROM design_versions v JOIN designs d ON d.id=v.design_id WHERE d.owner_id=?)');
        $q->execute([$actor, $actor]);
        if ((int)$q->fetchColumn() + $extra > self::MAX_BYTES) throw new DomainException('Design storage is full (64 MB). Delete designs you no longer need, then save again.');
    }
}
