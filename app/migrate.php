<?php
declare(strict_types=1);
function migrateAccounts(PDO $db): void {
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000');
    $db->exec('BEGIN IMMEDIATE');
    try {
        $columns = array_column($db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (in_array('name', $columns, true) && !in_array('first_name', $columns, true)) {
            $db->exec("ALTER TABLE users RENAME COLUMN name TO first_name");
            $db->exec("ALTER TABLE users ADD COLUMN middle_name TEXT");
            $db->exec("ALTER TABLE users ADD COLUMN last_name TEXT NOT NULL DEFAULT ''");
            $accounts = $db->query('SELECT id,first_name FROM users')->fetchAll(PDO::FETCH_ASSOC);
            $update = $db->prepare('UPDATE users SET first_name=?, middle_name=?, last_name=? WHERE id=?');
            foreach ($accounts as $account) {
                $fullName = $account['first_name'];
                $parts = preg_split(mb_check_encoding($fullName, 'UTF-8') ? '/\s+/u' : '/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
                // A single-word legacy name has no known surname.
                $first = array_shift($parts) ?? '';
                $last = array_pop($parts) ?? '';
                $update->execute([$first, $parts ? implode(' ', $parts) : null, $last, $account['id']]);
            }
            $db->exec("ALTER TABLE users ADD COLUMN name TEXT GENERATED ALWAYS AS (trim(first_name || ' ' || coalesce(nullif(middle_name, '') || ' ', '') || last_name)) VIRTUAL");
        }
        $db->exec(file_get_contents(__DIR__ . '/schema.sql'));
        $columns = array_column($db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ([
            'role'=>"TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('user','sub_admin','admin'))",
            'status'=>"TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended'))",
            'session_version'=>'INTEGER NOT NULL DEFAULT 0',
            'phone'=>'TEXT',
            'birthdate'=>'TEXT',
        ] as $name=>$definition) {
            if (!in_array($name, $columns, true)) $db->exec("ALTER TABLE users ADD COLUMN $name $definition");
        }
        $db->exec(file_get_contents(__DIR__ . '/admin-schema.sql'));
        $db->exec(file_get_contents(__DIR__ . '/designs-schema.sql'));
        $db->exec("INSERT OR IGNORE INTO app_migrations(version,applied_at) VALUES(4,strftime('%s','now'))");
        $db->exec("INSERT OR IGNORE INTO app_migrations(version,applied_at) VALUES(5,strftime('%s','now'))");
        $db->exec("INSERT OR IGNORE INTO app_migrations(version,applied_at) VALUES(6,strftime('%s','now'))");
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
