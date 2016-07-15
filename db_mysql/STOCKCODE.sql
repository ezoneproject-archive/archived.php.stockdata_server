-- --------------------------------------------------------
-- 서버 버전:                        5.5.49-MariaDB - Source distribution
-- 서버 OS:                        Linux
-- HeidiSQL 버전:                  9.3.0.4984
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS STOCKCODE (
  STCODE varchar(10) NOT NULL COMMENT '종목코드',
  STNAME varchar(50) DEFAULT NULL COMMENT '종목명',
  CATENAME varchar(100) DEFAULT NULL COMMENT '소속업종',
  PRIMARY KEY (STCODE)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='종목코드 테이블';

