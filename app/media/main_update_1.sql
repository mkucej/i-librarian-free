/* Add index for reference_type filtering. */
CREATE INDEX IF NOT EXISTS ix_items_reference_type ON items(reference_type);
