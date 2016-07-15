-- --------------------------------------------------------
-- 서버 버전:                        5.5.49-MariaDB - Source distribution
-- 서버 OS:                        Linux
-- HeidiSQL 버전:                  9.3.0.4984
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS SAMSTRUCT (
  SAM_KEY int(11) NOT NULL COMMENT 'SAMMAST PK',
  ITEM_KEY int(11) NOT NULL COMMENT 'SAM FIELD ITEM PK',
  ITEM_NAME varchar(50) NOT NULL COMMENT '항목명',
  PRIMARY KEY (SAM_KEY,ITEM_KEY)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='자료파일 구조 정보';

