-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               5.1.73 - Source distribution
-- Server OS:                    redhat-linux-gnu
-- HeidiSQL Version:             9.3.0.5004
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Dumping database structure for additional_content
CREATE DATABASE IF NOT EXISTS `additional_content` /*!40100 DEFAULT CHARACTER SET latin1 */;
USE `additional_content`;


-- Dumping structure for table additional_content.Additional_Content
CREATE TABLE IF NOT EXISTS `Additional_Content` (
  `Content_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SKU` varchar(100) COLLATE latin1_general_cs NOT NULL,
  `Clean_SKU` varchar(100) COLLATE latin1_general_cs NOT NULL,
  `UPC` varchar(14) COLLATE latin1_general_cs DEFAULT NULL,
  `Manufacturer_ID` varchar(50) COLLATE latin1_general_cs NOT NULL,
  `Manufacturer` varchar(100) COLLATE latin1_general_cs DEFAULT NULL,
  `Name` varchar(255) COLLATE latin1_general_cs NOT NULL,
  `MarketingDescr` text COLLATE latin1_general_cs,
  `TechnicalDescr` text COLLATE latin1_general_cs,
  `MAP` decimal(18,3) DEFAULT NULL,
  `Weight` decimal(18,3) DEFAULT NULL,
  `MSRP` decimal(18,3) DEFAULT NULL,
  `Categories` varchar(2048) COLLATE latin1_general_cs DEFAULT NULL,
  `Length` decimal(18,3) DEFAULT NULL,
  `Width` decimal(18,3) DEFAULT NULL,
  `Height` decimal(18,3) DEFAULT NULL,
  `MainImage` varchar(255) COLLATE latin1_general_cs DEFAULT NULL,
  `Warranty` mediumtext COLLATE latin1_general_cs,
  `Product_ID` varchar(20) COLLATE latin1_general_cs DEFAULT NULL,
  `MD5` varchar(50) COLLATE latin1_general_cs NOT NULL,
  `Content_Source_ID` int(11) NOT NULL,
  `CREATED_DATE` datetime NOT NULL,
  `UPDATED_DATE` datetime DEFAULT NULL,
  PRIMARY KEY (`Content_Source_ID`,`Content_ID`),
  KEY `Product_ID` (`Product_ID`),
  KEY `UPC_Manufacturer_ID` (`UPC`,`Manufacturer_ID`),
  KEY `Clean_SKU_Manufacturer_ID` (`Clean_SKU`,`Manufacturer_ID`),
  KEY `Content_ID` (`Content_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;

-- Data exporting was unselected.


-- Dumping structure for table additional_content.Additional_Content_Fields
CREATE TABLE IF NOT EXISTS `Additional_Content_Fields` (
  `Field_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Field_Name` varchar(100) COLLATE latin1_general_cs DEFAULT NULL,
  PRIMARY KEY (`Field_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;

-- Data exporting was unselected.


-- Dumping structure for table additional_content.Additional_Content_Sources
CREATE TABLE IF NOT EXISTS `Additional_Content_Sources` (
  `Source_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Manufacturer_ID` varchar(50) COLLATE latin1_general_cs DEFAULT NULL,
  `URL` varchar(255) COLLATE latin1_general_cs DEFAULT NULL,
  `Name` varchar(100) COLLATE latin1_general_cs DEFAULT NULL,
  `Type` enum('scrape','feed') COLLATE latin1_general_cs DEFAULT NULL,
  `Status` enum('ok','failed') COLLATE latin1_general_cs DEFAULT NULL,
  `Script` varchar(200) COLLATE latin1_general_cs DEFAULT NULL,
  `Server` int(11) DEFAULT NULL,
  `Frequency` enum('daily','weekly','monthly') COLLATE latin1_general_cs DEFAULT NULL,
  `Last_Successful_Processing` datetime DEFAULT NULL,
  `Last_Successful_Extraction` datetime DEFAULT NULL,
  PRIMARY KEY (`Source_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;

-- Data exporting was unselected.


-- Dumping structure for table additional_content.Additional_Content_Values
CREATE TABLE IF NOT EXISTS `Additional_Content_Values` (
  `Content_ID` int(11) NOT NULL,
  `Content_Source_ID` int(11) NOT NULL,
  `Field_ID` int(11) NOT NULL,
  `Value` varchar(1024) COLLATE latin1_general_cs DEFAULT NULL,
  PRIMARY KEY (`Content_ID`,`Field_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;

-- Data exporting was unselected.
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
