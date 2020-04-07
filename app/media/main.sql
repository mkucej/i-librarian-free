PRAGMA page_size = 4096;
PRAGMA user_version = 49000;
/* Secure delete. Make it an option? */
PRAGMA secure_delete = OFF;
/* 3.6.19 FK */
PRAGMA foreign_keys = ON;
/* 3.7.0  WAL */
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;

/* Table users holds the main user info. */
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_hash TEXT NOT NULL, -- application must use this hash, not id
    username TEXT NOT NULL,
    password TEXT NOT NULL,
    first_name TEXT,
    last_name TEXT,
    email TEXT,
    permissions TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'A', -- (S)uspended, (A)ctive, (D)eleted
    added_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

/* Unique indexes on id_hash, username, and email to prevent duplicates. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_users_id_hash ON users(id_hash);
CREATE UNIQUE INDEX IF NOT EXISTS ix_users_username ON users(username);
CREATE UNIQUE INDEX IF NOT EXISTS ix_users_email ON users(email);

/* User groups to help with project user management. */
CREATE TABLE IF NOT EXISTS groups (
    id INTEGER PRIMARY KEY,
    group_name TEXT NOT NULL,
    added_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

/* Many-to-many gorups users. */
CREATE TABLE IF NOT EXISTS groups_users (
    group_id INTEGER,
    user_id INTEGER,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* Table sessions holds additional data about user sessions. */
CREATE TABLE IF NOT EXISTS sessions (
    session_id TEXT PRIMARY KEY NOT NULL,
    user_id INTEGER NOT NULL,
    added_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* Table user_settings holds user-specific settings. */
CREATE TABLE IF NOT EXISTS user_settings (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    setting_name TEXT NOT NULL,
    setting_value TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* Table settings holds global settings. */
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY,
    setting_name TEXT NOT NULL,
    setting_value TEXT NOT NULL
);

/* Table items holds main item data. */
CREATE TABLE IF NOT EXISTS items (
     id INTEGER PRIMARY KEY,
     title TEXT NOT NULL,
     primary_title_id INTEGER,
     secondary_title_id INTEGER,
     tertiary_title_id INTEGER,
     publication_date TEXT,
     volume TEXT,
     issue TEXT,
     pages TEXT,
     abstract TEXT,
     affiliation TEXT,
     publisher TEXT,
     place_published TEXT,
     reference_type TEXT,
     bibtex_id TEXT,
     bibtex_type TEXT,
     urls TEXT,
     custom1 TEXT,
     custom2 TEXT,
     custom3 TEXT,
     custom4 TEXT,
     custom5 TEXT,
     custom6 TEXT,
     custom7 TEXT,
     custom8 TEXT,
     private TEXT NOT NULL,
     file_hash TEXT,
     added_by INTEGER,
     changed_by INTEGER,
     added_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
     changed_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
     FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
     FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
     FOREIGN KEY (primary_title_id) REFERENCES primary_titles(id) ON DELETE RESTRICT,
     FOREIGN KEY (secondary_title_id) REFERENCES secondary_titles(id) ON DELETE RESTRICT,
     FOREIGN KEY (tertiary_title_id) REFERENCES tertiary_titles(id) ON DELETE RESTRICT
);

/* Indexes on items data to help with performance and prevent duplicates. */
CREATE INDEX IF NOT EXISTS ix_items_title ON items(title);
CREATE INDEX IF NOT EXISTS ix_items_publication_date ON items(publication_date DESC);
CREATE INDEX IF NOT EXISTS ix_items_file_hash ON items(file_hash);
CREATE INDEX IF NOT EXISTS ix_items_month ON items(substr(added_time, 1,  7));
CREATE INDEX IF NOT EXISTS ix_items_day ON items(substr(added_time, 1, 10));
CREATE INDEX IF NOT EXISTS ix_items_custom1 ON items(custom1);
CREATE INDEX IF NOT EXISTS ix_items_custom2 ON items(custom2);
CREATE INDEX IF NOT EXISTS ix_items_custom3 ON items(custom3);
CREATE INDEX IF NOT EXISTS ix_items_custom4 ON items(custom4);
CREATE INDEX IF NOT EXISTS ix_items_custom5 ON items(custom5);
CREATE INDEX IF NOT EXISTS ix_items_custom6 ON items(custom6);
CREATE INDEX IF NOT EXISTS ix_items_custom7 ON items(custom7);
CREATE INDEX IF NOT EXISTS ix_items_custom8 ON items(custom8);

/* Table primary_titles holds primary titles. */
CREATE TABLE IF NOT EXISTS primary_titles (
    id INTEGER PRIMARY KEY,
    primary_title TEXT
);

/* Unique index on primary_title to prevent duplicates. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_primary_titles_primary_title ON primary_titles(primary_title);

/* Triggers to delete primary titles that are not used in items. */
CREATE TRIGGER IF NOT EXISTS items_ad_primary_titles
    AFTER DELETE ON items
    WHEN 0=(SELECT count(*) FROM items WHERE primary_title_id=OLD.primary_title_id)
    BEGIN
        DELETE FROM primary_titles WHERE primary_titles.id=OLD.primary_title_id;
    END;

CREATE TRIGGER IF NOT EXISTS items_au_primary_titles
    AFTER UPDATE ON items
    WHEN 0=(SELECT count(*) FROM items WHERE primary_title_id=OLD.primary_title_id)
BEGIN
    DELETE FROM primary_titles WHERE primary_titles.id=OLD.primary_title_id;
END;

/* Table secondary_titles holds secondary titles. */
CREATE TABLE IF NOT EXISTS secondary_titles (
    id INTEGER PRIMARY KEY,
    secondary_title TEXT
);

/* Unique index on secondary_title to prevent duplicates. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_secondary_titles_secondary_title ON secondary_titles(secondary_title);

/* Triggers to delete secondary titles that are not used in items. */
CREATE TRIGGER IF NOT EXISTS items_ad_secondary_titles
    AFTER DELETE ON items
    WHEN 0=(SELECT count(*) FROM items WHERE secondary_title_id=OLD.secondary_title_id)
    BEGIN
        DELETE FROM secondary_titles WHERE secondary_titles.id=OLD.secondary_title_id;
    END;

CREATE TRIGGER IF NOT EXISTS items_au_secondary_titles
    AFTER UPDATE ON items
    WHEN 0=(SELECT count(*) FROM items WHERE secondary_title_id=OLD.secondary_title_id)
BEGIN
    DELETE FROM secondary_titles WHERE secondary_titles.id=OLD.secondary_title_id;
END;

/* Table tertiary_titles holds tertiary titles. */
CREATE TABLE IF NOT EXISTS tertiary_titles (
    id INTEGER PRIMARY KEY,
    tertiary_title TEXT
);

/* Unique index on tertiary_title to prevent duplicates. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_tertiary_titles_tertiary_title ON tertiary_titles(tertiary_title);

/* Triggers to delete tertiary titles that are not used in items. */
CREATE TRIGGER IF NOT EXISTS items_ad_tertiary_titles
    AFTER DELETE ON items
    WHEN 0=(SELECT count(*) FROM items WHERE tertiary_title_id=OLD.tertiary_title_id)
    BEGIN
        DELETE FROM tertiary_titles WHERE tertiary_titles.id=OLD.tertiary_title_id;
    END;

CREATE TRIGGER IF NOT EXISTS items_au_tertiary_titles
    AFTER UPDATE ON items
    WHEN 0=(SELECT count(*) FROM items WHERE tertiary_title_id=OLD.tertiary_title_id)
BEGIN
    DELETE FROM tertiary_titles WHERE tertiary_titles.id=OLD.tertiary_title_id;
END;

/* UIDs. */
CREATE TABLE IF NOT EXISTS uids (
    id INTEGER PRIMARY KEY,
    uid_type TEXT NOT NULL,
    uid TEXT NOT NULL,
    item_id INTEGER NOT NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

/* Index to speed up UID searches. */
CREATE INDEX IF NOT EXISTS ix_uids_type_uid ON uids(uid_type, uid);

/* Table authors holds authors' first and last names. */
CREATE TABLE IF NOT EXISTS authors (
    id INTEGER PRIMARY KEY,
    first_name TEXT,
    last_name TEXT NOT NULL
);

/* Unique covering index on authors' first and last name to prevent duplicates. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_authors_author_name ON authors(last_name, first_name);

/* Items authors many-to-many table. */
CREATE TABLE IF NOT EXISTS items_authors (
    item_id INTEGER NOT NULL,
    author_id INTEGER NOT NULL,
    position INTEGER NOT NULL, -- a position of the author in an item's list of authors
    PRIMARY KEY (item_id, author_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE RESTRICT
);

/* Trigger to delete authors that are not used in items. */
CREATE TRIGGER IF NOT EXISTS items_authors_ad
    AFTER DELETE ON items_authors
    WHEN 0=(SELECT count(*) FROM items_authors WHERE author_id=OLD.author_id)
    BEGIN
        DELETE FROM authors WHERE authors.id=OLD.author_id;
    END;

/* Table editors holds editors' first and last names. */
CREATE TABLE IF NOT EXISTS editors (
    id INTEGER PRIMARY KEY,
    first_name TEXT,
    last_name TEXT NOT NULL
);

/* Unique covering index on editors' first and last name to prevent duplicates. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_editors_editor_name ON editors(last_name, first_name);

/* Items editors many-to-many table. */
CREATE TABLE IF NOT EXISTS items_editors (
    item_id INTEGER NOT NULL,
    editor_id INTEGER NOT NULL,
    position INTEGER NOT NULL,
    PRIMARY KEY (item_id, editor_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (editor_id) REFERENCES editors(id) ON DELETE RESTRICT
);

/* Trigger to delete editors that are not used in items. */
CREATE TRIGGER IF NOT EXISTS items_editors_ad
    AFTER DELETE ON items_editors
    WHEN 0=(SELECT count(*) FROM items_editors WHERE editor_id=OLD.editor_id)
    BEGIN
        DELETE FROM editors WHERE editors.id=OLD.editor_id;
    END;

/* Table keywords holds item keywords. */
CREATE TABLE IF NOT EXISTS keywords (
    id INTEGER PRIMARY KEY,
    keyword TEXT
);

/* Unique index on keywords to prevent duplicates. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_keywords_keyword ON keywords(keyword);

/* Items keywords many-to-many table. */
CREATE TABLE IF NOT EXISTS items_keywords (
    item_id INTEGER NOT NULL,
    keyword_id INTEGER NOT NULL,
    PRIMARY KEY (item_id, keyword_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (keyword_id) REFERENCES keywords(id) ON DELETE RESTRICT
);

/* Table tags holds item tags (former categories). */
CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY,
    tag TEXT NOT NULL
);

/* Unique index on tags to prevent duplicates. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_tags_tag ON tags(tag);

/* Items tags many-to-many table. */
CREATE TABLE IF NOT EXISTS items_tags (
    item_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (item_id, tag_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

/* Clipboard. */
CREATE TABLE IF NOT EXISTS clipboard (
    user_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, item_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* Table projects holds main project data. */
CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    project TEXT NOT NULL,
    is_active TEXT NOT NULL DEFAULT 'Y',
    is_restricted TEXT NOT NULL DEFAULT 'N',
    added_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

/* Projects users many-to-many table. */
CREATE TABLE IF NOT EXISTS projects_users (
    project_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    PRIMARY KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* Projects items many-to-many table. */
CREATE TABLE IF NOT EXISTS projects_items (
    project_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    added_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, item_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

/* Table notes holds HTML notes on an item. */
CREATE TABLE IF NOT EXISTS item_notes (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    note TEXT NOT NULL,
    changed_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

/* Unique covering index on user_id and item_id to prevent duplicate notes. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_item_notes_user_id_item_id ON item_notes(user_id, item_id);

/* Table notes holds HTML notes on a project. */
CREATE TABLE IF NOT EXISTS project_notes (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    note TEXT NOT NULL,
    changed_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

/* Unique covering index on user_id and project_id to prevent duplicate notes. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_project_notes_user_id_project_id ON project_notes(user_id, project_id);

/* Table searches holds search parameters in JSON format. */
CREATE TABLE IF NOT EXISTS searches (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    search_type TEXT NOT NULL,
    search_name TEXT NOT NULL,
    search_url TEXT NOT NULL,
    auto_search TEXT NOT NULL DEFAULT 'N',
    changed_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* Table markers holds PDF marker highlights. */
CREATE TABLE IF NOT EXISTS markers (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    page INTEGER NOT NULL,
    marker_position INTEGER NOT NULL,
    marker_top INTEGER NOT NULL,
    marker_left INTEGER NOT NULL,
    marker_width INTEGER NOT NULL,
    marker_height INTEGER NOT NULL,
    marker_color TEXT NOT NULL,
    marker_text TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

/* Unique covering index on marker user, item, page, top, and left to prevent duplicate highlights. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_markers_user_item_page_top_left ON markers(user_id, item_id, page, marker_top, marker_left);

/* Table annotations holds PDF notes. */
CREATE TABLE IF NOT EXISTS annotations (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    page INTEGER NOT NULL,
    annotation_top TEXT NOT NULL,
    annotation_left TEXT NOT NULL,
    annotation TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

/* Unique covering index on annotation user, item, page, top, and left to prevent duplicate notes. */
CREATE UNIQUE INDEX IF NOT EXISTS ix_annotations_user_item_page_top_left ON annotations(user_id, item_id, page, annotation_top, annotation_left);

/* Table item_discussions holds item discussion messages. */
CREATE TABLE IF NOT EXISTS item_discussions (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    added_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

/* Table project_discussions holds project discussion messages. */
CREATE TABLE IF NOT EXISTS project_discussions (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    added_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

/* Stats */
CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY,
    table_name TEXT NOT NULL,
    total_count INTEGER,
    changed_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO stats
    (table_name, total_count)
    VALUES('items', 0);

CREATE TRIGGER IF NOT EXISTS stats_items_ai
    AFTER INSERT ON items
    BEGIN
        UPDATE stats
            SET total_count = total_count + 1, changed_time = CURRENT_TIMESTAMP
            WHERE table_name = 'items';
    END;

CREATE TRIGGER IF NOT EXISTS stats_items_ad
    AFTER DELETE ON items
    BEGIN
        UPDATE stats
            SET total_count = total_count - 1, changed_time = CURRENT_TIMESTAMP
            WHERE table_name = 'items';
    END;

CREATE TRIGGER IF NOT EXISTS stats_items_au
    AFTER UPDATE ON items
BEGIN
    UPDATE stats
        SET changed_time = CURRENT_TIMESTAMP
        WHERE table_name = 'items';
END;
