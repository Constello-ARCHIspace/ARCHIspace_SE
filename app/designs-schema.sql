-- My designs: working designs saved on the server, with a version for every save.
CREATE TABLE IF NOT EXISTS designs (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 public_id TEXT NOT NULL UNIQUE,
 owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 name TEXT NOT NULL,
 kind TEXT NOT NULL CHECK(kind IN ('House','Commercial','Mixed use')),
 state_json TEXT NOT NULL,
 version INTEGER NOT NULL DEFAULT 1,
 created_at INTEGER NOT NULL,
 updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS designs_owner ON designs(owner_id, updated_at);
CREATE TABLE IF NOT EXISTS design_versions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 design_id INTEGER NOT NULL REFERENCES designs(id) ON DELETE CASCADE,
 version INTEGER NOT NULL,
 name TEXT NOT NULL,
 state_json TEXT NOT NULL,
 created_at INTEGER NOT NULL,
 UNIQUE(design_id, version)
);
INSERT OR IGNORE INTO app_migrations(version,applied_at) VALUES(3,strftime('%s','now'));
