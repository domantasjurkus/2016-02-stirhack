/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;



# Dump of table users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id`        int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name`      varchar(255) NOT NULL DEFAULT '',
  `surname`   varchar(255) NOT NULL DEFAULT '',
  `studying`  varchar(255) DEFAULT '',
  `level`     varchar(255) DEFAULT '',
  `bio`       varchar(255) DEFAULT '',
  `country`   varchar(60)  DEFAULT '',
  `email`     varchar(255) NOT NULL,
  `password`  varchar(60)  NOT NULL,
  `img_url`   varchar(255) DEFAULT NULL,
  `img_hash`  varchar(32)  DEFAULT NULL,
  `token`     varchar(100) DEFAULT NULL,
  `token_expiration`  timestamp NULL DEFAULT NULL,
  `created_at`        timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `activated`         INT(1) NOT NULL DEFAULT 0,
  `profile_setup`     INT(1) NOT NULL DEFAULT 0,
  `activation_hash`   VARCHAR( 32 ) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;



# Dump of table slots (recurring slots only)
# ------------------------------------------------------------

DROP TABLE IF EXISTS `slots_recurring`;

CREATE TABLE `slots_recurring` (
  `id`            mediumint(8)  unsigned NOT NULL AUTO_INCREMENT,
  `user_id`       int(10)       unsigned NOT NULL,
  `recurring_day` tinyint(1)    unsigned NOT NULL DEFAULT '0',
  `start_time`     smallint(4)   unsigned NOT NULL DEFAULT '0',
  `length`        smallint(4)    unsigned NOT NULL DEFAULT '0',
  `inserted`      timestamp     NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `time` (`user_id`, `recurring_day`, `start_time`, `length`), # ensures each slot from one user is unique
  CONSTRAINT `user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=ascii;


# Dump of table matches
# ------------------------------------------------------------

DROP TABLE IF EXISTS `meets_invited`;

CREATE TABLE `meets_invited` (
  `id`              int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invitor_id`      int(10) unsigned NOT NULL,
  `invitee_id`      int(10) unsigned NOT NULL,
  `created`         timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated`         timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `user_sug_fk` FOREIGN KEY (`invitor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_inv_fk` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


# Dump of table matches
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `meets_matched` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invitor_id` int(10) unsigned NOT NULL,
  `invitee_id` int(10) unsigned NOT NULL,
  `timestamp` int(10) unsigned NOT NULL,
  `length` int(10) unsigned NOT NULL,
  `location` int(10) unsigned NOT NULL,
  `invitor_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `invitee_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `invitor_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `invitee_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user1_id_fk` (`invitor_id`),
  KEY `user2_id_fk` (`invitee_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=139 ;


# Dump of table locations
# ------------------------------------------------------------

DROP TABLE IF EXISTS `locations`;

CREATE TABLE `locations` (
  `id`            int(10)       UNSIGNED NOT NULL AUTO_INCREMENT,
  `location_name` varchar(255)  NOT NULL,
  `type`          varchar(64)   DEFAULT NULL,
  `lat`           float(4)      DEFAULT NULL,
  `lng`           float(4)      DEFAULT NULL,
  `address`       varchar(255)  DEFAULT NULL,
  `rating`        int(1)        UNSIGNED DEFAULT 0,
  `img_url`       varchar(255)  DEFAULT NULL,
  `discounts`     varchar(255),

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `feedback`;

CREATE TABLE `feedback` (
  `id`      int(10)      UNSIGNED NOT NULL AUTO_INCREMENT,
  `author`  int(10)      UNSIGNED NOT NULL,
  `content` varchar(255) DEFAULT NULL,
  `created` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;





















# ----- For later versions -----

# Dump of table feedback
# ------------------------------------------------------------

DROP TABLE IF EXISTS `feedback`;

CREATE TABLE `feedback` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author` int(10) unsigned NOT NULL,
  `recipient` int(10) unsigned NOT NULL,
  `content` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `content_hash` char(32) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_hash` (`content_hash`,`author`,`recipient`),
  KEY `feedback_author_fk` (`author`),
  KEY `feedback_recipient_fk` (`recipient`),
  CONSTRAINT `feedback_author_fk` FOREIGN KEY (`author`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `feedback_recipient_fk` FOREIGN KEY (`recipient`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;





# Dump of table messages
# ------------------------------------------------------------

DROP TABLE IF EXISTS `messages`;

CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meets_id` bigint(20) NOT NULL,
  `author` int(10) unsigned NOT NULL,
  `content` text NOT NULL DEFAULT '',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `content_hash` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_hash` (`content_hash`,`recipient`,`author`),
  KEY `sender_index` (`author`) USING BTREE,
  CONSTRAINT `owner_fk` FOREIGN KEY (`author`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
