# Phase 1 POS

The modern pharmacy POS is available at:

`http://localhost/mypharmacypos/public/pos.php`

## Selling modes

- Box
- Strip
- Smallest unit (tablet by default)

The POS converts each pack into the smallest inventory unit automatically. Example: 10 tablets/strip × 10 strips/box = 100 units/box.

If a box is selected, the POS sends the equivalent smallest-unit quantity to the existing sale transaction. A strip sends `units_per_strip`; a tablet/unit sends 1.

## Windows/XAMPP

1. Pull/download the latest repository into `C:\xampp\htdocs\mypharmacypos`.
2. Start Apache and MySQL.
3. Ensure the `mypharmacypos` database is imported from `database/schema.sql`.
4. Open `http://localhost/mypharmacypos/public/pos.php`.

The inventory receive screen keeps batch number required and expiry date optional.
