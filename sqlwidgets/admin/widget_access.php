<?php
/* Gestion des droits d'accès aux widgets par groupe */
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
$langs->loadLangs(array("sqlwidgets@sqlwidgets"));
if (!$user->rights->sqlwidgets->manage_access) accessforbidden();

$widget_id = GETPOST('widget_id','int');
$action    = GETPOST('action','aZ09');
$object    = new SqlWidget($db);

// Save access
if ($action === 'save_access' && $widget_id > 0) {
    $object->fetch($widget_id);
    $groups = GETPOST('groups','array');
    $object->saveAccess($groups ?: array());
    setEventMessages($langs->trans('AccessSaved'), null, 'mesgs');
    header('Location: widget_access.php?widget_id='.$widget_id); exit;
}

// Get all widgets
$widgets = $object->fetchAll(false);

// Get all user groups
$sql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."usergroup ORDER BY nom";
$res2 = $db->query($sql);
$allgroups = array();
while ($obj = $db->fetch_object($res2)) $allgroups[$obj->rowid] = $obj->nom;

// Current widget access
$current_groups = array();
$current_widget = null;
if ($widget_id > 0) {
    $current_widget = new SqlWidget($db);
    $current_widget->fetch($widget_id);
    $current_groups = $current_widget->getAccess();
}

llxHeader('', $langs->trans('AccessRights'));
print dol_get_fiche_head(array(), '', $langs->trans('SQLWidgets').' - '.$langs->trans('AccessRights'), 0, 'stats');
print '<div align="right"><a href="setup.php">'.$langs->trans("BackToList").'</a></div>';
?>
<div style="display:flex;gap:20px;flex-wrap:wrap">
<!-- Liste des widgets -->
<div style="min-width:220px;flex:1">
<h3 style="margin-top:0"><?php echo $langs->trans("SQLWidgets")?></h3>
<div style="border:1px solid #e0e0e0;border-radius:8px;overflow:hidden">
<?php foreach ($widgets as $w): ?>
<a href="widget_access.php?widget_id=<?php echo $w->id; ?>" style="display:block;padding:10px 15px;text-decoration:none;color:inherit;<?php echo $w->id==$widget_id?'background:#3B82F2;color:#fff;font-weight:bold':''; ?>;border-bottom:1px solid #e0e0e0">
  <?php echo dol_escape_htmltag($w->label); ?>
  <?php if ($w->id==$widget_id) echo ' &rarr;'; ?>
</a>
<?php endforeach; ?>
</div>
</div>

<!-- Droits d'accès du widget sélectionné -->
<div style="flex:2">
<?php if ($current_widget): ?>
<h3 style="margin-top:0"><?php echo $langs->trans("WidgetAccesPour")?> <strong><?php echo dol_escape_htmltag($current_widget->label); ?></strong></h3>
<form method="POST" action="widget_access.php?widget_id=<?php echo $widget_id; ?>">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="save_access">
<input type="hidden" name="widget_id" value="<?php echo $widget_id; ?>">

<div style="background:#f8f9fa;border-radius:8px;padding:15px;border:1px solid #e0e0e0">
<p style="margin-top:0;color:#666;font-size:13px"><?php echo $langs->trans("WidgetPhraseDroit")?></p>
<?php if (empty($allgroups)): ?>
  <p class="opacitymedium"><?php echo $langs->trans("WidgetGroupNoThis")?></p>
<?php else: ?>
  <div style="columns:2;column-gap:20px">
  <?php foreach ($allgroups as $gid=>$gname): ?>
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer">
      <input type="checkbox" name="groups[]" value="<?php echo $gid; ?>" <?php echo in_array($gid,$current_groups)?'checked':''; ?> style="width:16px;height:16px">
      <span><?php echo dol_escape_htmltag($gname); ?></span>
    </label>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
<div class="tabsAction" style="margin-top:15px">
  <button type="submit" class="butAction"><?php echo $langs->trans("WidgetGroupSave")?></button>
</div>
</form>
<?php else: ?>
<div style="text-align:center;padding:40px;color:#999">
  <div style="font-size:48px">🔐</div>
  <p><?php echo $langs->trans("WidgetSelectGroup")?></p>
</div>
<?php endif; ?>
</div>
</div>
<?php
print dol_get_fiche_end();
llxFooter(); $db->close();
?>
