-- --------------------------------------------------------
-- 서버 버전:                        5.5.49-MariaDB - Source distribution
-- 서버 OS:                        Linux
-- HeidiSQL 버전:                  9.3.0.4984
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS SAMTIME (
  SAM_KEY int(11) NOT NULL COMMENT 'SAMMAST 자료 마스터키',
  TIME_VALUE varchar(6) NOT NULL,
  TIME_DISPLAY varchar(8) DEFAULT NULL,
  PRIMARY KEY (SAM_KEY,TIME_VALUE)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='자료시각';

