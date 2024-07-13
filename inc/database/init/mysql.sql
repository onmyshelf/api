SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `collection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `owner` int(11) NOT NULL,
  `template` tinyint(4) NOT NULL DEFAULT 0,
  `visibility` int(10) NOT NULL DEFAULT 0,
  `borrowable` int(10) NOT NULL DEFAULT 0,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `collectionLabel` (
  `collectionId` int(11) NOT NULL,
  `lang` varchar(10) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  UNIQUE KEY `collection_lang` (`collectionId`,`lang`),
  KEY `collection` (`collectionId`),
  CONSTRAINT `collectionLabel_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `config` (
  `param` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`param`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collectionId` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `visibility` int(10) NOT NULL DEFAULT 0,
  `borrowable` int(10) NOT NULL DEFAULT 0,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `collection` (`collectionId`),
  CONSTRAINT `item_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `itemProperty` (
  `collectionId` int(11) NOT NULL,
  `itemId` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  KEY `item` (`itemId`),
  KEY `collectionId` (`collectionId`),
  KEY `name` (`name`),
  CONSTRAINT `itemProperty_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`),
  CONSTRAINT `itemProperty_ibfk_2` FOREIGN KEY (`itemId`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `loan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `itemId` int(11) NOT NULL,
  `state` varchar(255) NOT NULL,
  `lent` int(11) DEFAULT NULL,
  `returned` int(11) DEFAULT NULL,
  `borrower` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `itemId` (`itemId`),
  CONSTRAINT `loan_ibfk_1` FOREIGN KEY (`itemId`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL DEFAULT 1,
  `datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `type` varchar(255) NOT NULL,
  `text` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  KEY `type` (`type`),
  KEY `datetime` (`datetime`),
  CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `property` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collectionId` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `suffix` varchar(255) DEFAULT NULL,
  `default` text DEFAULT NULL,
  `authorizedValues` text DEFAULT NULL,
  `visibility` int(10) NOT NULL DEFAULT 0,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `hideLabel` tinyint(1) NOT NULL DEFAULT 0,
  `isId` tinyint(1) NOT NULL DEFAULT 0,
  `isTitle` tinyint(1) NOT NULL DEFAULT 0,
  `isSubTitle` tinyint(1) NOT NULL DEFAULT 0,
  `isCover` tinyint(1) NOT NULL DEFAULT 0,
  `preview` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Show in preview',
  `multiple` tinyint(1) NOT NULL DEFAULT 0,
  `filterable` tinyint(1) NOT NULL DEFAULT 0,
  `searchable` tinyint(1) NOT NULL DEFAULT 0,
  `sortable` tinyint(1) NOT NULL DEFAULT 0,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Field position order',
  `hidden` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `collection_name` (`collectionId`,`name`),
  KEY `name` (`name`),
  CONSTRAINT `property_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `propertyLabel` (
  `propertyId` int(11) NOT NULL,
  `lang` varchar(10) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  UNIQUE KEY `field_lang` (`propertyId`,`lang`),
  KEY `lang` (`lang`),
  KEY `field` (`propertyId`),
  KEY `label` (`label`),
  CONSTRAINT `propertyLabel_ibfk_1` FOREIGN KEY (`propertyId`) REFERENCES `property` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `token` (
  `token` varchar(255) NOT NULL,
  `userId` int(11) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'session',
  `expiration` int(11) NOT NULL,
  `created` int(11) NOT NULL,
  `ipIssuer` varchar(255) NOT NULL,
  PRIMARY KEY (`token`),
  KEY `user` (`userId`),
  KEY `type` (`type`),
  CONSTRAINT `token_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(255) NOT NULL DEFAULT 'user',
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email` varchar(255) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
