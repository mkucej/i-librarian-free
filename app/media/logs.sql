PRAGMA page_size = 4096;
PRAGMA user_version = 49000;
PRAGMA secure_delete = OFF;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;

/* PDF page bookmark. */
CREATE TABLE IF NOT EXISTS last_pages(
    item_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    page    INTEGER NOT NULL,
    PRIMARY KEY(item_id, user_id)
);

/* Items opened. */
CREATE TABLE IF NOT EXISTS opens(
    id      INTEGER PRIMARY KEY,
    item_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_opens_item  ON opens(item_id);
CREATE INDEX IF NOT EXISTS ix_opens_user  ON opens(user_id);
CREATE INDEX IF NOT EXISTS ix_opens_month ON opens(substr(created, 1,  7));
CREATE INDEX IF NOT EXISTS ix_opens_day   ON opens(substr(created, 1, 10));

/* PDF pages read. */
CREATE TABLE IF NOT EXISTS pages(
    id      INTEGER PRIMARY KEY,
    item_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    page    INTEGER NOT NULL,
    created TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_pages_item  ON pages(item_id);
CREATE INDEX IF NOT EXISTS ix_pages_user  ON pages(user_id);
CREATE INDEX IF NOT EXISTS ix_pages_month ON pages(substr(created, 1,  7));
CREATE INDEX IF NOT EXISTS ix_pages_day   ON pages(substr(created, 1, 10));

/* PDF downloads. */
CREATE TABLE IF NOT EXISTS downloads(
    id        INTEGER PRIMARY KEY,
    item_id   INTEGER NOT NULL,
    user_id   INTEGER NOT NULL,
    created   TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_downloads_item  ON pages(item_id);
CREATE INDEX IF NOT EXISTS ix_downloads_user  ON pages(user_id);
CREATE INDEX IF NOT EXISTS ix_downloads_month ON downloads(substr(created, 1,  7));
CREATE INDEX IF NOT EXISTS ix_downloads_day   ON downloads(substr(created, 1, 10));

/* Item edits */
CREATE TABLE IF NOT EXISTS edits(
    id      INTEGER PRIMARY KEY,
    item_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    changes TEXT    NOT NULL,
    created TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_edits_item ON edits(item_id);
CREATE INDEX IF NOT EXISTS ix_edits_user ON edits(user_id);

/* Update last page on pages insert. */
CREATE TRIGGER IF NOT EXISTS pages_ai_last_pages
    AFTER INSERT ON pages
BEGIN
    INSERT OR REPLACE
    INTO last_pages (item_id, user_id, page)
    VALUES(new.item_id, new.user_id, new.page);
END;
