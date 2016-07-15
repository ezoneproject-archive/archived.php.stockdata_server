-- --------------------------------------------------------
-- 서버 버전:                        5.5.49-MariaDB - Source distribution
-- 서버 OS:                        Linux
-- HeidiSQL 버전:                  9.3.0.4984
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS APILOG (
  NO int(11) NOT NULL AUTO_INCREMENT,
  DATE date NOT NULL,
  TIME time NOT NULL,
  API_KEY varchar(16) NOT NULL,
  REQUEST_ID varchar(50) NOT NULL,
  METHOD varchar(15) NOT NULL,
  RESOURCE varchar(255) NOT NULL,
  MESSAGE mediumtext,
  PRIMARY KEY (NO)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

