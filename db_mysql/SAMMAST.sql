-- --------------------------------------------------------
-- 서버 버전:                        5.5.49-MariaDB - Source distribution
-- 서버 OS:                        Linux
-- HeidiSQL 버전:                  9.3.0.4984
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS SAMMAST (
  SAM_KEY int(11) NOT NULL AUTO_INCREMENT COMMENT 'PK',
  ACCESS_ID int(11) NOT NULL DEFAULT '0',
  SAM_NAME varchar(100) DEFAULT NULL COMMENT '자료파일명',
  PRIMARY KEY (SAM_KEY)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='자료파일 마스터';

