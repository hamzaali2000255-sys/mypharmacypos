# XAMPP installation

1. Install XAMPP with Apache and MySQL.
2. Clone/download this repository into `C:\xampp\htdocs\mypharmacypos`.
3. Start Apache and MySQL in XAMPP.
4. Open phpMyAdmin and import `database/schema.sql`.
5. Check `config/config.php`. Default XAMPP credentials are database user `root` with an empty password.
6. Open `http://localhost/mypharmacypos/`.

## Loose tablets

Inventory is stored in the smallest sellable unit. For a medicine configured as 10 tablets per strip and 10 strips per box:

- 1 box receiving = 100 units
- 1 strip sale = 10 units
- 3 tablet sale = 3 units

The POS charges `quantity × retail_unit_price` and deducts the same number of smallest units from the earliest-expiring available batch.

## Next production hardening

Before using with real pharmacy transactions, add authentication, CSRF protection, role permissions, validated returns, supplier/purchase screens, printer integration, database backups, and a full audit log. These should be treated as production requirements rather than optional UI features.
