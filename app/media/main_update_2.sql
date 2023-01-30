/* Items authors many-to-many table with new primary key. */
CREATE TABLE IF NOT EXISTS items_authors_new (
    item_id INTEGER NOT NULL,
    author_id INTEGER NOT NULL,
    position INTEGER NOT NULL, -- a position of the author in an item's list of authors
    PRIMARY KEY (item_id, author_id, position),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE RESTRICT
);

/* Create fallback table, if something goes wrong */
CREATE TABLE IF NOT EXISTS items_authors_backup (
    item_id INTEGER NOT NULL,
    author_id INTEGER NOT NULL,
    position INTEGER NOT NULL
);

/* Copy the old table. */
INSERT INTO items_authors_new SELECT * FROM items_authors;
INSERT INTO items_authors_backup SELECT * FROM items_authors;

/* Drop the old table. */
DROP TABLE items_authors;

/* Rename new table. */
ALTER TABLE items_authors_new RENAME TO items_authors;
