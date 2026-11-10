CREATE TABLE IF NOT EXISTS projects (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 public_id TEXT NOT NULL UNIQUE,
 owner_id INTEGER NOT NULL REFERENCES users(id),
 name TEXT NOT NULL,
 kind TEXT NOT NULL,
 owner_request TEXT NOT NULL,
 state_json TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'unassigned' CHECK(status IN ('unassigned','needs_feedback','in_review','awaiting_owner','completed')),
 assigned_to INTEGER REFERENCES users(id),
 assigned_by INTEGER REFERENCES users(id),
 assigned_at INTEGER,
 created_at INTEGER NOT NULL,
 updated_at INTEGER NOT NULL,
 version INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS projects_owner ON projects(owner_id, created_at);
CREATE INDEX IF NOT EXISTS projects_reviewer ON projects(assigned_to, status);
CREATE TABLE IF NOT EXISTS feedback (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 project_id INTEGER NOT NULL REFERENCES projects(id),
 author_id INTEGER NOT NULL REFERENCES users(id),
 location TEXT NOT NULL,
 priority TEXT NOT NULL CHECK(priority IN ('information','needs_attention','urgent')),
 body TEXT NOT NULL,
 status TEXT NOT NULL CHECK(status IN ('draft','published')),
 created_at INTEGER NOT NULL,
 updated_at INTEGER NOT NULL,
 published_at INTEGER
);
CREATE UNIQUE INDEX IF NOT EXISTS feedback_draft ON feedback(project_id,author_id) WHERE status='draft';
CREATE TABLE IF NOT EXISTS audit_log (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 actor_id INTEGER REFERENCES users(id),
 action TEXT NOT NULL,
 target_type TEXT NOT NULL,
 target_id INTEGER,
 details TEXT NOT NULL,
 created_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS invitations (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 email TEXT NOT NULL,
 token_hash TEXT NOT NULL UNIQUE,
 created_by INTEGER NOT NULL REFERENCES users(id),
 expires_at INTEGER NOT NULL,
 accepted_at INTEGER,
 created_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS app_migrations (version INTEGER PRIMARY KEY, applied_at INTEGER NOT NULL);
INSERT OR IGNORE INTO app_migrations(version,applied_at) VALUES(2,strftime('%s','now'));
