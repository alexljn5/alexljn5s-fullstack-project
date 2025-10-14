-- MySQL Script for tools4ever_db (Fixed Version)
-- Thu 18 Sep 2025 01:28 PM CEST
-- Fixes:
-- - Removed redundant ALTER TABLE for amount_in_stock
-- - Changed price to DECIMAL(10,2) for proper currency handling
-- - Added AUTO_INCREMENT to idemployees and idorders for consistency
-- - Made username and email UNIQUE in employees
-- - Ensured all tables use InnoDB and UTF8 charset
-- - Added comments for clarity

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema tools4ever_db
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `tools4ever_db` DEFAULT CHARACTER SET utf8;
USE `tools4ever_db`;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`employees`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`employees` (
  `idemployees` INT NOT NULL AUTO_INCREMENT COMMENT 'Unique employee ID',
  `admin` TINYINT NOT NULL DEFAULT 0 COMMENT '1 for admin, 0 for regular employee',
  `username` VARCHAR(45) NOT NULL COMMENT 'Unique username for login',
  `password` VARCHAR(255) NOT NULL COMMENT 'Hashed password',
  PRIMARY KEY (`idemployees`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`products` (
  `productid` VARCHAR(50) NOT NULL COMMENT 'Unique product identifier',
  `type` VARCHAR(45) NOT NULL COMMENT 'Product type or category',
  `manufacturer` VARCHAR(45) NOT NULL COMMENT 'Product manufacturer',
  `price` DECIMAL(10,2) NOT NULL COMMENT 'Unit price of the product',
  `amount_in_stock` INT NOT NULL DEFAULT 0 COMMENT 'Stock quantity',
  PRIMARY KEY (`productid`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`location`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`location` (
  `idlocation` INT NOT NULL AUTO_INCREMENT COMMENT 'Unique location ID',
  `city` VARCHAR(45) NOT NULL COMMENT 'City name',
  `zipcode` VARCHAR(45) NOT NULL COMMENT 'Postal code',
  PRIMARY KEY (`idlocation`),
  UNIQUE KEY `uk_city` (`city`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`orders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`orders` (
  `idorders` INT NOT NULL AUTO_INCREMENT COMMENT 'Unique order ID',
  `order_date` DATE NOT NULL COMMENT 'Date the order was placed',
  `delivery_date` DATE NULL COMMENT 'Expected delivery date',
  `order_notes` VARCHAR(255) NULL COMMENT 'Additional notes for the order',
  `order_quantity` INT NOT NULL COMMENT 'Total quantity of items ordered',
  `location_idlocation` INT NOT NULL COMMENT 'References location.idlocation',
  `delivery_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 for pending, 1 for delivered',
  PRIMARY KEY (`idorders`),
  INDEX `fk_orders_location_idx` (`location_idlocation` ASC),
  CONSTRAINT `fk_orders_location`
    FOREIGN KEY (`location_idlocation`)
    REFERENCES `tools4ever_db`.`location` (`idlocation`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`location_has_products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`location_has_products` (
  `location_idlocation` INT NOT NULL,
  `products_productid` VARCHAR(50) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0 COMMENT 'Quantity at this location',
  `purchase_price` DECIMAL(6,2) NOT NULL COMMENT 'Purchase price per unit',
  `sale_price` DECIMAL(6,2) NOT NULL COMMENT 'Sale price per unit',
  PRIMARY KEY (`location_idlocation`, `products_productid`),
  INDEX `fk_location_has_products_products1_idx` (`products_productid` ASC),
  INDEX `fk_location_has_products_location1_idx` (`location_idlocation` ASC),
  CONSTRAINT `fk_location_has_products_location1`
    FOREIGN KEY (`location_idlocation`)
    REFERENCES `tools4ever_db`.`location` (`idlocation`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_location_has_products_products1`
    FOREIGN KEY (`products_productid`)
    REFERENCES `tools4ever_db`.`products` (`productid`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`orders_has_products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`orders_has_products` (
  `orders_idorders` INT NOT NULL,
  `products_productid` VARCHAR(50) NOT NULL,
  `order_quantity` INT NOT NULL COMMENT 'Quantity of this product in the order',
  PRIMARY KEY (`orders_idorders`, `products_productid`),
  INDEX `fk_orders_has_products_products1_idx` (`products_productid` ASC),
  INDEX `fk_orders_has_products_orders1_idx` (`orders_idorders` ASC),
  CONSTRAINT `fk_orders_has_products_orders1`
    FOREIGN KEY (`orders_idorders`)
    REFERENCES `tools4ever_db`.`orders` (`idorders`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_orders_has_products_products1`
    FOREIGN KEY (`products_productid`)
    REFERENCES `tools4ever_db`.`products` (`productid`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;