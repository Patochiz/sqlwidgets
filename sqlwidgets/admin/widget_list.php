<?php
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
if (!$user->rights->sqlwidgets->write) accessforbidden();
$action = GETPOST("action","aZ09");
$id = GETPOST("id","int");
$object = new SqlWidget($db);
if ($action === "delete" && $id > 0 && $user->rights->sqlwidgets->delete) {
    $object->fetch($id);
    $object->delete($user);
    header("Location: widget_list.php"); exit;
}
if ($action === "toggle" && $id > 0) {
    $object->fetch($id); $object->active = $object->active ? 0 : 1; $object->update($user);
    header("Location: widget_list.php"); exit;
}
llxHeader("", $langs->trans("GestionWidgets"));
print dol_get_fiche_head(array(), "list", $langs->trans("SQLWidgets"), 0, "stats");
print '<div align="right"> <a href="setup.php">'.$langs->trans("BackToList").'</a></div>';
$widgets = $object->fetchAll(false);
$typeStyle = array(
	"number"=>"background:#3B82F6", 
	"table"=>"background:#10B981", 
	"chart"=>"background:#F59E0B"
);
$typeName = array(
	"number"=>$langs->trans("WidgetChiffre"), 
	"table"=>$langs->trans("WidgetTableau"), 
	"chart"=>$langs->trans("WidgetGraphique")
);
print "<table class=\"noborder centpercent\">";
print "<tr class=\"liste_titre\">
	<th>".$langs->trans("WidgetRef")."</th>
	<th>".$langs->trans("WidgetLabel")."</th>
	<th>".$langs->trans("WidgetType")."</th>
	<th>".$langs->trans("WidgetPosition")."</th>
	<th>".$langs->trans("WidgetStatut")."</th>
	<th>".$langs->trans("WidgetActions")."</th>
</tr>";






foreach ($widgets as $w) {
    $s = isset($typeStyle[$w->widget_type]) ? $typeStyle[$w->widget_type] : "";
    print "<tr class=\"oddeven\">";
    print "<td><a href=\"widget_edit.php?id=".$w->id."\">".dol_escape_htmltag($w->ref)."</a></td>";
    print "<td>".dol_escape_htmltag($w->label)."</td>";
    print "<td><span style=\"".$s.";color:#fff;padding:2px 10px;border-radius:12px;font-size:11px\">".$typeName[$w->widget_type]."</span></td>";
    print "<td>".(int)$w->position."</td>";
    print "<td><a href=\"widget_list.php?action=toggle&id=".$w->id."&token=".newToken()."\">".($w->active?"<b style=\"color:#10B981\">".$langs->trans("WidgetActive")."</b>":"<span style=\"color:#999\">".$langs->trans("WidgetInactif")."</span>")."</a></td>";
    print "<td class=\"nowraponall\"><a href=\"widget_edit.php?id=".$w->id."\">".img_edit()."</a> ";
    if ($user->rights->sqlwidgets->delete) print "<a href=\"widget_list.php?action=delete&id=".$w->id."&token=".newToken()."\" onclick=\"return confirm('Confirmer la suppression ?')\">".img_delete()."</a>";
    print "</td></tr>";
}
if (empty($widgets)) print "<tr><td colspan=6 class=\"opacitymedium center\">".$langs->trans("NoWidgetFound")."</td></tr>";
print "</table>";
print "<div class=\"tabsAction\"><a href=\"widget_edit.php\" class=\"butAction\">".$langs->trans("NouveauWidget")."</a></div>";

print dol_get_fiche_end();
llxFooter(); $db->close();
