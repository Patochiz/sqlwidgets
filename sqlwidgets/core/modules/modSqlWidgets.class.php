<?php
/* Module SQL Widgets pour Dolibarr - Compatible 11+ */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modSqlWidgets extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf;
        $this->db = $db;
        $this->numero = 499100;
        $this->rights_class = 'sqlwidgets';
        $this->family = 'other';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Widgets SQL dynamiques : chiffres, tableaux et graphiques';
        $this->version = '1.1.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'stats';
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('sqlwidgets@sqlwidgets');
        $this->config_page_url = array('setup.php@sqlwidgets');
        $this->phpmin = array(7, 2);
        $this->need_dolibarr_version = array(20, 0);
		// Author
		$this->editor_name = 'S2IRD';
		$this->editor_url = 'https://www.s2ird.fr';         // Must be an external online web site
		$this->editor_squarred_logo = '';

        $this->module_parts = array('hooks' => array('index'));
        $this->dirs = array();
        $this->const = array();
        $this->tabs = array();
        $this->cronjobs = array();

        // BOX - Déclaration dynamique : une box par widget table/chart
        // Les fichiers box_sqlwidget_ID.php sont générés par SqlWidgetBoxManager
        // lors de la création/modification/suppression d'un widget dans l'admin.
        $this->boxes = array();
        $box_dir = '../../core/boxes/';
        $box_files = glob($box_dir.'box_sqlwidget_[0-9]*.php') ?: array();
        foreach ($box_files as $bf) {
            $this->boxes[] = array(
                'file'               => basename($bf).'@sqlwidgets',
                'note'               => '',
                'enabledbydefaulton' => 'Home',
            );
        }

        // DROITS
        $this->rights = array();
        $r = 0;
        $this->rights[$r][0] = 500201;
        $this->rights[$r][1] = 'Voir les widgets SQL';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 500202;
        $this->rights[$r][1] = 'Creer et modifier widgets SQL';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'write';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 500203;
        $this->rights[$r][1] = 'Supprimer widgets SQL';
        $this->rights[$r][2] = 'd';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'delete';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 500204;
        $this->rights[$r][1] = 'Gerer les droits acces aux widgets';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'manage_access';
        $this->rights[$r][5] = '';
        $r++;

        // MENUS
        $this->menu = array();
        $r = 0;
        $this->menu[$r] = array(
            'fk_menu'  => 'fk_mainmenu=sqlwidgets',
            'type'     => 'left',
            'titre'    => 'Dashboard',
            'mainmenu' => 'sqlwidgets',
            'leftmenu' => 'sqlwidgets_db',
            'url'      => '/sqlwidgets/index.php',
            'langs'    => 'sqlwidgets@sqlwidgets',
            'position' => 10,
            'enabled'  => '$conf->sqlwidgets->enabled',
            'perms'    => '$user->rights->sqlwidgets->read',
            'target'   => '',
            'user'     => 2,
        );
        $r++;
        $this->menu[$r] = array(
            'fk_menu'  => 'fk_mainmenu=sqlwidgets',
            'type'     => 'left',
            'titre'    => 'Gestion widgets',
            'mainmenu' => 'sqlwidgets',
            'leftmenu' => 'sqlwidgets_admin',
            'url'      => '/sqlwidgets/admin/widget_list.php',
            'langs'    => 'sqlwidgets@sqlwidgets',
            'position' => 20,
            'enabled'  => '$conf->sqlwidgets->enabled',
            'perms'    => '$user->rights->sqlwidgets->write',
            'target'   => '',
            'user'     => 2,
        );
        $r++;
        $this->menu[$r] = array(
            'fk_menu'  => 'fk_mainmenu=sqlwidgets,fk_leftmenu=sqlwidgets_admin',
            'type'     => 'left',
            'titre'    => 'Nouveau widget',
            'mainmenu' => 'sqlwidgets',
            'leftmenu' => 'sqlwidgets_new',
            'url'      => '/sqlwidgets/admin/widget_edit.php',
            'langs'    => 'sqlwidgets@sqlwidgets',
            'position' => 21,
            'enabled'  => '$conf->sqlwidgets->enabled',
            'perms'    => '$user->rights->sqlwidgets->write',
            'target'   => '',
            'user'     => 2,
        );
        $r++;
        $this->menu[$r] = array(
            'fk_menu'  => 'fk_mainmenu=sqlwidgets',
            'type'     => 'left',
            'titre'    => 'Droits d acces',
            'mainmenu' => 'sqlwidgets',
            'leftmenu' => 'sqlwidgets_access',
            'url'      => '/sqlwidgets/admin/widget_access.php',
            'langs'    => 'sqlwidgets@sqlwidgets',
            'position' => 30,
            'enabled'  => '$conf->sqlwidgets->enabled',
            'perms'    => '$user->rights->sqlwidgets->manage_access',
            'target'   => '',
            'user'     => 2,
        );
        $r++;
    }

    public function init($options = '')
    {
        $sqls = array();
        $sqls[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."sqlwidgets_widget (
            rowid           integer      NOT NULL AUTO_INCREMENT,
            entity          integer      DEFAULT 1 NOT NULL,
            ref             varchar(128) NOT NULL,
            label           varchar(255) NOT NULL,
            description     text,
            widget_type     varchar(32)  NOT NULL DEFAULT 'number',
            datasource      longtext     NOT NULL,
            color_primary   varchar(32)  DEFAULT '#3B82F6',
            color_secondary varchar(32)  DEFAULT '#10B981',
            color_accent    varchar(32)  DEFAULT '#F59E0B',
            color_bg        varchar(32)  DEFAULT '#1E293B',
            color_text      varchar(32)  DEFAULT '#FFFFFF',
            chart_type      varchar(32)  DEFAULT 'bar',
            type_options    longtext,
            position        integer      DEFAULT 0,
            active          tinyint(1)   DEFAULT 1,
            date_creation   datetime,
            tms             timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            fk_user_creat   integer,
            fk_user_modif   integer,
            PRIMARY KEY (rowid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sqls[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."sqlwidgets_widget_access (
            rowid       integer NOT NULL AUTO_INCREMENT,
            fk_widget   integer NOT NULL,
            fk_group    integer NOT NULL,
            PRIMARY KEY (rowid),
            UNIQUE KEY uniq_sw_access (fk_widget, fk_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        foreach ($sqls as $sql) {
            $res = $this->db->query($sql);
            if (!$res) {
                $this->error = $this->db->lasterror();
                return -1;
            }
        }
        // Synchroniser les box files table/chart
        $dire=__DIR__;
		$dire=str_replace("core/modules","class/sqlwidget_boxmanager.class.php",$dire);
        // Synchroniser les box files table/chart
		require_once $dire;
        $bm = new SqlWidgetBoxManager($this->db);
        $bm->syncAll();

        return $this->_init(array(), $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
