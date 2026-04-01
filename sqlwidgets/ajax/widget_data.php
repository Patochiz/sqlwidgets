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
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

header('Content-Type: application/json; charset=utf-8');

// Validation ID
$id = GETPOST('id', 'int');
if (!$id) { echo json_encode(array('error' => 'ID manquant')); exit; }

// Chargement widget
$widget = new SqlWidget($db);
if ($widget->fetch($id) <= 0) { echo json_encode(array('error' => 'Widget introuvable')); exit; }

// Vérification droits d'accès
if (!$user->admin) {
    $sql  = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."sqlwidgets_widget_access a";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."usergroup_user u ON u.fk_usergroup=a.fk_group";
    $sql .= " WHERE a.fk_widget=".(int)$id." AND u.fk_user=".(int)$user->id;
    $res2 = $db->query($sql);
    $obj  = $db->fetch_object($res2);
    if (!$obj || $obj->nb == 0) { echo json_encode(array('error' => 'Accès refusé')); exit; }
}

// Exécution requête principale
$rows = $widget->executeQuery();
if (isset($rows['error'])) { echo json_encode(array('error' => $rows['error'])); exit; }

$result = array(
    'id'    => $widget->id,
    'type'  => $widget->widget_type,
    'label' => $widget->label,
);

// ============================================================
// TYPE : number
// ============================================================
if ($widget->widget_type === 'number') {

    $opts = $widget->type_options ?: array();

    // Valeur principale : brute, le JS gère le formatage des chiffres
    $result['data']   = $rows;
    $result['prefix'] = $opts['prefix'] ?? '';
    $result['suffix'] = $opts['suffix'] ?? '';

    if (!empty($opts['sub1_sql'])) {
        $sub1 = $widget->executeQuery($opts['sub1_sql']);
        $result['sub1'] = array(
            'label' => $opts['sub1_label'] ?? '',
            'data'  => isset($sub1['error']) ? array() : $sub1,
        );
    }
    if (!empty($opts['sub2_sql'])) {
        $sub2 = $widget->executeQuery($opts['sub2_sql']);
        $result['sub2'] = array(
            'label' => $opts['sub2_label'] ?? '',
            'data'  => isset($sub2['error']) ? array() : $sub2,
        );
    }
}

// ============================================================
// TYPE : table  - formatage colonne par colonne selon "type"
// ============================================================
elseif ($widget->widget_type === 'table') {

    $opts     = $widget->type_options ?: array();
    $col_defs = $opts['columns'] ?? array();

    // Index field => type pour accès rapide
    $col_types = array();
    foreach ($col_defs as $col) {
        if (!empty($col['field']) && !empty($col['type'])) {
            $col_types[$col['field']] = $col['type'];
        }
    }

    $formatted_rows = array();
    foreach ($rows as $row) {
        $fmt_row = array();
        foreach ($row as $field => $value) {
            $type = isset($col_types[$field]) ? $col_types[$field] : 'varchar';
            $fmt_row[$field] = sw_format_value($value, $type, $langs, $conf);
        }
        $formatted_rows[] = $fmt_row;
    }

    $result['data'] = $formatted_rows;
}

// ============================================================
// TYPE : chart  - valeurs numériques pures pour Chart.js
// ============================================================
elseif ($widget->widget_type === 'chart') {

    $chart_rows = array();
    foreach ($rows as $row) {
        $keys    = array_keys($row);
        $fmt_row = array();
        foreach ($row as $field => $value) {
            if ($field === $keys[0]) {
                // Première colonne = label axe X -> string
                $fmt_row[$field] = (string)$value;
            } else {
                // Colonnes suivantes = séries -> float obligatoirement
                $fmt_row[$field] = is_numeric($value) ? (float)$value : 0.0;
            }
        }
        $chart_rows[] = $fmt_row;
    }

    $result['data'] = $chart_rows;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$db->close();

// ============================================================
// Formatage d'une valeur selon le type de colonne
// Utilise les fonctions natives Dolibarr (locale + devise)
// ============================================================

/**
 * @param mixed  $value  Valeur brute DB
 * @param string $type   varchar|int|numeric|price|pourcent
 * @param object $langs  Langs Dolibarr
 * @param object $conf   Conf Dolibarr
 * @return string        Valeur formatée
 */
function sw_format_value($value, $type, $langs, $conf)
{
    if ($value === null) return '';

    // Séparateurs locaux récupérés depuis Dolibarr
    $dec  = ($langs->trans('DecimalSeparator')  !== 'DecimalSeparator')  ? $langs->trans('DecimalSeparator')  : ',';
    $thou = ($langs->trans('ThousandSeparator') !== 'ThousandSeparator') ? $langs->trans('ThousandSeparator') : ' ';

    switch ($type) {

        case 'int':
            // Entier arrondi avec séparateur de milliers
            if (!is_numeric($value)) return (string)$value;
            return number_format((int)round((float)$value), 0, $dec, $thou);

        case 'numeric':
            // Décimal 2 chiffres avec séparateurs locaux
            if (!is_numeric($value)) return (string)$value;
            $n = (float)price2num($value, 2);
            return number_format($n, 2, $dec, $thou);

        case 'price':
            // Prix avec devise via price() - respecte monnaie et locale Dolibarr
            // Signature : price($amount, $form, $outlangs, $trunc, $rounding, $forcerounding, $currency_code)
            if (!is_numeric($value)) return (string)$value;
            $n        = (float)price2num($value, 'MT');
            $currency = !empty($conf->currency) ? $conf->currency : 'EUR';
            return price($n, 0, $langs, 1, -1, -1, $currency);

        case 'pourcent':
            // Pourcentage : 2 décimales + symbole %
            if (!is_numeric($value)) return (string)$value;
            $n = (float)price2num($value, 2);
            return number_format($n, 2, $dec, $thou).'&nbsp;%';

        case 'varchar':
        default:
            return (string)$value;
    }
}
