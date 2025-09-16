SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema tools4ever_db
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `tools4ever_db`;
USE `tools4ever_db`;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`employees`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`employees` (
  `idemployees` INT NOT NULL,
  `admin` TINYINT NULL,
  `username` VARCHAR(45) NULL,
  `password` VARCHAR(45) NULL,
  PRIMARY KEY (`idemployees`)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`products` (
  `productid` VARCHAR(50) NOT NULL,
  `type` VARCHAR(45) NULL,
  `manufacturer` VARCHAR(45) NULL,
  `price` VARCHAR(45) NULL,
  `amount_in_stock` INT NULL,
  PRIMARY KEY (`productid`)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`location`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`location` (
  `idlocation` INT NOT NULL,
  `city` VARCHAR(45) NULL,
  `zipcode` VARCHAR(45) NULL,
  PRIMARY KEY (`idlocation`)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`orders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`orders` (
  `idorders` INT NOT NULL,
  `order_date` VARCHAR(45) NULL,
  `delivery_date` VARCHAR(45) NULL,
  `order_notes` VARCHAR(45) NULL,
  `order_quantity` VARCHAR(45) NULL,
  `location_idlocation` INT NOT NULL,
  PRIMARY KEY (`idorders`),
  INDEX `fk_orders_location_idx` (`location_idlocation` ASC),
  CONSTRAINT `fk_orders_location`
    FOREIGN KEY (`location_idlocation`)
    REFERENCES `tools4ever_db`.`location` (`idlocation`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`location_has_products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`location_has_products` (
  `location_idlocation` INT NOT NULL,
  `products_productid` VARCHAR(50) NOT NULL,
  `quantity` INT NULL,
  `purchase_price` DECIMAL(6,2) NULL,
  `sale_price` DECIMAL(6,2) NULL,
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
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `tools4ever_db`.`orders_has_products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools4ever_db`.`orders_has_products` (
  `orders_idorders` INT NOT NULL,
  `products_productid` VARCHAR(50) NOT NULL,
  `order_quantity` VARCHAR(45) NULL,
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
) ENGINE = InnoDB;

-- Add amount_in_stock column to existing products table (if not already present)
ALTER TABLE `tools4ever_db`.`products`
ADD COLUMN `amount_in_stock` INT NULL AFTER `price`;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;