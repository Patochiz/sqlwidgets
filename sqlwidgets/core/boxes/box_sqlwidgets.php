<?php
/**
 * Box SQL Widgets - Affiche les widgets SQL sur le dashboard Dolibarr principal
 * Fichier : sqlwidgets/core/boxes/box_sqlwidgets.php
 */
include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
require_once '../../class/sqlwidget.class.php';

class box_sqlwidgets extends ModeleBoxes
{
    public $boxcode  = 'box_sqlwidgets';
    public $boximg   = 'stats';
    public $boxlabel = 'SQLWidgetsDashboard';
    public $depends  = array('sqlwidgets');
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

    public function loadBox($max = 5)
    {
        global $langs;
        $this->info_box_head     = array('text' => $langs->trans('SQLWidgetsDashboard'));
        $this->info_box_contents = array(array());
    }

    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        global $user, $langs, $conf;

        $langs->loadLangs(array('sqlwidgets@sqlwidgets'));

        $obj     = new SqlWidget($this->db);
        $widgets = $obj->fetchAllForUser($user);
        if (empty($widgets)) return '';

        $module_root_path = dirname(__DIR__, 2);
		$module_rel = str_replace(DOL_DOCUMENT_ROOT,'',$module_root_path);
		$ajax_url = DOL_URL_ROOT . $module_rel . '/ajax/widget_data.php';
        $token    = newToken();

        /* ---- CSS ---- */
        $out  = '<style>';
        $out .= '.sw-grid{display:flex;flex-wrap:wrap;gap:16px;padding:10px 0;}';
        $out .= '.sw-card{border-radius:10px;overflow:hidden;box-shadow:0 2px 14px rgba(0,0,0,.18);flex:1;min-width:220px;max-width:100%;}';
        $out .= '.sw-card-full{flex-basis:100%;}';
        $out .= '.sw-card-head{padding:10px 16px;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:space-between;}';
        $out .= '.sw-card-body{padding:16px;}';
        $out .= '.sw-number-val{font-size:46px;font-weight:800;line-height:1;}';
        $out .= '.sw-number-subs{display:flex;gap:20px;margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,.15);}';
        $out .= '.sw-sub-val{font-size:20px;font-weight:700;}';
        $out .= '.sw-sub-lbl{font-size:10px;opacity:.65;text-transform:uppercase;letter-spacing:.5px;}';
        $out .= '.sw-table{width:100%;border-collapse:collapse;font-size:12px;}';
        $out .= '.sw-table th{padding:6px 10px;border-bottom:1px solid rgba(255,255,255,.2);font-size:10px;text-transform:uppercase;opacity:.75;text-align:left;}';
        $out .= '.sw-table td{padding:6px 10px;border-bottom:1px solid rgba(255,255,255,.07);}';
        $out .= '.sw-badge{display:inline-block;padding:1px 8px;border-radius:20px;font-size:11px;font-weight:600;}';
        $out .= '.sw-loading{text-align:center;padding:20px;opacity:.5;font-size:12px;}';
        $out .= '</style>';

        /* ---- Chart.js ---- */
        $out .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></sc'.'ript>';

        /* ---- Cards HTML ---- */
        $out .= '<div class="sw-grid">';
        foreach ($widgets as $w) {
            $bg      = htmlspecialchars($w->color_bg,     ENT_QUOTES);
            $fg      = htmlspecialchars($w->color_text,    ENT_QUOTES);
            $hbg     = htmlspecialchars($w->color_primary, ENT_QUOTES);
            $opts_j  = htmlspecialchars(json_encode($w->type_options ?: array()), ENT_QUOTES);
            $cols_j  = htmlspecialchars(json_encode(array(
                'primary'   => $w->color_primary,
                'secondary' => $w->color_secondary,
                'accent'    => $w->color_accent,
                'bg'        => $w->color_bg,
                'text'      => $w->color_text,
            )), ENT_QUOTES);
            $full = ($w->widget_type === 'table' || $w->widget_type === 'chart') ? ' sw-card-full' : '';

            $out .= '<div class="sw-card'.$full.'"';
            $out .= ' style="background:'.$bg.';color:'.$fg.'"';
            $out .= ' data-swid="'.(int)$w->id.'"';
            $out .= ' data-swtype="'.htmlspecialchars($w->widget_type, ENT_QUOTES).'"';
            $out .= ' data-swchart="'.htmlspecialchars($w->chart_type,  ENT_QUOTES).'"';
            $out .= " data-swopts='".$opts_j."'";
            $out .= " data-swcolors='".$cols_j."'>";
            $out .= '<div class="sw-card-head" style="background:'.$hbg.';color:#fff">';
            $out .= '<span>'.htmlspecialchars($w->label, ENT_QUOTES).'</span>';
            $out .= '<span style="font-size:10px;opacity:.8">'.strtoupper($w->widget_type).'</span>';
            $out .= '</div>';
            $out .= '<div class="sw-card-body"><div class="sw-loading">Chargement...</div></div>';
            $out .= '</div>';
        }
        $out .= '</div>';

        /* ---- JavaScript - URL et token injectés via PHP ---- */
        $js_ajax = json_encode($ajax_url);
        $js_tok  = json_encode($token);

        $out .= '<sc'.'ript>(function(){';
        $out .= 'var A='.$js_ajax.',T='.$js_tok.';';

        /* helpers */
        $out .= 'function esc(s){if(s==null)return"";return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}';
        $out .= 'function fmt(v){var n=parseFloat(v);if(isNaN(n))return String(v);if(n>=1e6)return(n/1e6).toFixed(1)+"M";return new Intl.NumberFormat("fr-FR").format(Math.round(n));}';

        /* renderNumber */
        $out .= 'function rNum(b,d,c){';
        $out .= 'var rows=d.data||[],pre=d.prefix||"",suf=d.suffix||"",val=rows.length?Object.values(rows[0])[0]:"--";';
        $out .= 'var h=\'<div class="sw-number-val" style="color:\'+c.primary+\'">\'+pre+fmt(val)+suf+"</div>";';
        $out .= 'var s="";';
        $out .= 'if(d.sub1&&d.sub1.data&&d.sub1.data.length){var v1=Object.values(d.sub1.data[0])[0];s+=\'<div class="sw-sub"><div class="sw-sub-val" style="color:\'+c.secondary+\'">\'+pre+fmt(v1)+suf+\'</div><div class="sw-sub-lbl">\'+esc(d.sub1.label||"")+"</div></div>";}';
        $out .= 'if(d.sub2&&d.sub2.data&&d.sub2.data.length){var v2=Object.values(d.sub2.data[0])[0];s+=\'<div class="sw-sub"><div class="sw-sub-val" style="color:\'+c.accent+\'">\'+pre+fmt(v2)+suf+\'</div><div class="sw-sub-lbl">\'+esc(d.sub2.label||"")+"</div></div>";}';
        $out .= 'if(s)h+=\'<div class="sw-number-subs">\'+s+"</div>";';
        $out .= 'b.innerHTML=h;}';

        /* renderTable */
        $out .= 'function rTable(b,d,c,o){';
        $out .= 'var rows=d.data||[],cols=o.columns||[];';
        $out .= 'if(!rows.length){b.innerHTML=\'<div class="sw-loading">Aucune donn\u00e9e</div>\';return;}';
        $out .= 'var keys=cols.length?cols.map(function(x){return x.field;}).filter(Boolean):Object.keys(rows[0]);';
        $out .= 'var h=\'<div style="overflow-x:auto"><table class="sw-table"><thead><tr>\';';
        $out .= 'if(cols.length)cols.forEach(function(x){h+="<th>"+esc(x.label||x.field)+"</th>";});';
        $out .= 'else keys.forEach(function(k){h+="<th>"+esc(k)+"</th>";});';
        $out .= 'h+="</tr></thead><tbody>";';
        $out .= 'rows.forEach(function(row){h+="<tr>";';
        $out .= 'if(cols.length){cols.forEach(function(x){';
        $out .= 'var raw=row[x.field]!=null?row[x.field]:"",cell=esc(raw);';
        $out .= 'if(x.badge){try{var bj=typeof x.badge==="string"?JSON.parse(x.badge):x.badge,bi=bj[String(raw)];if(bi)cell=\'<span class="sw-badge" style="background:\'+esc(bi.color||"#999")+\';color:#fff">\'+esc(bi.label||raw)+"</span>";}catch(e){}}';
        $out .= 'if(x.link){var hr=x.link.replace(/\{(\w+)\}/g,function(m,k){return encodeURIComponent(row[k]||"");});cell=\'<a href="\'+hr+\'" style="color:\'+c.primary+\'">\'+cell+"</a>";}';
        $out .= 'h+=\'<td class="\'+esc(x.class||"")+\'">\'+cell+"</td>";';
        $out .= '});}else{keys.forEach(function(k){h+="<td>"+esc(row[k])+"</td>";});}';
        $out .= 'h+="</tr>";});';
        $out .= 'h+="</tbody></table></div>";b.innerHTML=h;}';

        /* renderChart */
        $out .= 'function rChart(b,d,c,o,ct,wid){';
        $out .= 'var rows=d.data||[];';
        $out .= 'if(!rows.length){b.innerHTML=\'<div class="sw-loading">Aucune donn\u00e9e</div>\';return;}';
        $out .= 'var keys=Object.keys(rows[0]),lk=keys[0],vk=keys.slice(1);';
        $out .= 'var lbs=rows.map(function(r){return String(r[lk]);});';
        $out .= 'var pal=(o.colors&&o.colors.length)?o.colors:[c.primary,c.secondary,c.accent,"#8B5CF6","#EF4444","#14B8A6"];';
        $out .= 'b.innerHTML=\'<canvas id="swc_\'+wid+\'" style="max-height:260px"></canvas>\';';
        $out .= 'var ctx=document.getElementById("swc_"+wid).getContext("2d");';
        $out .= 'var ds=vk.map(function(k,i){return{label:k,data:rows.map(function(r){return parseFloat(r[k])||0;}),backgroundColor:ct==="line"?"transparent":(pal[i%pal.length]+(ct==="doughnut"?"":"CC")),borderColor:pal[i%pal.length],borderWidth:ct==="line"?2:1,tension:0.4,fill:false,pointBackgroundColor:pal[i%pal.length]};});';
        $out .= 'new Chart(ctx,{type:ct,data:{labels:lbs,datasets:ds},options:{responsive:true,plugins:{legend:{labels:{color:c.text}}},scales:ct==="doughnut"?{}:{x:{ticks:{color:c.text},grid:{color:"rgba(255,255,255,.08)"}},y:{ticks:{color:c.text},grid:{color:"rgba(255,255,255,.08)"}}}}});}';

        /* load + init */
        $out .= 'async function load(el){';
        $out .= 'var id=el.dataset.swid,type=el.dataset.swtype,ct=el.dataset.swchart||"bar";';
        $out .= 'var opts=JSON.parse(el.dataset.swopts||"{}"),colors=JSON.parse(el.dataset.swcolors||"{}");';
        $out .= 'var body=el.querySelector(".sw-card-body");';
        $out .= 'try{var r=await fetch(A+"?id="+id+"&token="+T),data=await r.json();';
        $out .= 'if(data.error){body.innerHTML=\'<div style="color:#f87171;padding:8px;font-size:12px">\'+esc(data.error)+"</div>";return;}';
        $out .= 'if(type==="number")rNum(body,data,colors);';
        $out .= 'else if(type==="table")rTable(body,data,colors,opts);';
        $out .= 'else if(type==="chart")rChart(body,data,colors,opts,ct,id);';
        $out .= '}catch(e){body.innerHTML=\'<div style="color:#f87171;padding:8px;font-size:12px">Erreur: \'+esc(String(e))+"</div>";}}';
        $out .= 'document.querySelectorAll("[data-swid]").forEach(load);';
        $out .= '})();</sc'.'ript>';

        if ($nooutput) return $out;
        echo $out;
        return '';
    }
}
