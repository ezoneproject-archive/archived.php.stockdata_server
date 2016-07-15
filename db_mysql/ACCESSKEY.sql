-- --------------------------------------------------------
-- 서버 버전:                        5.5.49-MariaDB - Source distribution
-- 서버 OS:                        Linux
-- HeidiSQL 버전:                  9.3.0.4984
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS ACCESSKEY (
  ACCESS_ID int(11) NOT NULL,
  API_KEY varchar(16) NOT NULL,
  API_SECRET varchar(50) NOT NULL,
  PRIMARY KEY (ACCESS_ID, API_KEY),
  UNIQUE KEY UNIQUE_1 (API_KEY),
  UNIQUE KEY UNIQUE_2 (API_SECRET)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='API Access key list';

