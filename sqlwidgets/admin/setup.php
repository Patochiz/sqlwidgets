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

/** Page de configuration du module SQL Widgets */
if (!$res) { print "Include fails"; exit; }
$langs->loadLangs(array("sqlwidgets@sqlwidgets","admin"));
if (!$user->admin) accessforbidden();

llxHeader('', $langs->trans("ConfigSQLWG"));
print load_fiche_titre($langs->trans("SQLWGConfig"), '', 'stats');
print '<div class="info">';
print '<p>Le module <strong>'.$langs->trans("SQLWidgets").'</strong> est actif.</p>';
print '<ul>';
print '<li><a href="widget_list.php">'.$langs->trans("WidgetGestion").'</a></li>';
print '<li><a href="widget_access.php">'.$langs->trans("WidgetDroits").'</a></li>';
print '</ul>';
print '</div>';
llxFooter(); $db->close();
