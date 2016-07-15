-- --------------------------------------------------------
-- 서버 버전:                        5.5.49-MariaDB - Source distribution
-- 서버 OS:                        Linux
-- HeidiSQL 버전:                  9.3.0.4984
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS SAMDATA (
  GSDATE varchar(8) NOT NULL,
  GSTIME varchar(6) NOT NULL,
  SAM_KEY int(11) NOT NULL COMMENT 'SAMMAST PK',
  STCODE varchar(10) NOT NULL,
  ITEM_KEY int(11) NOT NULL COMMENT 'SAMSTRUCT KEY',
  DATA varchar(100) NOT NULL,
  PRIMARY KEY (GSDATE,GSTIME,SAM_KEY,STCODE,ITEM_KEY)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='자료파일 데이터';

