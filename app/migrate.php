<?php
declare(strict_types=1);
function migrateAccounts(PDO $db): void {
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000');
    $db->exec('BEGIN IMMEDIATE');
    try {
        $db->exec(file_get_contents(__DIR__ . '/schema.sql'));
        $columns = array_column($db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ([
            'role'=>"TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('user','sub_admin','admin'))",
            'status'=>"TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended'))",
            'session_version'=>'INTEGER NOT NULL DEFAULT 0',
        ] as $name=>$definition) {
            if (!in_array($name, $columns, true)) $db->exec("ALTER TABLE users ADD COLUMN $name $definition");
        }
        $db->exec(file_get_contents(__DIR__ . '/admin-schema.sql'));
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
