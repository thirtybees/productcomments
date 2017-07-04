CREATE TABLE IF NOT EXISTS `PREFIX_product_comment` (
  `id_product_comment` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product`         INT(11) UNSIGNED NOT NULL,
  `id_customer`        INT(11) UNSIGNED NOT NULL,
  `id_guest`           INT(11) UNSIGNED NULL,
  `title`              VARCHAR(64)      NULL,
  `content`            TEXT             NOT NULL,
  `customer_name`      VARCHAR(64)      NULL,
  `grade`              FLOAT UNSIGNED   NOT NULL,
  `validate`           TINYINT(1)       NOT NULL,
  `deleted`            TINYINT(1)       NOT NULL,
  `date_add`           DATETIME         NOT NULL,
  PRIMARY KEY (`id_product_comment`),
  KEY `id_product` (`id_product`),
  KEY `id_customer` (`id_customer`),
  KEY `id_guest` (`id_guest`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_product_comment_criterion` (
  `id_product_comment_criterion`      INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product_comment_criterion_type` TINYINT(1)       NOT NULL,
  `active`                            TINYINT(1)       NOT NULL,
  PRIMARY KEY (`id_product_comment_criterion`)
)
  ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_product_comment_criterion_product` (
  `id_product`                   INT(11) UNSIGNED NOT NULL,
  `id_product_comment_criterion` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_product`, `id_product_comment_criterion`),
  KEY `id_product_comment_criterion` (`id_product_comment_criterion`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_product_comment_criterion_lang` (
  `id_product_comment_criterion` INT(11) UNSIGNED NOT NULL,
  `id_lang`                      INT(11) UNSIGNED NOT NULL,
  `name`                         VARCHAR(64)      NOT NULL,
  PRIMARY KEY (`id_product_comment_criterion`, `id_lang`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_product_comment_criterion_category` (
  `id_product_comment_criterion` INT(11) UNSIGNED NOT NULL,
  `id_category`                  INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_product_comment_criterion`, `id_category`),
  KEY `id_category` (`id_category`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_product_comment_grade` (
  `id_product_comment`           INT(11) UNSIGNED NOT NULL,
  `id_product_comment_criterion` INT(11) UNSIGNED NOT NULL,
  `grade`                        INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_product_comment`, `id_product_comment_criterion`),
  KEY `id_product_comment_criterion` (`id_product_comment_criterion`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_product_comment_usefulness` (
  `id_product_comment` INT(11) UNSIGNED    NOT NULL,
  `id_customer`        INT(11) UNSIGNED    NOT NULL,
  `usefulness`         TINYINT(1) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_product_comment`, `id_customer`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_product_comment_report` (
  `id_product_comment` INT(11) UNSIGNED NOT NULL,
  `id_customer`        INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_product_comment`, `id_customer`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO `PREFIX_product_comment_criterion` VALUES ('1', '1', '1');

INSERT IGNORE INTO `PREFIX_product_comment_criterion_lang` (`id_product_comment_criterion`, `id_lang`, `name`)
  (
    SELECT
      '1',
      l.`id_lang`,
      'Quality'
    FROM `PREFIX_lang` l
  );

