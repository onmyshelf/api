SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE `collection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cover` varchar(255) DEFAULT NULL,
  `owner` int(11) NOT NULL,
  `template` tinyint(4) NOT NULL DEFAULT 0,
  `visibility` int(10) NOT NULL DEFAULT 3,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `collectionLabel` (
  `collectionId` int(11) NOT NULL,
  `lang` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  UNIQUE KEY `collection_lang` (`collectionId`,`lang`),
  KEY `collection` (`collectionId`),
  CONSTRAINT `collectionLabel_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `config` (
  `param` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`param`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `field` (
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
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Field position order',
  PRIMARY KEY (`id`),
  UNIQUE KEY `collection_name` (`collectionId`,`name`),
  CONSTRAINT `field_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `fieldLabel` (
  `fieldId` int(11) NOT NULL,
  `lang` varchar(10) DEFAULT NULL,
  `label` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  UNIQUE KEY `field_lang` (`fieldId`,`lang`),
  KEY `lang` (`lang`),
  KEY `field` (`fieldId`),
  KEY `label` (`label`),
  CONSTRAINT `fieldLabel_ibfk_1` FOREIGN KEY (`fieldId`) REFERENCES `field` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collectionId` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `visibility` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `collection` (`collectionId`),
  CONSTRAINT `item_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `itemField` (
  `collectionId` int(11) NOT NULL,
  `itemId` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL DEFAULT '',
  KEY `item` (`itemId`),
  KEY `collectionId` (`collectionId`),
  CONSTRAINT `itemField_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`),
  CONSTRAINT `itemField_ibfk_2` FOREIGN KEY (`itemId`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `itemInstance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collectionId` int(11) NOT NULL,
  `itemId` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `collectionId` (`collectionId`),
  KEY `itemId` (`itemId`),
  CONSTRAINT `itemInstance_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collection` (`id`),
  CONSTRAINT `itemInstance_ibfk_2` FOREIGN KEY (`itemId`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `itemInstanceField` (
  `itemInstanceId` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` longtext DEFAULT NULL,
  KEY `itemInstanceId` (`itemInstanceId`),
  CONSTRAINT `itemInstanceField_ibfk_1` FOREIGN KEY (`itemInstanceId`) REFERENCES `itemInstance` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL DEFAULT 1,
  `datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `type` varchar(255) NOT NULL,
  `text` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `token` (
  `token` varchar(255) NOT NULL,
  `userId` int(11) NOT NULL,
  `expiration` int(11) NOT NULL,
  `created` int(11) NOT NULL,
  `ipIssuer` varchar(255) NOT NULL,
  PRIMARY KEY (`token`),
  KEY `user` (`userId`),
  CONSTRAINT `token_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email` varchar(255) DEFAULT NULL,
  `resetToken` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;