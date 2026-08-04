ALTER TABLE medicines ADD COLUMN min_profit_margin_pct DECIMAL(5,2) NOT NULL DEFAULT 10.00 AFTER reorder_level;
