<?php
/* Création / édition d'un widget SQL */
$dir = __DIR__;
$found = false;
for ($i = 0; $i < 10; $i++) {
    $try = $dir . '/main.inc.php';
    if (file_exists($try)) {
        $res   = @include $try;
        $found = true;
        break;
    }
    $parent = dirname($dir);
    if ($parent === $dir) break; // racine du filesystem, on arrête
    $dir = $parent;
}
if (!$found) { http_response_code(500); die('Cannot find main.inc.php'); }
require_once '../class/sqlwidget.class.php';
require_once '../class/sqlwidget_boxmanager.class.php';
$langs->loadLangs(array("sqlwidgets@sqlwidgets"));
if (!$user->rights->sqlwidgets->write) accessforbidden();

$id     = GETPOST('id','int');
$action = GETPOST('action','aZ09');
$object = new SqlWidget($db);

if ($id > 0) $object->fetch($id);

// ---- Handle save ----
if ($action === 'save') {
    $object->ref           = GETPOST('ref','alphanohtml');
    $object->label         = GETPOST('label','alphanohtml');
    $object->description   = GETPOST('description','restricthtml');
    $object->widget_type   = GETPOST('widget_type','aZ09');
    $object->datasource    = GETPOST('datasource','nohtml');
    $object->color_primary = GETPOST('color_primary','nohtml');
    $object->color_secondary = GETPOST('color_secondary','nohtml');
    $object->color_accent  = GETPOST('color_accent','nohtml');
    $object->color_bg      = GETPOST('color_bg','nohtml');
    $object->color_text    = GETPOST('color_text','nohtml');
    $object->chart_type    = GETPOST('chart_type','aZ09');
    $object->position      = GETPOST('position','int');
    $object->active        = GETPOST('active','int');

    // Type options
    $opts = array();
    if ($object->widget_type === 'number') {
        $opts['icon']        = GETPOST('opt_icon','alphanohtml');
        $opts['link_url']    = GETPOST('opt_link_url','nohtml');
        $opts['prefix']      = GETPOST('opt_prefix','alphanohtml');
        $opts['suffix']      = GETPOST('opt_suffix','alphanohtml');
        $opts['sub1_label']  = GETPOST('opt_sub1_label','alphanohtml');
        $opts['sub1_sql']    = GETPOST('opt_sub1_sql','nohtml');
        $opts['sub2_label']  = GETPOST('opt_sub2_label','alphanohtml');
        $opts['sub2_sql']    = GETPOST('opt_sub2_sql','nohtml');
    } elseif ($object->widget_type === 'table') {
        $cols_json = GETPOST('opt_columns','nohtml');
        $opts['columns'] = json_decode($cols_json, true) ?: array();
    } elseif ($object->widget_type === 'chart') {
        $opts['colors'] = array_filter(array_map('trim', explode(',', GETPOST('opt_colors','nohtml'))));
    }
    $object->type_options = $opts;

    if (!$object->validateSql($object->datasource)) {
        setEventMessages('Requête SQL invalide. Seules les requêtes SELECT sont autorisées.', null, 'errors');
    } else {
        if ($id > 0) { $object->id = $id; $res2 = $object->update($user); $msg = 'WidgetUpdated'; }
        else         { $res2 = $object->create($user); $msg = 'WidgetCreated'; }
        if ($res2 > 0) {
            setEventMessages($langs->trans($msg), null, 'mesgs');
            // Créer/mettre à jour/supprimer le fichier box selon le type de widget
            $bm = new SqlWidgetBoxManager($db);
            if (in_array($object->widget_type, array('table', 'chart'))) {
                $wid = ($id > 0) ? $id : $res2;
                $bm->createBoxFile($wid, $object->label, $object->widget_type);
            } else {
                // Si le type a changé vers 'number', supprimer l'ancienne box
                if ($id > 0) $bm->deleteBoxFile($id);
            }
            header('Location: widget_list.php'); exit;
        } else {
            setEventMessages($object->error, null, 'errors');
        }
    }
}


// ---- Handle AJAX SQL test ----
if ($action === 'test_sql' && !empty($_POST['sql'])) {
    $sql = GETPOST('sql','nohtml');
    if (!$object->validateSql($sql)) {
        echo json_encode(array('error'=>'Requête non autorisée (SELECT uniquement)'));
    } else {
        $rows = $object->executeQuery($sql);
        if (isset($rows['error'])) echo json_encode(array('error'=>$rows['error']));
        else echo json_encode(array('success'=>true,'count'=>count($rows),'preview'=>array_slice($rows,0,3)));
    }
    exit;
}


// ---- View ----
$title = $id > 0 ? $langs->trans('WidgetEdit') : $langs->trans('WidgetCreate');
$title='';
llxHeader('', $title, '', '', 0, 0);
print dol_get_fiche_head(array(), '', $langs->trans('SQLWidgets'), 0, 'stats');
print '<h2 style="margin-top:0">'.$title.'</h2>';

$tok = newToken();
$opts = $object->type_options;
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
.sw-section { background:#f8f9fa;border-radius:8px;padding:15px 20px;margin-bottom:15px;border:1px solid #e0e0e0; }
.sw-section h3 { margin-top:0;color:#333;font-size:14px;text-transform:uppercase;letter-spacing:.5px; }
.sw-row { display:flex;gap:15px;flex-wrap:wrap; }
.sw-row .sw-field { flex:1;min-width:200px; }
.sw-field label { display:block;font-weight:600;margin-bottom:4px;font-size:13px;color:#555; }
.sw-field input[type=text],.sw-field input[type=color],.sw-field select,.sw-field textarea {
    width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:5px;font-size:13px;box-sizing:border-box; }
.sw-field textarea { min-height:80px;font-family:monospace; }
.col-config-row { display:flex;gap:8px;margin-bottom:6px;align-items:center; }
.col-config-row input { flex:1;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:12px; }
.type-panel { display:none; }
.type-panel.active { display:block; }
#sql-preview { background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;font-family:monospace;font-size:12px;white-space:pre-wrap;margin-top:8px;min-height:40px; }
</style>

<form method="POST" action="widget_edit.php<?php echo $id>0 ? '?id='.$id : ''; ?>" id="swForm">
<input type="hidden" name="token" value="<?php echo $tok; ?>">
<input type="hidden" name="action" value="save">

<!-- Informations générales -->
<div class="sw-section">
<h3><?php echo $langs->trans("WidgetInfoGeneral")?></h3>
<div class="sw-row">
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetReference")?></label>
    <input type="text" name="ref" value="<?php echo dol_escape_htmltag($object->ref); ?>" required placeholder="ex: nb_commandes_mois">
  </div>
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetLibelle")?></label>
    <input type="text" name="label" value="<?php echo dol_escape_htmltag($object->label); ?>" required placeholder="ex: Commandes du mois">
  </div>
  <div class="sw-field" style="max-width:120px">
    <label><?php echo $langs->trans("WidgetPosition")?></label>
    <input type="text" name="position" value="<?php echo (int)$object->position; ?>">
  </div>
  <div class="sw-field" style="max-width:100px">
    <label><?php echo $langs->trans("WidgetActif")?></label>
    <select name="active">
      <option value="1" <?php echo $object->active?'selected':''; ?>><?php echo $langs->trans("WidgetOui")?></option>
      <option value="0" <?php echo !$object->active?'selected':''; ?>><?php echo $langs->trans("WidgetNon")?></option>
    </select>
  </div>
</div>
<div class="sw-field" style="margin-top:10px">
  <label>Description</label>
  <input type="text" name="description" value="<?php echo dol_escape_htmltag($object->description); ?>" placeholder="Description optionnelle">
</div>
</div>

<!-- Type de widget -->
<div class="sw-section">
<h3><?php echo $langs->trans("WidgetType")?></h3>
<div class="sw-row" style="gap:10px">
  <?php
  $types = array(
	'number'=>array('label'=>$langs->trans("WidgetChiffre"),'icon'=>'#','color'=>'#3B82F6'), 
	'table'=>array('label'=>$langs->trans("WidgetTableau"),'icon'=>'⊞','color'=>'#10B981'), 
	'chart'=>array('label'=>$langs->trans("WidgetGraphique"),'icon'=>'📊','color'=>'#F59E0B')
  );
  foreach ($types as $tkey=>$tval) {
	$affiche=true;
	if($id > 0){
		if($object->widget_type != $tkey) $affiche=false;
	}
	if($affiche){
		$sel = $object->widget_type===$tkey ? 'border:3px solid '.$tval['color'].';background:'.dol_escape_htmltag($tval['color']).'22' : 'border:2px solid #ddd';
		echo '<label style="cursor:pointer;padding:15px 25px;border-radius:8px;text-align:center;'.$sel.';min-width:100px">';
		echo '<input type="radio" name="widget_type" value="'.dol_escape_htmltag($tkey).'" '.($object->widget_type===$tkey?'checked':'').' onchange="showTypePanel(this.value)" style="display:none">';
		echo '<div style="font-size:24px">'.$tval['icon'].'</div>';
		echo '<div style="font-weight:600;color:'.dol_escape_htmltag($tval['color']).'">'.$tval['label'].'</div>';
		echo '</label>';
	}
  }
  ?>
</div>
</div>

<!-- Source de données SQL -->
<div class="sw-section">
<h3><?php echo $langs->trans("WidgetDataSourceSQL")?></h3>

<!-- Bouton variables -->
<div style="margin-bottom:10px">
  <button type="button" onclick="toggleVarPanel()" style="background:#6366F1;color:#fff;border:none;border-radius:5px;padding:6px 14px;cursor:pointer;font-size:12px;font-weight:600">
    {{ }} Variables disponibles
  </button>
  <span style="font-size:11px;color:#94A3B8;margin-left:10px">Injectez des données dynamiques dans vos requêtes SQL</span>
</div>

<!-- Panneau de référence des variables -->
<div id="var-panel" style="display:none;background:#1e293b;border-radius:8px;padding:16px 20px;margin-bottom:14px;font-size:12px;line-height:2;color:#e2e8f0">
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:4px 36px">

    <?php
    // Groupes de variables
    $var_groups = array(
      array(
        'icon'  => '👤',
        'title' => 'Utilisateur connecté',
        'color' => '#818cf8',
        'vars'  => array(
          '{{user_id}}'        => "ID de l'utilisateur",
          '{{user_login}}'     => "Login",
          '{{user_lastname}}'  => "Nom",
          '{{user_firstname}}' => "Prénom",
          '{{user_email}}'     => "Email",
        ),
      ),
      array(
        'icon'  => '📅',
        'title' => 'Dates courantes',
        'color' => '#34d399',
        'vars'  => array(
          '{{today}}'     => "Aujourd'hui (YYYY-MM-DD)",
          '{{now}}'       => "Date + heure courante",
          '{{yesterday}}' => "Hier",
          '{{year}}'      => "Année courante",
          '{{month}}'     => "Mois courant (MM)",
          '{{day}}'       => "Jour courant (DD)",
        ),
      ),
      array(
        'icon'  => '📆',
        'title' => 'Périodes',
        'color' => '#fbbf24',
        'vars'  => array(
          '{{first_day_month}}'  => "1er jour du mois courant",
          '{{last_day_month}}'   => "Dernier jour du mois courant",
          '{{prev_month_start}}' => "1er jour du mois précédent",
          '{{prev_month_end}}'   => "Dernier jour du mois précédent",
          '{{first_day_year}}'   => "1er janvier de l'année",
          '{{last_day_year}}'    => "31 décembre de l'année",
          '{{prev_year}}'        => "Année précédente",
          '{{first_day_week}}'   => "Lundi de la semaine courante",
          '{{last_day_week}}'    => "Dimanche de la semaine courante",
          '{{30_days_ago}}'      => "Il y a 30 jours",
          '{{90_days_ago}}'      => "Il y a 90 jours",
          '{{12_months_ago}}'    => "Il y a 12 mois",
        ),
      ),
      array(
        'icon'  => '⚙️',
        'title' => 'Dolibarr',
        'color' => '#f472b6',
        'vars'  => array(
          '{{entity}}'   => "ID entité courante",
          '{{currency}}' => "Code devise (EUR, USD…)",
          '{{llx}}'      => "Préfixe tables (ex: llx_)",
        ),
      ),
    );

    foreach ($var_groups as $group) {
      echo '<div style="margin-bottom:8px">';
      echo '<div style="color:#64748b;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;';
      echo 'margin-bottom:4px;border-bottom:1px solid #334155;padding-bottom:3px">';
      echo $group['icon'].' '.$group['title'].'</div>';
      foreach ($group['vars'] as $var => $desc) {
        echo '<div style="display:flex;align-items:baseline;gap:8px;padding:1px 0">';
        echo '<code onclick="insertVar(\''.dol_escape_htmltag($var).'\')" ';
        echo 'style="background:#334155;padding:0 6px;border-radius:3px;color:'.dol_escape_htmltag($group['color']).';';
        echo 'cursor:pointer;white-space:nowrap;font-size:11px" title="Cliquer pour insérer dans l\'éditeur">';
        echo dol_escape_htmltag($var).'</code>';
        echo '<span style="color:#64748b;font-size:11px">'.dol_escape_htmltag($desc).'</span>';
        echo '</div>';
      }
      echo '</div>';
    }
    ?>

  </div>
  <div style="margin-top:10px;padding:8px 12px;background:#0f172a;border-radius:5px;color:#64748b;font-size:11px;line-height:1.7">
    💡 <strong style="color:#94a3b8">Cliquez sur une variable</strong> pour l'insérer à la position du curseur dans l'éditeur SQL.<br>
    Les variables sont <strong style="color:#94a3b8">résolues dynamiquement</strong> à chaque exécution de la requête (date du jour, utilisateur connecté, etc.).<br>
    Exemple : <code style="background:#1e293b;padding:0 5px;border-radius:3px;color:#34d399">WHERE entity = {{entity}} AND date_cmd >= {{first_day_month}}</code>
  </div>
</div>

<div class="sw-field">
  <label><?php echo $langs->trans("WidgetSQLPrincipal")?></label>
  <textarea name="datasource" id="datasource" required
    placeholder="SELECT COUNT(*) as valeur FROM llx_commande WHERE entity = {{entity}} AND date_commande >= {{first_day_month}}"
  ><?php echo dol_escape_htmltag($object->datasource); ?></textarea>
</div>
<div style="margin-top:8px">
  <button type="button" onclick="testSQL(document.getElementById('datasource').value,'sql-preview')" class="button smallpadding">
    <?php echo $langs->trans("WidgetTestSQL")?>
  </button>
</div>
<div id="sql-preview"><?php echo $langs->trans("WidgetTestClick")?></div>
</div>

<!-- Couleurs -->
<div class="sw-section">
<h3><?php echo $langs->trans("WidgetCouleur")?></h3>
<div class="sw-row">
  <?php
  $colors = array(
	'color_primary'=>array($langs->trans("WidgetColorPrinc"),$object->color_primary,'#3B82F6'), 
	'color_secondary'=>array($langs->trans("WidgetColorSec"),$object->color_secondary,'#10B981'), 
	'color_accent'=>array($langs->trans("WidgetColorAcc"),$object->color_accent,'#F59E0B'), 
	'color_bg'=>array($langs->trans("WidgetColorFond"),$object->color_bg,'#1E293B'), 
	'color_text'=>array($langs->trans("WidgetColorText"),$object->color_text,'#FFFFFF')
  );
  foreach ($colors as $cname=>$cinfo) {
    echo '<div class="sw-field" style="max-width:160px">';
    echo '<label>'.$cinfo[0].'</label>';
    echo '<input type="color" name="'.dol_escape_htmltag($cname).'" value="'.dol_escape_htmltag($cinfo[1] ?: $cinfo[2]).'">';
    echo '</div>';
  }
  ?>
</div>
</div>

<!-- Options spécifiques au type : Chiffre -->
<div id="panel-number" class="sw-section type-panel <?php echo $object->widget_type==='number'?'active':''; ?>">
<h3><?php echo $langs->trans("WidgetOptionChiffre")?></h3>
<div class="sw-row">
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetIcone")?> <small style="color:#999;font-weight:normal">(picto Dolibarr : <code>order</code>, FA : <code>fa-chart-bar</code>, emoji : 📊)</small></label>
    <input type="text" name="opt_icon" value="<?php echo dol_escape_htmltag($opts['icon']??'stats'); ?>" placeholder="stats / fa-shopping-cart / 📦">
  </div>
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetLien")?></label>
    <input type="text" name="opt_link_url" value="<?php echo dol_escape_htmltag($opts['link_url']??''); ?>" placeholder="/dolibarr/commande/list.php">
  </div>
</div>
<div class="sw-row">
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetPrefix")?></label>
    <input type="text" name="opt_prefix" value="<?php echo dol_escape_htmltag($opts['prefix']??''); ?>" placeholder="€">
  </div>
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetSuffixe")?></label>
    <input type="text" name="opt_suffix" value="<?php echo dol_escape_htmltag($opts['suffix']??''); ?>" placeholder="%">
  </div>
</div>
<hr>
<div class="sw-row">
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetLibelleSous1")?></label>
    <input type="text" name="opt_sub1_label" value="<?php echo dol_escape_htmltag($opts['sub1_label']??''); ?>" placeholder="Ce mois">
  </div>
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetSQLSous1")?></label>
    <textarea name="opt_sub1_sql" rows="2" placeholder="SELECT COUNT(*) FROM ..."><?php echo dol_escape_htmltag($opts['sub1_sql']??''); ?></textarea>
  </div>
  <button type="button" onclick="testSQL(document.querySelector('[name=opt_sub1_sql]').value,'sql-sub1-preview')" class="button smallpadding" style="align-self:flex-end">
	<?php echo $langs->trans("WidgetTester")?>
</button>
</div>
<div id="sql-sub1-preview" style="background:#1e293b;color:#e2e8f0;padding:8px;border-radius:4px;font-size:11px;margin-top:4px;display:none"></div>
<div class="sw-row" style="margin-top:10px">
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetLibelleSous2")?></label>
    <input type="text" name="opt_sub2_label" value="<?php echo dol_escape_htmltag($opts['sub2_label']??''); ?>" placeholder="Cette année">
  </div>
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetSQLSous2")?></label>
    <textarea name="opt_sub2_sql" rows="2" placeholder="SELECT COUNT(*) FROM ..."><?php echo dol_escape_htmltag($opts['sub2_sql']??''); ?></textarea>
  </div>
  <button type="button" onclick="testSQL(document.querySelector('[name=opt_sub2_sql]').value,'sql-sub2-preview')" class="button smallpadding" style="align-self:flex-end">
	<?php echo $langs->trans("WidgetTester")?>
  </button>
</div>
<div id="sql-sub2-preview" style="background:#1e293b;color:#e2e8f0;padding:8px;border-radius:4px;font-size:11px;margin-top:4px;display:none"></div>
</div>

<!-- Options spécifiques : Tableau -->
<div id="panel-table" class="sw-section type-panel <?php echo $object->widget_type==='table'?'active':''; ?>">
<h3><?php echo $langs->trans("WidgetOptionTable") ?></h3>
<p style="font-size:12px;color:#666;margin-top:0"><?php echo $langs->trans("WidgetConfigChamps") ?></p>
<div id="columns-builder">
  <?php
  $cols = $opts['columns'] ?? array();
  if (empty($cols)) $cols = array(array('field'=>'','label'=>'','type'=>'','link'=>'','class'=>'','badge'=>''));
  foreach ($cols as $ci=>$col) { ?>
	<div class="col-row" style="border:1px solid #e0e0e0;border-radius:6px;padding:10px;margin-bottom:8px;background:#fff">
		<div class="sw-row">
			<div class="sw-field">
				<label><?php echo $langs->trans("WidgetChampsSQL") ?></label>
				<input type="text" class="col-field" value="<?php echo dol_escape_htmltag($col['field']??''); ?>" placeholder="id">
			</div>
			<div class="sw-field">
				<label><?php echo $langs->trans("WidgetLibelle") ?></label>
				<input type="text" class="col-label" value="<?php echo dol_escape_htmltag($col['label']??''); ?>" placeholder="Identifiant">
			</div>
			
			
			<div class="sw-field">
				<label><?php echo $langs->trans("WidgetTypeDonnee") ?></label>
				<select name="type" class="col-type">
				  <option value="varchar" 	<?php echo $col['type']=="varchar"  ?'selected':''; ?>><?php echo $langs->trans("WidgetText") ?></option>
				  <option value="int" 		<?php echo $col['type']=="int"      ?'selected':''; ?>><?php echo $langs->trans("WidgetEntier") ?></option>
				  <option value="numeric" 	<?php echo $col['type']=="numeric"  ?'selected':''; ?>><?php echo $langs->trans("WidgetNumeric") ?></option>
				  <option value="price" 	<?php echo $col['type']=="price"    ?'selected':''; ?>><?php echo $langs->trans("WidgetPrix") ?></option>
				  <option value="pourcent" 	<?php echo $col['type']=="pourcent" ?'selected':''; ?>><?php echo $langs->trans("WidgetPourcent") ?></option>
				</select>
			</div>
			
			
			<div class="sw-field">
				<label><?php echo $langs->trans("WidgetLien") ?></label>
				<input type="text" class="col-link" value="<?php echo dol_escape_htmltag($col['link']??''); ?>" placeholder="/dolibarr/commande/card.php?id={id}">
			</div>
		</div>
		<div class="sw-row" style="margin-top:6px">
			<div class="sw-field">
				<label><?php echo $langs->trans("WidgetClassCSS") ?></label>
				<input type="text" class="col-class" value="<?php echo dol_escape_htmltag($col['class']??''); ?>" placeholder="text-right nowraponall">
			</div>
			<div class="sw-field">
				<label><?php echo $langs->trans("WidgetBadesJSON") ?> ({"0":{"label":"Draft","color":"#999"},"1":{"label":"<?php echo $langs->trans("WidgetValide") ?>","color":"#10B981"}})</label>
				<input type="text" class="col-badge" value="<?php echo dol_escape_htmltag($col['badge']??''); ?>" placeholder="{}">
			</div>
			<button 
				type="button" 
				onclick="this.closest('.col-row').remove()" 
				style="background:#ef4444;color:#fff;border:none;border-radius:4px;padding:5px 10px;cursor:pointer;align-self:flex-end">
				✕
			</button>
		</div>
	</div>
  <?php } ?>
</div>
<input type="hidden" name="opt_columns" id="opt_columns" value="<?php echo dol_escape_htmltag(json_encode($cols)); ?>">
<button type="button" onclick="addColumn()" class="button smallpadding" style="margin-top:8px"><?php echo $langs->trans("WidgetAddColonne") ?></button>
</div>

<!-- Options spécifiques : Graphique -->
<div id="panel-chart" class="sw-section type-panel <?php echo $object->widget_type==='chart'?'active':''; ?>">
<h3><?php echo $langs->trans("WidgetOptionGraphique") ?></h3>
<p style="font-size:12px;color:#666;margin-top:0"><?php echo $langs->trans("WidgetLibelleGraph") ?></p>
<div class="sw-row">
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetTypeGraph") ?></label>
    <select name="chart_type" id="chart_type">
      <?php foreach (array('bar'=>$langs->trans("WidgetBarres"),'line'=>$langs->trans("WidgetCourbes"),'doughnut'=>$langs->trans("WidgetAnneaux")) as $ct=>$cl) { echo '<option value="'.$ct.'" '.($object->chart_type===$ct?'selected':'').'>'.$cl.'</option>'; } ?>
    </select>
  </div>
  <div class="sw-field">
    <label><?php echo $langs->trans("WidgetCouleurSeries") ?></label>
    <input type="text" name="opt_colors" value="<?php echo dol_escape_htmltag(implode(',', $opts['colors']??array('#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6'))); ?>" placeholder="#3B82F6,#10B981,#F59E0B">
  </div>
</div>
</div>

<!-- Boutons -->
<div class="tabsAction">
  <button type="submit" class="butAction"><?php echo $langs->trans("WidgetSave") ?></button>
  <a href="widget_list.php" class="butActionDelete"><?php echo $langs->trans("WidgetCancel") ?></a>
</div>
</form>

<?php print dol_get_fiche_end(); ?>

<script>
// ── Variables panel ──────────────────────────────────────
function toggleVarPanel() {
    var p = document.getElementById('var-panel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}

function insertVar(varName) {
    // Insère la variable à la position du curseur dans le textarea actif
    // On cherche le textarea visible le plus proche (datasource ou sous-SQL)
    var ta = document.activeElement;
    if (!ta || ta.tagName !== 'TEXTAREA') {
        ta = document.getElementById('datasource');
    }
    var start = ta.selectionStart;
    var end   = ta.selectionEnd;
    var val   = ta.value;
    ta.value  = val.substring(0, start) + varName + val.substring(end);
    // Repositionner le curseur après la variable insérée
    var pos = start + varName.length;
    ta.setSelectionRange(pos, pos);
    ta.focus();
}

function showTypePanel(type) {
    document.querySelectorAll('.type-panel').forEach(p => p.classList.remove('active'));
    var panel = document.getElementById('panel-' + type);
    if (panel) panel.classList.add('active');
    // Style radio labels
    document.querySelectorAll('[name=widget_type]').forEach(function(r) {
        var lbl = r.closest('label');
        lbl.style.border = r.checked ? '3px solid ' + ({number:'#3B82F6',table:'#10B981',chart:'#F59E0B'}[r.value]||'#ccc') : '2px solid #ddd';
        lbl.style.background = r.checked ? ({number:'#3B82F622',table:'#10B98122',chart:'#F59E0B22'}[r.value]||'') : '';
    });
}

async function testSQL(sql, previewId) {
    if (!sql || !sql.trim()) return;
    var el = document.getElementById(previewId);
    if (!el) return;
    el.style.display = 'block';
    el.textContent = '<?php echo $langs->trans("WidgetTestCours")?>Test en cours...';
    try {
        var fd = new FormData();
        fd.append('action','test_sql');
        fd.append('sql', sql);
        fd.append('token','<?php echo newToken(); ?>');
        var r = await fetch('widget_edit.php<?php echo $id>0?"?id=$id":""; ?>', {method:'POST',body:fd});
        var data = await r.json();
        if (data.error) {
            el.style.color = '#f87171';
            el.textContent = '<?php echo $langs->trans("WidgetError")?>Erreur : ' + data.error;
        } else {
            el.style.color = '#86efac';
            el.textContent = 'OK - ' + data.count + ' <?php echo $langs->trans("WidgetLigneRetour")?>ligne(s) retournée(s)\nAperçu : ' + JSON.stringify(data.preview, null, 2);
        }
    } catch(e) { el.textContent = '<?php echo $langs->trans("WidgetErreurReseau")?>Erreur réseau: ' + e; }
}

function addColumn() {
    var tpl = '<div class="col-row" style="border:1px solid #e0e0e0;border-radius:6px;padding:10px;margin-bottom:8px;background:#fff">' +
        '<div class="sw-row">' +
        '<div class="sw-field"><label><?php echo $langs->trans("WidgetChampsSQL")?></label><input type="text" class="col-field" placeholder="id"></div>' +
        '<div class="sw-field"><label><?php echo $langs->trans("WidgetLibelle")?></label><input type="text" class="col-label" placeholder="ID"></div>' +
		'<div class="sw-field">' +
		'	<label>Type donnée</label>' +
		'	<select name="type" class="col-type">' +
		'	  <option value="varchar" ><label><?php echo $langs->trans("WidgetText")?></option>' +
		'	  <option value="int" 	  ><label><?php echo $langs->trans("WidgetEntier")?></option>' +
		'	  <option value="numeric" ><label><?php echo $langs->trans("WidgetNumeric")?></option>' +
		'	  <option value="price"   ><label><?php echo $langs->trans("WidgetPrix")?></option>' +
		'	  <option value="pourcent"><label><?php echo $langs->trans("WidgetPourcent")?></option>' +
		'	</select>' +
		'</div>' +		
        '<div class="sw-field"><label><?php echo $langs->trans("WidgetLien")?></label><input type="text" class="col-link" placeholder="/dolibarr/commande/card.php?id={id}"></div>' +
        '</div><div class="sw-row" style="margin-top:6px">' +
        '<div class="sw-field"><label<?php echo $langs->trans("WidgetClassCSS")?>>Classe CSS</label><input type="text" class="col-class" placeholder=""></div>' +
        '<div class="sw-field"><label><?php echo $langs->trans("WidgetBadesJSON")?>Badges JSON</label><input type="text" class="col-badge" placeholder="{}"></div>' +
        '<button type="button" onclick="this.closest(\'.col-row\').remove()" style="background:#ef4444;color:#fff;border:none;border-radius:4px;padding:5px 10px;cursor:pointer;align-self:flex-end">✕</button>' +
        '</div></div>';
    document.getElementById('columns-builder').insertAdjacentHTML('beforeend', tpl);
}

document.getElementById('swForm').addEventListener('submit', function() {
    var cols = [];
    document.querySelectorAll('.col-row').forEach(function(row) {
        cols.push({
            field: row.querySelector('.col-field').value,
            label: row.querySelector('.col-label').value,
			type: row.querySelector('.col-type').value,
            link:  row.querySelector('.col-link').value,
            class: row.querySelector('.col-class').value,
            badge: row.querySelector('.col-badge').value
        });
    });
    document.getElementById('opt_columns').value = JSON.stringify(cols);
});
</script>
<?php llxFooter(); $db->close(); ?>
