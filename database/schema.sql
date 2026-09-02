CREATE DATABASE IF NOT EXISTS mypharmacypos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mypharmacypos;

CREATE TABLE medicines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  generic_name VARCHAR(160) NULL,
  strength VARCHAR(80) NULL,
  manufacturer VARCHAR(160) NULL,
  barcode VARCHAR(80) UNIQUE NULL,
  unit_name VARCHAR(30) NOT NULL DEFAULT 'tablet',
  units_per_strip INT UNSIGNED NOT NULL DEFAULT 1,
  strips_per_box INT UNSIGNED NOT NULL DEFAULT 1,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  retail_unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
  min_profit_margin_pct DECIMAL(5,2) NOT NULL DEFAULT 10,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_medicine_name(name), INDEX idx_barcode(barcode)
);

CREATE TABLE batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medicine_id INT UNSIGNED NOT NULL,
  batch_no VARCHAR(80) NULL,
  expiry_date DATE NULL,
  units_received INT NOT NULL DEFAULT 0,
  units_remaining INT NOT NULL DEFAULT 0,
  purchase_unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
  INDEX idx_batch_expiry(expiry_date), INDEX idx_batch_medicine(medicine_id)
);

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(50) NULL,
  address VARCHAR(255) NULL,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(50) NULL,
  address VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(40) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','card','credit','other') NOT NULL DEFAULT 'cash',
  paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE sale_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id BIGINT UNSIGNED NOT NULL,
  medicine_id INT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  quantity_units INT NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL
);

CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medicine_id INT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  movement_type ENUM('purchase','sale','return','adjustment') NOT NULL,
  quantity_units INT NOT NULL,
  reference_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL
);

CREATE TABLE purchases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(60) NOT NULL UNIQUE,
  supplier_id INT UNSIGNED NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
  INDEX idx_purchase_supplier(supplier_id), INDEX idx_purchase_date(created_at)
);

CREATE TABLE purchase_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id BIGINT UNSIGNED NOT NULL,
  medicine_id INT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  quantity_units INT UNSIGNED NOT NULL,
  unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT,
  INDEX idx_purchase_item_medicine(medicine_id)
);

CREATE TABLE supplier_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT UNSIGNED NOT NULL,
  purchase_id BIGINT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE SET NULL
);

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','pharmacist','cashier') NOT NULL DEFAULT 'cashier',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  details VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE returns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_type ENUM('sale','purchase') NOT NULL,
  medicine_id INT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  quantity_units INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT,
  INDEX idx_returns_date(created_at),
  INDEX idx_returns_batch(batch_id)
);

INSERT INTO medicines (name,generic_name,strength,manufacturer,barcode,unit_name,units_per_strip,strips_per_box,purchase_price,retail_unit_price,reorder_level,min_profit_margin_pct)
VALUES ('Demo Paracetamol','Paracetamol','500mg','Demo Manufacturer','100000000001','tablet',10,10,400,5,20,10);
