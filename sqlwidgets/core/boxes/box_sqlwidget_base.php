<?php
/**
 * Classe de base pour les boxes SQL Widgets dynamiques
 * Fichier : sqlwidgets/core/boxes/box_sqlwidget_base.php
 *
 * Chaque widget "table" ou "chart" a son propre fichier stub
 * box_sqlwidget_ID.php qui étend cette classe.
 *
 * parent::showBox() génère automatiquement les boutons natifs Dolibarr :
 *   fa-arrows-alt (déplacer) et fa-times (masquer/désactiver la box)
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
require_once dirname(__DIR__, 2).'/class/sqlwidget.class.php';

class box_sqlwidget_base extends ModeleBoxes
{
    /** ID du widget SQL associé - défini dans chaque classe enfant */
    public $widget_id = 0;

    public $db;
    public $info_box_head     = array();
    public $info_box_contents = array();
    public $error = '';

    public function __construct($db, $param = '')
    {
        global $langs;
        $this->db = $db;
        $langs->loadLangs(array('sqlwidgets@sqlwidgets'));
    }

    /**
     * loadBox - Charge le widget depuis la DB et remplit info_box_head/contents.
     * Appelé par showBox() avant de déléguer au parent.
     */
    public function loadBox($max = 5)
    {
        global $user, $langs;
        $langs->loadLangs(array('sqlwidgets@sqlwidgets'));

        $w = new SqlWidget($this->db);

        if ($w->fetch($this->widget_id) <= 0) {
            $this->info_box_head     = array('text' => 'Widget #'.$this->widget_id.' introuvable');
            $this->info_box_contents = array(array(array('td' => 'class="nohover"', 'text' => '')));
            return;
        }

        // Vérifier les droits d'accès (sauf admin)
        if (!$user->admin) {
            $sql  = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."sqlwidgets_widget_access a";
            $sql .= " INNER JOIN ".MAIN_DB_PREFIX."usergroup_user ugu ON ugu.fk_usergroup=a.fk_group";
            $sql .= " WHERE a.fk_widget=".(int)$this->widget_id." AND ugu.fk_user=".(int)$user->id;
            $res  = $this->db->query($sql);
            $chk  = $this->db->fetch_object($res);
            if (empty($chk->nb)) {
                $this->info_box_head     = array('text' => htmlspecialchars($w->label, ENT_QUOTES));
                $this->info_box_contents = array(array(array('td' => 'class="nohover"', 'text' => '')));
                return;
            }
        }

        // En-tête avec icône optionnelle
        $icon = '';
        if (!empty($w->type_options['icon'])) {
            $icon = $this->renderIcon($w->type_options['icon']).' ';
        }
        $this->info_box_head = array(
            'text'  => $icon.htmlspecialchars($w->label, ENT_QUOTES),
            'limit' => 0,
        );

        // Corps du widget (rendu AJAX)
        $this->info_box_contents = array(
            array(
                array(
//                    'td'   => 'class="nohover" style="padding:0;border:none"',

                    'td' => '',
                    'textnoformat' => $this->buildWidgetHtml($w),
                ),
            ),
        );
    }

    /**
     * showBox - Délègue à parent::showBox() de ModeleBoxes.
     * C'est le parent qui génère l'encadré avec les boutons fa-arrows-alt et fa-times.
     */
    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        $this->loadBox();
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }

    // =========================================================================
    // Méthodes privées
    // =========================================================================

    /**
     * Construit le HTML AJAX du widget (table ou chart)
     */
    private function buildWidgetHtml($w)
    {
		$module_root_path = dirname(__DIR__, 2);
		$module_rel = str_replace(DOL_DOCUMENT_ROOT,'',$module_root_path);
		$ajax_url = DOL_URL_ROOT . $module_rel . '/ajax/widget_data.php';
        $token    = newToken();
        $id       = (int)$w->id;

        $bg     = htmlspecialchars($w->color_bg,      ENT_QUOTES);
        $fg     = htmlspecialchars($w->color_text,     ENT_QUOTES);
        $opts_j = htmlspecialchars(json_encode($w->type_options ?: array()), ENT_QUOTES);
        $cols_j = htmlspecialchars(json_encode(array(
            'primary'   => $w->color_primary,
            'secondary' => $w->color_secondary,
            'accent'    => $w->color_accent,
            'bg'        => $w->color_bg,
            'text'      => $w->color_text,
        )), ENT_QUOTES);

        $tc = 'sw-tbl-'.$id; // classe CSS unique pour éviter les conflits

        // --- CSS ---
        $out  = '<style>';
        $out .= '#sw-w-'.$id.'{background:'.$bg.';color:'.$fg.';padding:10px;min-height:50px;}';
        $out .= '.'.$tc.'{width:100%;border-collapse:collapse;font-size:12px;}';
        $out .= '.'.$tc.' th{padding:5px 8px;font-size:10px;text-transform:uppercase;';
        $out .= 'opacity:.75;text-align:left;border-bottom:1px solid rgba(255,255,255,.2);}';
        $out .= '.'.$tc.' td{padding:5px 8px;border-bottom:1px solid rgba(255,255,255,.06);}';
        $out .= '.'.$tc.' tr:last-child td{border-bottom:none;}';
        $out .= '.sw-badge{display:inline-block;padding:1px 7px;border-radius:20px;font-size:11px;font-weight:600;}';
        $out .= '.sw-spin-'.$id.'{text-align:center;padding:16px;opacity:.5;font-size:12px;}';
        $out .= '</style>';

        // Chart.js uniquement pour les graphiques
        if ($w->widget_type === 'chart') {
            $out .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></sc'.'ript>';
        }

        // --- Conteneur ---
        $out .= '<div id="sw-w-'.$id.'"';
        $out .= ' data-swid="'.$id.'"';
        $out .= ' data-swtype="'.htmlspecialchars($w->widget_type, ENT_QUOTES).'"';
        $out .= ' data-swchart="'.htmlspecialchars($w->chart_type,  ENT_QUOTES).'"';
        $out .= " data-swopts='".$opts_j."'";
        $out .= " data-swcolors='".$cols_j."'>";
        $out .= '<div class="sw-spin-'.$id.'">Chargement...</div>';
        $out .= '</div>';

        // --- JavaScript ---
        $a = json_encode($ajax_url);
        $t = json_encode($token);

        $out .= '<sc'.'ript>(function(){';
        $out .= 'var A='.$a.',T='.$t.',el=document.getElementById("sw-w-'.$id.'");';
        $out .= 'if(!el)return;';
        $out .= 'function esc(s){if(s==null)return"";return String(s)';
        $out .= '.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}';

        // renderTable
        $out .= 'function rTbl(d,c,o){';
        $out .= 'var rows=d.data||[],cols=o.columns||[];';
        $out .= 'if(!rows.length){el.innerHTML=\'<div class="sw-spin-'.$id.'">Aucune donn\u00e9e</div>\';return;}';
        $out .= 'var keys=cols.length?cols.map(function(x){return x.field;}).filter(Boolean):Object.keys(rows[0]);';
        $out .= 'var h=\'<div style="overflow-x:auto"><table class="'.$tc.'"><thead><tr>\';';
        $out .= 'if(cols.length)cols.forEach(function(x){h+="<th>"+esc(x.label||x.field)+"</th>";});';
        $out .= 'else keys.forEach(function(k){h+="<th>"+esc(k)+"</th>";});';
        $out .= 'h+="</tr></thead><tbody>";';
        $out .= 'rows.forEach(function(row){h+="<tr>";';
        $out .= 'if(cols.length){cols.forEach(function(x){';
        $out .= 'var raw=row[x.field]!=null?row[x.field]:"",cell=esc(raw);';
        $out .= 'if(x.badge){try{var bj=typeof x.badge==="string"?JSON.parse(x.badge):x.badge,bi=bj[String(raw)];';
        $out .= 'if(bi)cell=\'<span class="sw-badge" style="background:\'+esc(bi.color||"#999")+\';color:#fff">\'+esc(bi.label||raw)+"</span>";}catch(e){}}';
        $out .= 'if(x.link){var hr=x.link.replace(/\{(\w+)\}/g,function(m,k){return encodeURIComponent(row[k]||"");});';
        $out .= 'cell=\'<a href="\'+hr+\'" style="color:\'+c.primary+\'">\'+cell+"</a>";}';
        $out .= 'h+="<td>"+cell+"</td>";});}';
        $out .= 'else{keys.forEach(function(k){h+="<td>"+esc(row[k])+"</td>";});}';
        $out .= 'h+="</tr>";});h+="</tbody></table></div>";el.innerHTML=h;}';

        // renderChart
        $out .= 'function rChart(d,c,o,ct){';
        $out .= 'var rows=d.data||[];';
        $out .= 'if(!rows.length){el.innerHTML=\'<div class="sw-spin-'.$id.'">Aucune donn\u00e9e</div>\';return;}';
        $out .= 'var keys=Object.keys(rows[0]),lk=keys[0],vk=keys.slice(1);';
        $out .= 'var lbs=rows.map(function(r){return String(r[lk]);});';
        $out .= 'var pal=(o.colors&&o.colors.length)?o.colors:[c.primary,c.secondary,c.accent,"#8B5CF6","#EF4444","#14B8A6"];';
        $out .= 'el.innerHTML=\'<canvas id="swc_'.$id.'" style="max-height:280px"></canvas>\';';
        $out .= 'var ctx=document.getElementById("swc_'.$id.'").getContext("2d");';
        $out .= 'var ds=vk.map(function(k,i){return{label:k,';
        $out .= 'data:rows.map(function(r){return parseFloat(r[k])||0;}),';
        $out .= 'backgroundColor:ct==="line"?"transparent":(pal[i%pal.length]+(ct==="doughnut"?"":"CC")),';
        $out .= 'borderColor:pal[i%pal.length],borderWidth:ct==="line"?2:1,';
        $out .= 'tension:0.4,fill:false,pointBackgroundColor:pal[i%pal.length]};});';
        $out .= 'new Chart(ctx,{type:ct,data:{labels:lbs,datasets:ds},options:{responsive:true,';
        $out .= 'plugins:{legend:{labels:{color:c.text}}},';
        $out .= 'scales:ct==="doughnut"?{}:{x:{ticks:{color:c.text},grid:{color:"rgba(255,255,255,.08)"}},';
        $out .= 'y:{ticks:{color:c.text},grid:{color:"rgba(255,255,255,.08)"}}}}});}';

        // Chargement AJAX
        $out .= 'var id=el.dataset.swid,type=el.dataset.swtype,ct=el.dataset.swchart||"bar";';
        $out .= 'var opts=JSON.parse(el.dataset.swopts||"{}"),colors=JSON.parse(el.dataset.swcolors||"{}");';
        $out .= 'fetch(A+"?id="+id+"&token="+T)';
        $out .= '.then(function(r){return r.json();})';
        $out .= '.then(function(data){';
        $out .= 'if(data.error){el.innerHTML=\'<div style="color:#f87171;padding:8px;font-size:12px">\'+esc(data.error)+"</div>";return;}';
        $out .= 'if(type==="table")rTbl(data,colors,opts);';
        $out .= 'else if(type==="chart")rChart(data,colors,opts,ct);';
        $out .= '}).catch(function(e){el.innerHTML=\'<div style="color:#f87171;padding:8px">Erreur:\'+esc(String(e))+"</div>";});';
        $out .= '})();</sc'.'ript>';

        return $out;
    }

    /**
     * Rendu d'une icône dans le titre de la box (FA, picto Dolibarr, emoji)
     */
    private function renderIcon($icon, $color = '#555')
    {
        $icon = trim($icon);
        if (empty($icon)) return '';

        // Emoji
        if (mb_strlen($icon) <= 2 && !preg_match('/^[a-zA-Z0-9\-_ ]+$/', $icon)) {
            return '<span style="font-size:13px;vertical-align:middle">'.$icon.'</span>';
        }
        // FontAwesome
        if (preg_match('/^fa[srb]?\s+fa-/i', $icon) || preg_match('/^fa-/i', $icon)) {
            $fa = preg_match('/^fa-/i', $icon) ? 'fas '.$icon : $icon;
            $c  = htmlspecialchars($color, ENT_QUOTES);
            $k  = htmlspecialchars($fa, ENT_QUOTES);
            return '<i class="'.$k.'" style="color:'.$c.';margin-right:3px"></i>';
        }
        // Picto Dolibarr
        if (function_exists('img_picto')) {
            $img = img_picto('', $icon, 'width="14" height="14" style="vertical-align:middle;margin-right:3px"');
            if (!empty($img)) return $img;
        }
        return '';
    }
}
