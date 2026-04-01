CREATE TABLE IF NOT EXISTS llx_sqlwidgets_widget_access (
    rowid      INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_widget  INTEGER NOT NULL,
    fk_group   INTEGER NOT NULL,
    UNIQUE KEY uniq_widget_group (fk_widget, fk_group)
) ENGINE=innodb;
