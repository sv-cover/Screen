SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Table structure for table `slide`
--

DROP TABLE IF EXISTS `slide`;
CREATE TABLE `slide` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` VARCHAR(2048),
    `is_active` TINYINT NOT NULL DEFAULT '0',
    `type` ENUM('image', 'web') NOT NULL,
    `url` VARCHAR(4096) NOT NULL,
    `background_color` VARCHAR(7) NOT NULL DEFAULT '#ffffff',
    `fit` ENUM('cover','contain'),
    `order` INT NOT NULL,
    `duration` INT NOT NULL DEFAULT 20,
    `frequency` INT NOT NULL DEFAULT 1, # inverted. actual frequency is 1/frequency
    `start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end` DATETIME,
    PRIMARY KEY (`id`),
    CONSTRAINT chk_image CHECK (`type` != 'image' OR `fit` IS NOT NULL),
    CONSTRAINT chk_end CHECK (`end` IS NULL OR `end` > `start`)
);
