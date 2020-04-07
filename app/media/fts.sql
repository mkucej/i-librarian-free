PRAGMA page_size = 4096;
PRAGMA user_version = 49000;
/* Secure delete. Make it an option? */
PRAGMA secure_delete = OFF;
/* 3.6.19 FK */
PRAGMA foreign_keys = ON;
/* 3.7.0  WAL */
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;

/* Delete pro fts tables. */
DROP TABLE IF EXISTS fts_authors;
DROP TABLE IF EXISTS fts_editors;
DROP TABLE IF EXISTS fts_items;
DROP TABLE IF EXISTS fts_keywords;
DROP TABLE IF EXISTS fts_primary_titles;
DROP TABLE IF EXISTS fts_secondary_titles;
DROP TABLE IF EXISTS fts_tertiary_titles;
DROP TABLE IF EXISTS fts_item_notes;
DROP TABLE IF EXISTS fts_annotations;

/* Delete pro fts triggers. */
DROP TRIGGER IF EXISTS fts_authors_bd;
DROP TRIGGER IF EXISTS fts_authors_bu;
DROP TRIGGER IF EXISTS fts_authors_au;
DROP TRIGGER IF EXISTS fts_authors_ai;
DROP TRIGGER IF EXISTS fts_editors_bd;
DROP TRIGGER IF EXISTS fts_editors_bu;
DROP TRIGGER IF EXISTS fts_editors_au;
DROP TRIGGER IF EXISTS fts_editors_ai;
DROP TRIGGER IF EXISTS fts_items_bd;
DROP TRIGGER IF EXISTS fts_items_bu;
DROP TRIGGER IF EXISTS fts_items_au;
DROP TRIGGER IF EXISTS fts_items_ai;
DROP TRIGGER IF EXISTS fts_primary_titles_bd;
DROP TRIGGER IF EXISTS fts_primary_titles_bu;
DROP TRIGGER IF EXISTS fts_primary_titles_au;
DROP TRIGGER IF EXISTS fts_primary_titles_ai;
DROP TRIGGER IF EXISTS fts_secondary_titles_bd;
DROP TRIGGER IF EXISTS fts_secondary_titles_bu;
DROP TRIGGER IF EXISTS fts_secondary_titles_au;
DROP TRIGGER IF EXISTS fts_secondary_titles_ai;
DROP TRIGGER IF EXISTS fts_tertiary_titles_bd;
DROP TRIGGER IF EXISTS fts_tertiary_titles_bu;
DROP TRIGGER IF EXISTS fts_tertiary_titles_au;
DROP TRIGGER IF EXISTS fts_tertiary_titles_ai;
DROP TRIGGER IF EXISTS fts_keywords_bd;
DROP TRIGGER IF EXISTS fts_keywords_bu;
DROP TRIGGER IF EXISTS fts_keywords_au;
DROP TRIGGER IF EXISTS fts_keywords_ai;
DROP TRIGGER IF EXISTS fts_item_notes_bd;
DROP TRIGGER IF EXISTS fts_item_notes_bu;
DROP TRIGGER IF EXISTS fts_item_notes_au;
DROP TRIGGER IF EXISTS fts_item_notes_ai;
DROP TRIGGER IF EXISTS fts_annotations_bd;
DROP TRIGGER IF EXISTS fts_annotations_bu;
DROP TRIGGER IF EXISTS fts_annotations_au;
DROP TRIGGER IF EXISTS fts_annotations_ai;

/* Table for full text search in free version. */
CREATE TABLE IF NOT EXISTS ind_items (
    id INTEGER PRIMARY KEY,
    abstract_index TEXT,
    affiliation_index TEXT,
    authors_index TEXT,
    editors_index TEXT,
    keywords_index TEXT,
    primary_title_index TEXT,
    secondary_title_index TEXT,
    tertiary_title_index TEXT,
    title_index TEXT,
    custom1_index TEXT,
    custom2_index TEXT,
    custom3_index TEXT,
    custom4_index TEXT,
    custom5_index TEXT,
    custom6_index TEXT,
    custom7_index TEXT,
    custom8_index TEXT,
    full_text_index TEXT,
    full_text BLOB,
    FOREIGN KEY (id) REFERENCES items(id) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS ind_items_ai
    AFTER INSERT ON items
BEGIN
    INSERT INTO ind_items(
        id,
        abstract_index,
        affiliation_index,
        primary_title_index,
        secondary_title_index,
        tertiary_title_index,
        title_index,
        custom1_index,
        custom2_index,
        custom3_index,
        custom4_index,
        custom5_index,
        custom6_index,
        custom7_index,
        custom8_index
    ) VALUES (
        new.id,
        '     ' || deaccent(new.abstract, 0) || '     ',
        '     ' || deaccent(new.affiliation, 0) || '     ',
        '     ' || (SELECT deaccent(primary_title, 0)
                        FROM primary_titles
                        WHERE id=new.primary_title_id
        ) || '     ',
        '     ' || (SELECT deaccent(secondary_title, 0)
                        FROM secondary_titles
                        WHERE id=new.secondary_title_id
        ) || '     ',
        '     ' || (SELECT deaccent(tertiary_title, 0)
                        FROM tertiary_titles
                        WHERE id=new.tertiary_title_id
        ) || '     ',
        '     ' || deaccent(new.title, 0) || '     ',
        '     ' || deaccent(new.custom1, 0) || '     ',
        '     ' || deaccent(new.custom2, 0) || '     ',
        '     ' || deaccent(new.custom3, 0) || '     ',
        '     ' || deaccent(new.custom4, 0) || '     ',
        '     ' || deaccent(new.custom5, 0) || '     ',
        '     ' || deaccent(new.custom6, 0) || '     ',
        '     ' || deaccent(new.custom7, 0) || '     ',
        '     ' || deaccent(new.custom8, 0) || '     '
    );
END;

CREATE TRIGGER IF NOT EXISTS ind_items_authors_ai
    AFTER INSERT ON items_authors
BEGIN
    UPDATE ind_items
        SET authors_index = '     ' || (
            SELECT deaccent(group_concat(last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END, '     '), 0)
                FROM authors INNER JOIN items_authors ON authors.id = items_authors.author_id
                WHERE items_authors.item_id = new.item_id
        ) || '     '
    WHERE id=new.item_id;
END;

CREATE TRIGGER IF NOT EXISTS ind_items_editors_ai
    AFTER INSERT ON items_editors
BEGIN
    UPDATE ind_items
        SET editors_index = '     ' || (
            SELECT deaccent(group_concat(last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END, '     '), 0)
                FROM editors INNER JOIN items_editors ON editors.id = items_editors.editor_id
                WHERE items_editors.item_id = new.item_id
        ) || '     '
    WHERE id=new.item_id;
END;

CREATE TRIGGER IF NOT EXISTS ind_items_keywords_ai
    AFTER INSERT ON items_keywords
BEGIN
    UPDATE ind_items
        SET keywords_index = '     ' || (
            SELECT deaccent(group_concat(keyword, '     '), 0)
                FROM keywords INNER JOIN items_keywords ON keywords.id=items_keywords.keyword_id
                WHERE items_keywords.item_id=new.item_id
        ) || '     '
        WHERE id=new.item_id;
END;

CREATE TRIGGER IF NOT EXISTS ind_items_authors_ad
    AFTER DELETE ON items_authors
BEGIN
    UPDATE ind_items
        SET authors_index = '     ' || (
            SELECT deaccent(group_concat(last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END, '     '), 0)
                FROM authors INNER JOIN items_authors ON authors.id = items_authors.author_id
                WHERE items_authors.item_id = old.item_id
        ) || '     '
    WHERE id=old.item_id;
END;

CREATE TRIGGER IF NOT EXISTS ind_items_editors_ad
    AFTER DELETE ON items_editors
BEGIN
    UPDATE ind_items
    SET editors_index = '     ' || (
        SELECT deaccent(group_concat(last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END, '     '), 0)
            FROM editors INNER JOIN items_editors ON editors.id = items_editors.editor_id
            WHERE items_editors.item_id = old.item_id
    ) || '     '
    WHERE id=old.item_id;
END;

CREATE TRIGGER IF NOT EXISTS ind_items_keywords_ad
    AFTER DELETE ON items_keywords
BEGIN
    UPDATE ind_items
    SET keywords_index = '     ' || (
        SELECT deaccent(group_concat(keyword, '     '), 0)
            FROM keywords INNER JOIN items_keywords ON keywords.id=items_keywords.keyword_id
            WHERE items_keywords.item_id=old.item_id
    ) || '     '
    WHERE id=old.item_id;
END;

/* FTS authors for filters. */
CREATE TABLE IF NOT EXISTS ind_authors (
    id INTEGER PRIMARY KEY,
    author TEXT NOT NULL,
    FOREIGN KEY (id) REFERENCES authors(id) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS ind_authors_au
    AFTER UPDATE ON authors
BEGIN
    UPDATE ind_authors
        SET author = deaccent(new.last_name || CASE new.first_name WHEN '' THEN '' ELSE ', ' || new.first_name END, 0)
        WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS ind_authors_ai
    AFTER INSERT ON authors
BEGIN
    INSERT INTO ind_authors(
        id,
        author
    ) VALUES (
        new.id,
        deaccent(new.last_name || CASE new.first_name WHEN '' THEN '' ELSE ', ' || new.first_name END, 0)
    );
END;

/* FTS editors for filters. */
CREATE TABLE IF NOT EXISTS ind_editors (
    id INTEGER PRIMARY KEY,
    editor TEXT NOT NULL,
    FOREIGN KEY (id) REFERENCES editors(id) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS ind_editors_au
    AFTER UPDATE ON editors
BEGIN
    UPDATE ind_editors
        SET editor = deaccent(new.last_name || CASE new.first_name WHEN '' THEN '' ELSE ', ' || new.first_name END, 0)
        WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS ind_editors_ai
    AFTER INSERT ON editors
BEGIN
    INSERT INTO ind_editors(
        id,
        editor
    ) VALUES (
        new.id,
        deaccent(new.last_name || CASE new.first_name WHEN '' THEN '' ELSE ', ' || new.first_name END, 0)
    );
END;

/* FTS primary_titles for filters. */
CREATE TABLE IF NOT EXISTS ind_primary_titles (
    id INTEGER PRIMARY KEY,
    primary_title TEXT NOT NULL,
    FOREIGN KEY (id) REFERENCES primary_titles(id) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS ind_primary_titles_au
    AFTER UPDATE ON primary_titles
BEGIN
    UPDATE ind_primary_titles
        SET primary_title = deaccent(new.primary_title, 0)
        WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS ind_primary_titles_ai
    AFTER INSERT ON primary_titles
BEGIN
    INSERT INTO ind_primary_titles(
        id,
        primary_title
    ) VALUES (
        new.id,
        deaccent(new.primary_title, 0)
    );
END;

/* FTS secondary_titles for filters. */
CREATE TABLE IF NOT EXISTS ind_secondary_titles (
    id INTEGER PRIMARY KEY,
    secondary_title TEXT NOT NULL,
    FOREIGN KEY (id) REFERENCES secondary_titles(id) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS ind_secondary_titles_au
    AFTER UPDATE ON secondary_titles
BEGIN
    UPDATE ind_secondary_titles
        SET secondary_title = deaccent(new.secondary_title, 0)
        WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS ind_secondary_titles_ai
    AFTER INSERT ON secondary_titles
BEGIN
    INSERT INTO ind_secondary_titles(
        id,
        secondary_title
    ) VALUES (
        new.id,
        deaccent(new.secondary_title, 0)
    );
END;

/* FTS tertiary_titles for filters. */
CREATE TABLE IF NOT EXISTS ind_tertiary_titles (
    id INTEGER PRIMARY KEY,
    tertiary_title TEXT NOT NULL,
    FOREIGN KEY (id) REFERENCES tertiary_titles(id) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS ind_tertiary_titles_au
    AFTER UPDATE ON tertiary_titles
BEGIN
    UPDATE ind_tertiary_titles
        SET tertiary_title = deaccent(new.tertiary_title, 0)
        WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS ind_tertiary_titles_ai
    AFTER INSERT ON tertiary_titles
BEGIN
    INSERT INTO ind_tertiary_titles(
        id,
        tertiary_title
    ) VALUES (
        new.id,
        deaccent(new.tertiary_title, 0)
    );
END;

/* FTS keywords for filters. */
CREATE TABLE IF NOT EXISTS ind_keywords (
    id INTEGER PRIMARY KEY,
    keyword TEXT NOT NULL,
    FOREIGN KEY (id) REFERENCES keywords(id) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS ind_keywords_au
    AFTER UPDATE ON keywords
BEGIN
    UPDATE ind_keywords
        SET keyword = '     ' || deaccent(new.keyword, 0) || '     '
        WHERE id = new.id;
END;

CREATE TRIGGER IF NOT EXISTS ind_keywords_ai
    AFTER INSERT ON keywords
BEGIN
    INSERT INTO ind_keywords(
        id,
        keyword
    ) VALUES (
        new.id,
        '     ' || deaccent(new.keyword, 0) || '     '
    );
END;

/* Stats */
INSERT INTO stats
    (table_name, total_count)
    VALUES('ind_items', 0);

CREATE TRIGGER IF NOT EXISTS stats_ind_items_ai
    AFTER INSERT ON ind_items
BEGIN
    UPDATE stats
        SET total_count = total_count + 1, changed_time = CURRENT_TIMESTAMP
        WHERE table_name = 'ind_items';
END;

CREATE TRIGGER IF NOT EXISTS stats_ind_items_ad
    AFTER DELETE ON ind_items
BEGIN
    UPDATE stats
        SET total_count = total_count - 1, changed_time = CURRENT_TIMESTAMP
        WHERE table_name = 'ind_items';
END;

CREATE TRIGGER IF NOT EXISTS stats_ind_items_au
    AFTER UPDATE ON ind_items
BEGIN
    UPDATE stats
        SET changed_time = CURRENT_TIMESTAMP
        WHERE table_name = 'ind_items';
END;
