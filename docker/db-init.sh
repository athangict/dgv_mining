#!/bin/bash
# Import the bundled dump, continuing past statements that fail
# (the dump has triggers for tables it does not contain).
set -u
echo "[db-init] importing /dump/dgv_mining.sql (with --force)"
mariadb --force -uroot ${MARIADB_ROOT_PASSWORD:+-p"$MARIADB_ROOT_PASSWORD"} < /dump/dgv_mining.sql 2> /tmp/import-errors.log
echo "[db-init] done; $(grep -c ERROR /tmp/import-errors.log) statements skipped:"
cat /tmp/import-errors.log

# The dump does not include the session table used by Auth\Module's
# DbTableGateway save handler (default Laminas schema).
mariadb -uroot ${MARIADB_ROOT_PASSWORD:+-p"$MARIADB_ROOT_PASSWORD"} "${DB_NAME:-dheyma_mining_24}" <<'SQL'
CREATE TABLE IF NOT EXISTS `session` (
  `id`       CHAR(32)     NOT NULL,
  `name`     VARCHAR(255) NOT NULL,
  `modified` INT          NOT NULL,
  `lifetime` INT          NOT NULL,
  `data`     LONGTEXT,
  PRIMARY KEY (`id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stand-in for the login history table written by the sys_users
-- trigger `trigger_login_update` (missing from the partial dump).
CREATE TABLE IF NOT EXISTS `logins` (
  `id`       INT NOT NULL AUTO_INCREMENT,
  `ip`       VARCHAR(64),
  `author`   INT,
  `created`  DATETIME,
  `modified` DATETIME,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
echo "[db-init] session/logins tables ensured"
exit 0
