<?php
/**
 * Classe SQLWidget - Gestion des widgets SQL
 */
require_once 'sqlwidget_boxmanager.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class SqlWidget
{
    public $db;
    public $id;
    public $entity;
    public $ref;
    public $label;
    public $description;
    public $widget_type; // number, table, chart
    public $datasource;
    public $color_primary   = '#3B82F6';
    public $color_secondary = '#10B981';
    public $color_accent    = '#F59E0B';
    public $color_bg        = '#1E293B';
    public $color_text      = '#FFFFFF';
    public $chart_type      = 'bar'; // bar, line, doughnut
    public $type_options    = array();
    public $position        = 0;
    public $active          = 1;
    public $date_creation;
    public $fk_user_creat;
    public $error = '';
    public $errors = array();

    const TYPE_NUMBER = 'number';
    const TYPE_TABLE  = 'table';
    const TYPE_CHART  = 'chart';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create($user)
    {
        global $conf;
        $this->db->begin();
        $opts = is_array($this->type_options) ? json_encode($this->type_options) : $this->type_options;
        $sql  = "INSERT INTO ".MAIN_DB_PREFIX."sqlwidgets_widget";
        $sql .= " (entity,ref,label,description,widget_type,datasource,";
        $sql .= "color_primary,color_secondary,color_accent,color_bg,color_text,";
        $sql .= "chart_type,type_options,position,active,date_creation,fk_user_creat)";
        $sql .= " VALUES (";
        $sql .= $conf->entity.",";
        $sql .= "'".$this->db->escape($this->ref)."',";
        $sql .= "'".$this->db->escape($this->label)."',";
        $sql .= "'".$this->db->escape($this->description)."',";
        $sql .= "'".$this->db->escape($this->widget_type)."',";
        $sql .= "'".$this->db->escape($this->datasource)."',";
        $sql .= "'".$this->db->escape($this->color_primary)."',";
        $sql .= "'".$this->db->escape($this->color_secondary)."',";
        $sql .= "'".$this->db->escape($this->color_accent)."',";
        $sql .= "'".$this->db->escape($this->color_bg)."',";
        $sql .= "'".$this->db->escape($this->color_text)."',";
        $sql .= "'".$this->db->escape($this->chart_type)."',";
        $sql .= "'".$this->db->escape($opts)."',";
        $sql .= (int)$this->position.",";
        $sql .= (int)$this->active.",";
        $sql .= "'".$this->db->idate(dol_now())."',";
        $sql .= $user->id.")";

        $res = $this->db->query($sql);
        if ($res) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."sqlwidgets_widget");
			$fileok=true;
			
			if($this->widget_type!="number"){
				$bm = new SqlWidgetBoxManager($this->db);
				$fileok=$bm->createBoxFile($this->id, $this->label, $this->widget_type);
			}
			if($fileok)
				$this->db->commit();
			else{
				$this->error = "le fichier de référence n'a pas été crée";
				$this->db->rollback();
				return -1;
			}
			
			
            return $this->id;
        } else {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
    }

    public function update($user)
    {
        $this->db->begin();
        $opts = is_array($this->type_options) ? json_encode($this->type_options) : $this->type_options;
        $sql  = "UPDATE ".MAIN_DB_PREFIX."sqlwidgets_widget SET";
        $sql .= " ref='".$this->db->escape($this->ref)."',";
        $sql .= " label='".$this->db->escape($this->label)."',";
        $sql .= " description='".$this->db->escape($this->description)."',";
        $sql .= " widget_type='".$this->db->escape($this->widget_type)."',";
        $sql .= " datasource='".$this->db->escape($this->datasource)."',";
        $sql .= " color_primary='".$this->db->escape($this->color_primary)."',";
        $sql .= " color_secondary='".$this->db->escape($this->color_secondary)."',";
        $sql .= " color_accent='".$this->db->escape($this->color_accent)."',";
        $sql .= " color_bg='".$this->db->escape($this->color_bg)."',";
        $sql .= " color_text='".$this->db->escape($this->color_text)."',";
        $sql .= " chart_type='".$this->db->escape($this->chart_type)."',";
        $sql .= " type_options='".$this->db->escape($opts)."',";
        $sql .= " position=".(int)$this->position.",";
        $sql .= " active=".(int)$this->active.",";
        $sql .= " fk_user_modif=".$user->id;
        $sql .= " WHERE rowid=".(int)$this->id;

        $res = $this->db->query($sql);
        if ($res) { 
			$fileok=true;
			if($this->widget_type!="number"){
				$bm = new SqlWidgetBoxManager($this->db);
				if($this->widget_type=="number"){
					$bm->deleteBoxFile($this->id);
				}
				else{
					$fileok=$bm->createBoxFile($this->id, $this->label, $this->widget_type);			
				}
			}
			if($fileok){
				$this->db->commit(); 
			}
			else{
				$this->error = "le fichier de référence n'a pas été crée";
				$this->db->rollback();
				return -1;
			}
			return 1; 
		}
        $this->error = $this->db->lasterror();
        $this->db->rollback();
        return -1;
    }

    public function fetch($id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."sqlwidgets_widget WHERE rowid=".(int)$id;
        $res = $this->db->query($sql);
        if ($res && $this->db->num_rows($res) > 0) {
            $obj = $this->db->fetch_object($res);
            $this->id             = $obj->rowid;
            $this->entity         = $obj->entity;
            $this->ref            = $obj->ref;
            $this->label          = $obj->label;
            $this->description    = $obj->description;
            $this->widget_type    = $obj->widget_type;
            $this->datasource     = $obj->datasource;
            $this->color_primary  = $obj->color_primary;
            $this->color_secondary= $obj->color_secondary;
            $this->color_accent   = $obj->color_accent;
            $this->color_bg       = $obj->color_bg;
            $this->color_text     = $obj->color_text;
            $this->chart_type     = $obj->chart_type;
            $this->type_options   = json_decode($obj->type_options, true) ?: array();
            $this->position       = $obj->position;
            $this->active         = $obj->active;
            $this->date_creation  = $obj->date_creation;
            $this->fk_user_creat  = $obj->fk_user_creat;
            return 1;
        }
        return 0;
    }

    public function delete($user)
    {
		global $langs;
        $this->db->begin();
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."sqlwidgets_widget_access WHERE fk_widget=".(int)$this->id);
        $res = $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."sqlwidgets_widget WHERE rowid=".(int)$this->id);
        if ($res) { 
			
			$bm = new SqlWidgetBoxManager($this->db);
			$bm->deleteBoxFile((int)$this->id);

			
			$this->db->commit(); 
			setEventMessages($langs->trans("WidgetDeleted"),null,"mesgs");
			return 1; 
		}
        $this->error = $this->db->lasterror();
        $this->db->rollback();
        return -1;
    }

    public function fetchAll($activeonly = true)
    {
        global $conf, $user;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."sqlwidgets_widget WHERE entity=".$conf->entity;
        if ($activeonly) $sql .= " AND active=1";
        $sql .= " ORDER BY position ASC, rowid ASC";
        $res = $this->db->query($sql);
        $list = array();
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $w = new SqlWidget($this->db);
                $w->fetch($obj->rowid);
                $list[] = $w;
            }
        }
        return $list;
    }

    public function fetchAllForUser($user,$number=false)
    {
        global $conf;

        if ($number) {
            // Number widgets appear automatically — no access check needed
            $sql = "SELECT DISTINCT w.rowid FROM ".MAIN_DB_PREFIX."sqlwidgets_widget w";
            $sql .= " WHERE w.entity=".$conf->entity." AND w.active=1";
            $sql .= " AND w.widget_type='number'";
            $sql .= " ORDER BY w.position ASC, w.rowid ASC";
        } else {
            // Table/chart widgets require group-based access
            $sql = "SELECT DISTINCT w.rowid FROM ".MAIN_DB_PREFIX."sqlwidgets_widget w";
            $sql .= " INNER JOIN ".MAIN_DB_PREFIX."sqlwidgets_widget_access a ON a.fk_widget=w.rowid";
            $sql .= " INNER JOIN ".MAIN_DB_PREFIX."usergroup_user ugu ON ugu.fk_usergroup=a.fk_group";
            $sql .= " WHERE ugu.fk_user=".$user->id." AND w.entity=".$conf->entity." AND w.active=1";
            $sql .= " AND w.widget_type<>'number'";
            $sql .= " ORDER BY w.position ASC, w.rowid ASC";
        }
        $res = $this->db->query($sql);
        $list = array();
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $w = new SqlWidget($this->db);
                $w->fetch($obj->rowid);
                $list[] = $w;
            }
        }
        return $list;
    }

    public function getAccess()
    {
        $sql = "SELECT fk_group FROM ".MAIN_DB_PREFIX."sqlwidgets_widget_access WHERE fk_widget=".(int)$this->id;
        $res = $this->db->query($sql);
        $groups = array();
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $groups[] = $obj->fk_group;
            }
        }
        return $groups;
    }

    public function saveAccess($groupids)
    {
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."sqlwidgets_widget_access WHERE fk_widget=".(int)$this->id);
        foreach ($groupids as $gid) {
            $this->db->query("INSERT INTO ".MAIN_DB_PREFIX."sqlwidgets_widget_access (fk_widget,fk_group) VALUES (".(int)$this->id.",".(int)$gid.")");
        }
        return 1;
    }

    /**
     * Résout les variables {{variable}} dans une requête SQL.
     *
     * Variables disponibles :
     *
     * -- Utilisateur connecté --
     *   {{user_id}}          ID de l'utilisateur courant
     *   {{user_login}}       Login de l'utilisateur courant
     *   {{user_lastname}}    Nom de l'utilisateur courant
     *   {{user_firstname}}   Prénom de l'utilisateur courant
     *   {{user_email}}       Email de l'utilisateur courant
     *
     * -- Dates courantes --
     *   {{today}}            Date du jour           (YYYY-MM-DD)
     *   {{now}}              Date + heure courante  (YYYY-MM-DD HH:MM:SS)
     *   {{yesterday}}        Hier                   (YYYY-MM-DD)
     *   {{year}}             Année courante         (YYYY)
     *   {{month}}            Mois courant           (MM)
     *   {{day}}              Jour courant           (DD)
     *
     * -- Périodes --
     *   {{first_day_month}}  Premier jour du mois courant  (YYYY-MM-01)
     *   {{last_day_month}}   Dernier jour du mois courant  (YYYY-MM-DD)
     *   {{first_day_year}}   Premier jour de l'année       (YYYY-01-01)
     *   {{last_day_year}}    Dernier jour de l'année       (YYYY-12-31)
     *   {{first_day_week}}   Lundi de la semaine courante  (YYYY-MM-DD)
     *   {{last_day_week}}    Dimanche de la semaine cour.  (YYYY-MM-DD)
     *   {{prev_month_start}} Premier jour du mois préc.    (YYYY-MM-01)
     *   {{prev_month_end}}   Dernier jour du mois préc.    (YYYY-MM-DD)
     *   {{prev_year}}        Année précédente              (YYYY)
     *   {{30_days_ago}}      Il y a 30 jours               (YYYY-MM-DD)
     *   {{90_days_ago}}      Il y a 90 jours               (YYYY-MM-DD)
     *   {{12_months_ago}}    Il y a 12 mois                (YYYY-MM-DD)
     *
     * -- Dolibarr --
     *   {{entity}}           ID de l'entité courante
     *   {{currency}}         Code devise de l'entité (EUR, USD…)
     *   {{llx}}              Préfixe des tables Dolibarr (ex: llx_)
     *
     * @param  string $sql   Requête avec variables {{...}}
     * @param  object $user  Objet user Dolibarr
     * @return string        Requête avec variables résolues
     */
    public function resolveVariables($sql, $user = null)
    {
        global $conf;

        if (strpos($sql, '{{') === false) {
            return $sql; // Pas de variables → retour immédiat
        }

        // ── Dates & périodes ────────────────────────────────────────────────
        $today   = date('Y-m-d');
        $now     = date('Y-m-d H:i:s');
        $year    = date('Y');
        $month   = date('m');
        $day     = date('d');

        // Premier/dernier jour du mois courant
        $first_day_month = date('Y-m-01');
        $last_day_month  = date('Y-m-t');

        // Première/dernière jour de l'année
        $first_day_year  = date('Y').'-01-01';
        $last_day_year   = date('Y').'-12-31';

        // Lundi et dimanche de la semaine courante (ISO 8601)
        $day_of_week     = (int)date('N'); // 1=lundi, 7=dimanche
        $first_day_week  = date('Y-m-d', strtotime('-'.($day_of_week - 1).' days'));
        $last_day_week   = date('Y-m-d', strtotime('+'. (7 - $day_of_week).' days'));

        // Mois précédent
        $prev_month_ts    = strtotime('first day of last month');
        $prev_month_start = date('Y-m-01', $prev_month_ts);
        $prev_month_end   = date('Y-m-t',  $prev_month_ts);

        $prev_year        = (string)((int)$year - 1);
        $yesterday        = date('Y-m-d', strtotime('-1 day'));
        $days_30_ago      = date('Y-m-d', strtotime('-30 days'));
        $days_90_ago      = date('Y-m-d', strtotime('-90 days'));
        $months_12_ago    = date('Y-m-d', strtotime('-12 months'));

        // ── Utilisateur ─────────────────────────────────────────────────────
        $user_id        = ($user && $user->id)        ? (int)$user->id                                    : 0;
        $user_login     = ($user && $user->login)     ? $this->db->escape($user->login)                   : '';
        $user_lastname  = ($user && $user->lastname)  ? $this->db->escape($user->lastname)                : '';
        $user_firstname = ($user && $user->firstname) ? $this->db->escape($user->firstname)               : '';
        $user_email     = ($user && $user->email)     ? $this->db->escape($user->email)                   : '';

        // ── Dolibarr ─────────────────────────────────────────────────────────
        $entity   = isset($conf->entity)   ? (int)$conf->entity          : 1;
        $currency = isset($conf->currency) ? $this->db->escape($conf->currency) : 'EUR';
        $llx      = MAIN_DB_PREFIX; // ex: "llx_"

        // ── Table de remplacement ────────────────────────────────────────────
        $vars = array(
            // Utilisateur
            '{{user_id}}'          => $user_id,
            '{{user_login}}'       => "'".$user_login."'",
            '{{user_lastname}}'    => "'".$user_lastname."'",
            '{{user_firstname}}'   => "'".$user_firstname."'",
            '{{user_email}}'       => "'".$user_email."'",
            // Dates
            '{{today}}'            => "'".$today."'",
            '{{now}}'              => "'".$now."'",
            '{{yesterday}}'        => "'".$yesterday."'",
            '{{year}}'             => $year,
            '{{month}}'            => $month,
            '{{day}}'              => $day,
            // Périodes
            '{{first_day_month}}'  => "'".$first_day_month."'",
            '{{last_day_month}}'   => "'".$last_day_month."'",
            '{{first_day_year}}'   => "'".$first_day_year."'",
            '{{last_day_year}}'    => "'".$last_day_year."'",
            '{{first_day_week}}'   => "'".$first_day_week."'",
            '{{last_day_week}}'    => "'".$last_day_week."'",
            '{{prev_month_start}}' => "'".$prev_month_start."'",
            '{{prev_month_end}}'   => "'".$prev_month_end."'",
            '{{prev_year}}'        => $prev_year,
            '{{30_days_ago}}'      => "'".$days_30_ago."'",
            '{{90_days_ago}}'      => "'".$days_90_ago."'",
            '{{12_months_ago}}'    => "'".$months_12_ago."'",
            // Dolibarr
            '{{entity}}'           => $entity,
            '{{currency}}'         => "'".$currency."'",
            '{{llx}}'              => $llx,
        );

        return str_replace(array_keys($vars), array_values($vars), $sql);
    }

    /**
     * Exécute la requête SQL du widget - retourne un tableau de résultats
     * Les variables {{...}} sont résolues avant exécution.
     *
     * @param string $sql  Override SQL (pour les sous-chiffres)
     * @param object $user Objet user Dolibarr (optionnel, utilise le global par défaut)
     */
    public function executeQuery($sql = '', $user_obj = null)
    {
        global $langs, $user;

        if (empty($sql)) $sql = $this->datasource;

        // Résolution des variables {{...}} avant exécution
        $resolved_user = $user_obj ?: $user;
        $sql = $this->resolveVariables($sql, $resolved_user);

        // Sécurité : on n'autorise que les SELECT
        $clean = trim(preg_replace('/\s+/', ' ', strtoupper($sql)));
        if (strpos($clean, 'SELECT') !== 0 && strpos($clean, 'WITH') !== 0) {
            return array('error' => $langs->trans('WidgetSelect'));
        }
        $res = $this->db->query($sql);
        if (!$res) return array('error' => $this->db->lasterror());
        $rows = array();
        while ($obj = $this->db->fetch_object($res)) {
            $rows[] = (array)$obj;
        }
        return $rows;
    }

    public function validateSql($sql)
    {
        // Neutraliser les variables avant validation pour ne pas fausser le test
        $sql_clean = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '1', $sql);
        // Neutraliser les commentaires SQL (pourraient masquer des mots-clés)
        $sql_clean = preg_replace('!/\*.*?\*/!s', ' ', $sql_clean);
        $sql_clean = preg_replace('/--.*$/m', ' ', $sql_clean);

        $clean = trim(preg_replace('/\s+/', ' ', strtoupper($sql_clean)));
        if (strpos($clean, 'SELECT') !== 0 && strpos($clean, 'WITH') !== 0) {
            return false;
        }
        // Bloque les mots-clés dangereux
        $forbidden = array('DROP ', 'DELETE ', 'UPDATE ', 'INSERT ', 'ALTER ', 'CREATE ', 'TRUNCATE ', 'EXEC ', 'EXECUTE ');
        foreach ($forbidden as $kw) {
            if (strpos(strtoupper($sql_clean), $kw) !== false) return false;
        }
        return true;
    }
}
