<?php
/**
 * Hook actions SQL Widgets
 * Fichier : /sqlwidgets/class/actions_sqlwidgets.class.php
 *
 * Implémente UNIQUEMENT le hook addOpenElementsDashboardGroup
 * pour les widgets de type 'number' (chiffres).
 * Les widgets 'table' et 'chart' sont gérés par leurs propres boxes
 * (box_sqlwidget_ID.php) avec les boutons natifs Dolibarr.
 */

require_once 'sqlwidget.class.php';

class ActionsSqlWidgets
{
    public $db;
    public $error   = '';
    public $results = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook addOpenElementsDashboardGroup
     *
     * Injecte les pavés de chiffres (widget_type = 'number') dans le
     * dashboard natif Dolibarr (page d'accueil index.php).
     * Les widgets table/chart ont leurs propres boxes avec boutons natifs.
     *
     * @return int  0 = pas de contenu, 1 = contenu injecté, -1 = erreur
     */
    public function addOpenElementsDashboardGroup($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        // Ce hook ne s'exécute que sur la page d'accueil
        if (!in_array('index', explode(':', $parameters['context']))) {
            return 0;
        }

        $langs->loadLangs(array('sqlwidgets@sqlwidgets'));

        // Uniquement les widgets de type 'number' (le paramètre true filtre sur number)
        $obj         = new SqlWidget($this->db);
        $num_widgets = $obj->fetchAllForUser($user, true);

        if (empty($num_widgets)) {
            return 0;
        }

        $dashboardlines = isset($hookmanager->resArray['dashboardlines'])
            ? $hookmanager->resArray['dashboardlines']
            : array();

        foreach ($num_widgets as $w) {
            $opts = is_array($w->type_options) ? $w->type_options : array();

            // Valeur principale
            $main_rows = $w->executeQuery();
            if (isset($main_rows['error']) || empty($main_rows)) {
                $main_value = 'N/A';
            } else {
                $main_value = array_values((array)$main_rows[0])[0];
                if (is_numeric($main_value)) {
                    $main_value = number_format((float)$main_value, 0, ',', ' ');
                }
            }

            $prefix = !empty($opts['prefix']) ? htmlspecialchars($opts['prefix'], ENT_QUOTES) : '';
            $suffix = !empty($opts['suffix']) ? htmlspecialchars($opts['suffix'], ENT_QUOTES) : '';

            // Sous-chiffre 1
            $sub1_value = ''; $sub1_label = '';
            if (!empty($opts['sub1_sql'])) {
                $sub1_rows = $w->executeQuery($opts['sub1_sql']);
                if (!isset($sub1_rows['error']) && !empty($sub1_rows)) {
                    $v = array_values((array)$sub1_rows[0])[0];
                    $sub1_value = is_numeric($v) ? number_format((float)$v, 0, ',', ' ') : $v;
                    $sub1_label = !empty($opts['sub1_label']) ? $opts['sub1_label'] : '';
                }
            }

            // Sous-chiffre 2
            $sub2_value = ''; $sub2_label = '';
            if (!empty($opts['sub2_sql'])) {
                $sub2_rows = $w->executeQuery($opts['sub2_sql']);
                if (!isset($sub2_rows['error']) && !empty($sub2_rows)) {
                    $v = array_values((array)$sub2_rows[0])[0];
                    $sub2_value = is_numeric($v) ? number_format((float)$v, 0, ',', ' ') : $v;
                    $sub2_label = !empty($opts['sub2_label']) ? $opts['sub2_label'] : '';
                }
            }

            $icon      = !empty($opts['icon']) ? $opts['icon'] : 'stats';
            $icon_html = $this->renderIcon($icon, '#ffffff');

            $color_bg  = !empty($w->color_bg)       ? $w->color_bg       : '#1E293B';
            $color_pri = !empty($w->color_primary)   ? $w->color_primary   : '#3B82F6';
            $color_sec = !empty($w->color_secondary) ? $w->color_secondary : '#10B981';
            $color_acc = !empty($w->color_accent)    ? $w->color_accent    : '#F59E0B';
            $color_txt = !empty($w->color_text)      ? $w->color_text      : '#FFFFFF';

			$module_root_path = dirname(__DIR__, 2);
			$module_rel = str_replace(DOL_DOCUMENT_ROOT,'',$module_root_path);
			$ajax_url = DOL_URL_ROOT . $module_rel . '/admin/widget_list.php';

            $link_url = !empty($opts['link_url']) ? $opts['link_url']: $ajax_url;

            // Construction du HTML du pavé
            $html  = '<div class="boxstatsindicator thumbstat noborderoncenter" style="';
            $html .= 'background:'.$color_bg.';color:'.$color_txt.';';
            $html .= 'border-radius:10px;overflow:hidden;margin:4px;min-width:160px;';
            $html .= 'box-shadow:0 2px 10px rgba(0,0,0,.2);display:inline-block;vertical-align:top">';

            // En-tête
            $html .= '<div style="background:'.$color_pri.';padding:8px 12px;display:flex;align-items:center;gap:8px">';
            $html .= '<span style="font-size:18px">'.$icon_html.'</span>';
            $html .= '<span style="font-weight:700;font-size:12px;color:#fff">'.htmlspecialchars($w->label, ENT_QUOTES).'</span>';
            $html .= '</div>';

            // Corps
            $html .= '<div style="padding:12px 16px;text-align:center">';
            $html .= '<div style="font-size:38px;font-weight:800;line-height:1;color:'.$color_pri.'">';
            $html .= $prefix.htmlspecialchars($main_value, ENT_QUOTES).$suffix;
            $html .= '</div>';

            if ($sub1_value !== '' || $sub2_value !== '') {
                $html .= '<div style="display:flex;justify-content:center;gap:16px;margin-top:8px;';
                $html .= 'padding-top:8px;border-top:1px solid rgba(255,255,255,.15)">';
                if ($sub1_value !== '') {
                    $html .= '<div style="text-align:center">';
                    $html .= '<div style="font-size:16px;font-weight:700;color:'.$color_sec.'">'.$prefix.htmlspecialchars($sub1_value, ENT_QUOTES).$suffix.'</div>';
                    $html .= '<div style="font-size:10px;opacity:.65;text-transform:uppercase">'.htmlspecialchars($sub1_label, ENT_QUOTES).'</div>';
                    $html .= '</div>';
                }
                if ($sub2_value !== '') {
                    $html .= '<div style="text-align:center">';
                    $html .= '<div style="font-size:16px;font-weight:700;color:'.$color_acc.'">'.$prefix.htmlspecialchars($sub2_value, ENT_QUOTES).$suffix.'</div>';
                    $html .= '<div style="font-size:10px;opacity:.65;text-transform:uppercase">'.htmlspecialchars($sub2_label, ENT_QUOTES).'</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }

            $html .= '</div>'; // corps
            $html .= '</div>'; // boxstatsindicator
print $html;
            $dashboardlines[] = array(
                'label'              => htmlspecialchars($w->label, ENT_QUOTES),
                'labelshort'         => htmlspecialchars($w->label, ENT_QUOTES),
                'url'                => $link_url,
                'img'                => $icon,
                'nbtodoleft'         => $prefix.$main_value.$suffix,
                'nbtodoleftwarning'  => 0,
                'module'             => 'sqlwidgets',
            );
        }

        $hookmanager->resArray['dashboardlines'] = $dashboardlines;
        return 1;
    }

    private function renderIcon($icon, $color = '#ffffff')
    {
        $icon = trim($icon);
        if (empty($icon)) {
            $icon = 'fa-chart-bar';
        }

        // --- Emoji (ex: 📦 📊 💰) ---
        // Un emoji = 1-2 caractères non-ASCII
        if (mb_strlen($icon) <= 2 && !preg_match('/^[a-zA-Z0-9\-_ ]+$/', $icon)) {
            return '<span style="font-size:22px;line-height:1">' . $icon . '</span>';
        }

        // --- FontAwesome (fa-chart-bar / fas fa-chart-bar / far fa-chart-bar) ---
        if (preg_match('/^fa[srb]?\s+fa-/i', $icon) || preg_match('/^fa-/i', $icon)) {
            // Si l'utilisateur écrit juste "fa-chart-bar", on préfixe "fas"
            if (preg_match('/^fa-/i', $icon)) {
                $fa_class = 'fas ' . $icon;
            } else {
                $fa_class = $icon;
            }
            $c = htmlspecialchars($color, ENT_QUOTES);
            $k = htmlspecialchars($fa_class, ENT_QUOTES);
            return '<i class="' . $k . '" style="color:' . $c . ';font-size:18px"></i>';
        }

        // --- Picto Dolibarr natif (ex: order, invoice, bill, contract...) ---
        // img_picto() retourne un <img> ou <span> selon la version de Dolibarr.
        // filter: brightness(0) invert(1) rend n'importe quel picto blanc sur fond coloré.
        if (function_exists('img_picto')) {
            $img = img_picto('', $icon, 'width="20" height="20" style="filter:brightness(0) invert(1)"');
            if (!empty($img)) {
                return '<span style="display:inline-flex;align-items:center">' . $img . '</span>';
            }
        }

        // --- Fallback : icône FA générique ---
        $c = htmlspecialchars($color, ENT_QUOTES);
        return '<i class="fas fa-chart-bar" style="color:' . $c . ';font-size:18px"></i>';
    }
}
