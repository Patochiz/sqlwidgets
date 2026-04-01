<?php
/**
 * Gestionnaire de boxes dynamiques SQL Widgets
 * Fichier : sqlwidgets/class/sqlwidget_boxmanager.class.php
 *
 * Crée/supprime automatiquement un fichier box_sqlwidget_ID.php
 * dans core/boxes/ lors de la création ou suppression d'un widget
 * de type "table" ou "chart".
 *
 * Les widgets "number" sont gérés exclusivement via le hook
 * addOpenElementsDashboardGroup dans actions_sqlwidgets.class.php.
 */
class SqlWidgetBoxManager
{
    private $boxes_dir;
	public $db;

    public function __construct($db)
    {
        $this->db = $db;
		$this->boxes_dir = '../core/boxes/';
    }

    /**
     * Crée le fichier box stub pour un widget table/chart.
     * Écrase le fichier existant si le label a changé.
     */
    public function createBoxFile($id, $label, $type)
    {
		global $conf,$user;
        $id    = (int)$id;
        $label = strip_tags($label);
        $file  = $this->boxes_dir.'box_sqlwidget_'.$id.'.php';

        $code  = '<?php'."\n";
        $code .= '/* Box auto-générée — Widget SQL #'.$id.' ('.$type.') | '.addslashes($label).' */'."\n";
        $code .= '/* Ne pas éditer manuellement. */'."\n\n";
        $code .= "require_once 'box_sqlwidget_base.php';\n\n";
        $code .= 'class box_sqlwidget_'.$id.' extends box_sqlwidget_base'."\n{\n";
        $code .= "    public \$boxcode  = 'box_sqlwidget_".$id."';\n";
        $code .= "    public \$boximg   = 'stats';\n";
        $code .= "    public \$boxlabel = ".var_export($label, true).";\n";
        $code .= "    public \$depends  = array('sqlwidgets');\n";
        $code .= "    public \$widget_id = ".$id.";\n\n";
        $code .= "    public function __construct(\$db, \$param = '')\n    {\n";
        $code .= "        parent::__construct(\$db, \$param);\n    }\n}\n";
		$rst = file_put_contents($file, $code);
		if($rst===false) 
			return false;
        else{
			$fileok=true;
			$nb=0;
			$sql  = "SELECT count(*) as nb FROM ".MAIN_DB_PREFIX."boxes_def WHERE file='box_sqlwidget_".$id.".php@sqlwidgets'";
			$res  = $this->db->query($sql);
			while ($obj = $this->db->fetch_object($res)) {
				$nb=$obj->nb;
			}
			if($nb==0){
				$sql="INSERT INTO ".MAIN_DB_PREFIX."boxes_def (file,entity,note,fk_user) values 
				('box_sqlwidget_".$id.".php@sqlwidgets',".$conf->entity.",'".$this->db->escape($label)."',0)";
				$res = $this->db->query($sql);
				if(! $res) $fileok=false;
				if($fileok){
					$idbox=$this->db->last_insert_id(MAIN_DB_PREFIX."boxes_def");
					$sql="INSERT INTO ".MAIN_DB_PREFIX."boxes(entity,box_id,position,box_order,fk_user) values (".$conf->entity.",".$idbox.",0,'A01',0)";
					$res = $this->db->query($sql);
					$sql="INSERT INTO ".MAIN_DB_PREFIX."boxes(entity,box_id,position,box_order,fk_user) values (".$conf->entity.",".$idbox.",0,'B01',".$user->id.")";
					$res = $this->db->query($sql);
					if(!$res) if(! $res) $fileok=false;
				}
			}
			return true;
		}
    }

    /**
     * Supprime le fichier box d'un widget.
     */
    public function deleteBoxFile($id)
    {
        $id   = (int)$id;
        $file = $this->boxes_dir.'box_sqlwidget_'.$id.'.php';
		$sql="DELETE FROM ".MAIN_DB_PREFIX."boxes_def a,boxes b WHERE a.rowid=b.box_id and file='box_sqlwidget_".$id.".php@sqlwidgets'";
		$this->db->query($sql);
		$sql="DELETE FROM ".MAIN_DB_PREFIX."boxes_def WHERE file='box_sqlwidget_".$id.".php@sqlwidgets'";
		$this->db->query($sql);
		
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    /**
     * Synchronise tous les fichiers boxes depuis la DB.
     * Crée les manquants, supprime les orphelins.
     * Appelé depuis init() du module et depuis les pages admin.
     */
    public function syncAll()
    {
        global $conf;
        $sql  = "SELECT rowid, label, widget_type FROM ".MAIN_DB_PREFIX."sqlwidgets_widget";
        $sql .= " WHERE entity=".(int)$conf->entity." AND widget_type IN ('table','chart')";
        $res  = $this->db->query($sql);

        $existing_ids = array();
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $existing_ids[] = (int)$obj->rowid;
                $this->createBoxFile((int)$obj->rowid, $obj->label, $obj->widget_type);
            }
        }

        // Supprimer les fichiers orphelins
        $pattern = $this->boxes_dir.'box_sqlwidget_[0-9]*.php';
        foreach (glob($pattern) ?: array() as $file) {
            $id = (int)substr(basename($file, '.php'), strlen('box_sqlwidget_'));
            if ($id > 0 && !in_array($id, $existing_ids)) {
                @unlink($file);
            }
        }
    }

    /**
     * Retourne les définitions de boxes pour le descripteur de module.
     * Lit les fichiers générés dans core/boxes/.
     */
    public static function getBoxDefinitionsForModule()
    {
        $defs  = array();
        $files = glob('box_sqlwidget_[0-9]*.php') ?: array();
        foreach ($files as $file) {
            $defs[] = array(
                'file'               => basename($file).'@sqlwidgets',
                'note'               => '',
                'enabledbydefaulton' => 'Home',
            );
        }
        return $defs;
    }
	public function getBoxDir(){
		return $this->boxes_dir;
	}
	
}
