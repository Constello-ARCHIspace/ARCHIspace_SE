PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 public_id TEXT NOT NULL UNIQUE,
 first_name TEXT NOT NULL,
 middle_name TEXT,
 last_name TEXT NOT NULL,
 name TEXT GENERATED ALWAYS AS (trim(first_name || ' ' || coalesce(nullif(middle_name, '') || ' ', '') || last_name)) VIRTUAL,
 email TEXT NOT NULL UNIQUE COLLATE NOCASE,
 password_hash TEXT NOT NULL,
 role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('user','sub_admin','admin')),
 status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended')),
 session_version INTEGER NOT NULL DEFAULT 0,
 verified_at INTEGER,
 created_at INTEGER NOT NULL
);
CREATE TRIGGER IF NOT EXISTS users_require_names_on_insert
BEFORE INSERT ON users
WHEN trim(NEW.first_name, char(9,10,11,12,13,32,160)) = '' OR trim(NEW.last_name, char(9,10,11,12,13,32,160)) = ''
BEGIN
 SELECT RAISE(ABORT, 'First and last names are required.');
END;
CREATE TRIGGER IF NOT EXISTS users_require_names_on_update
BEFORE UPDATE OF first_name, last_name ON users
WHEN trim(NEW.first_name, char(9,10,11,12,13,32,160)) = '' OR trim(NEW.last_name, char(9,10,11,12,13,32,160)) = ''
BEGIN
 SELECT RAISE(ABORT, 'First and last names are required.');
END;
CREATE TABLE IF NOT EXISTS verification_tokens (
 token_hash TEXT PRIMARY KEY,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 expires_at INTEGER NOT NULL,
 created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS verification_user ON verification_tokens(user_id);
CREATE TABLE IF NOT EXISTS rate_limits (
 bucket TEXT PRIMARY KEY,
 count INTEGER NOT NULL,
 expires_at INTEGER NOT NULL
);
