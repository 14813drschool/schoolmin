<?php
/**
 * student/layout.php
 * ระบบนักเรียน (Student System) — โครงหน้า: แถบเมนูด้านบน แบนเนอร์ INFORMATION STUDENT การ์ด และกล่องป๊อปอัป
 * เมนูและรายการเหมือนระบบนักเรียน CRMS แต่ข้อมูลทั้งหมดมาจากระบบสารสนเทศของโรงเรียน (mis)
 */
defined('MIS') or exit;

function st_school(): string
{
    return (string) (cfg('school_name') ?: 'โรงเรียนดรุณราษฎร์วิทยา');
}

function st_school_address(): string
{
    return function_exists('school_address') ? (string) school_address() : '';
}

/* ================================================================ CSS / JS */

function st_css(): string
{
    // ลายรังผึ้งของแบนเนอร์ (วาดเอง)
    $hex = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="112" height="194" viewBox="0 0 112 194"><g fill="none" stroke="#9fd8ee" stroke-width="2" opacity=".55">'
        .'<path d="M56 2l52 30v60l-52 30-52-30V32z"/><path d="M56 99l52 30v60l-52 30-52-30v-60z" opacity=".6"/></g><path d="M56 22l34 20v40l-34 20-34-20V42z" fill="#ffffff" opacity=".35"/></svg>');
    return '<style>
:root{--nav:#343a40;--teal:#5f9ea0;--teal2:#4e8a8c;--bg:#e9ecef;--line:#dee2e6;--txt:#212529;--mut:#6c757d;--blue:#007bff;--info:#17a2b8;--ok:#28a745;--bad:#dc3545;--warn:#ffc107}
@font-face{font-family:"Niramit";font-style:normal;font-weight:400;font-display:swap;src:url(/mis/root/fonts/niramit/niramit-thai-400-normal.woff2) format("woff2");unicode-range:U+0E01-0E5B,U+200C-200D,U+25CC}
@font-face{font-family:"Niramit";font-style:normal;font-weight:400;font-display:swap;src:url(/mis/root/fonts/niramit/niramit-latin-400-normal.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:"Niramit";font-style:normal;font-weight:500;font-display:swap;src:url(/mis/root/fonts/niramit/niramit-thai-500-normal.woff2) format("woff2");unicode-range:U+0E01-0E5B,U+200C-200D,U+25CC}
@font-face{font-family:"Niramit";font-style:normal;font-weight:500;font-display:swap;src:url(/mis/root/fonts/niramit/niramit-latin-500-normal.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:"Niramit";font-style:normal;font-weight:700;font-display:swap;src:url(/mis/root/fonts/niramit/niramit-thai-700-normal.woff2) format("woff2");unicode-range:U+0E01-0E5B,U+200C-200D,U+25CC}
@font-face{font-family:"Niramit";font-style:normal;font-weight:700;font-display:swap;src:url(/mis/root/fonts/niramit/niramit-latin-700-normal.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
*{box-sizing:border-box}html,body{margin:0}body{background:#fff;color:var(--txt);font:16px/1.5 "Niramit",sans-serif}
a{color:var(--blue);text-decoration:none}
.nav{position:fixed;top:0;left:0;right:0;z-index:50;background:transparent;padding:8px 0;transition:background-color .3s}.nav.dark,.nav.mopen{background:#343a40}
.nav .nin{max-width:1140px;margin:0 auto;padding:0 15px;display:flex;align-items:center;justify-content:space-between;min-height:40px}
.nav .brand{color:rgba(0,0,0,.9);font-weight:400;font-size:20px;line-height:30px;padding:5px 0;white-space:nowrap}.nav.dark .brand,.nav.mopen .brand{color:#fff}
.nav ul{list-style:none;margin:0;padding:0;display:flex;align-items:center}
.nav li{position:relative}
.nav li>a,.nav li>button{display:block;padding:8px;color:rgba(0,0,0,.5);font:inherit;font-size:16px;line-height:24px;background:none;border:0;cursor:pointer;white-space:nowrap}.nav.dark li>a,.nav.dark li>button,.nav.mopen li>a,.nav.mopen li>button{color:rgba(255,255,255,.5)}
.nav li>a:hover,.nav li>button:hover,.nav li.open>button{color:rgba(0,0,0,.7)}.nav.dark li>a:hover,.nav.dark li>button:hover,.nav.dark li.open>button,.nav.mopen li>a:hover,.nav.mopen li>button:hover{color:rgba(255,255,255,.75)}
.nav li>button:after{content:"";display:inline-block;margin-left:5px;vertical-align:3px;border-top:5px solid;border-left:4px solid transparent;border-right:4px solid transparent}
.nav i.fa{margin-right:3px}
.dd{display:none;position:absolute;right:0;top:100%;min-width:250px;background:#fff;border:1px solid rgba(0,0,0,.15);border-radius:4px;padding:6px 0;box-shadow:0 6px 18px rgba(0,0,0,.18)}
.nav li.open .dd{display:block}
.dd a{display:block;padding:6px 20px;color:var(--txt);white-space:nowrap}.dd a:hover,.dd a.on{background:#f1f3f5;color:#16181b}.dd hr{border:0;border-top:1px solid #e9ecef;margin:6px 0}
.tog{display:none;background:none;border:1px solid rgba(0,0,0,.1);border-radius:4px;color:rgba(0,0,0,.5);font-size:20px;padding:3px 10px;cursor:pointer}.nav.dark .tog,.nav.mopen .tog{border-color:rgba(255,255,255,.1);color:rgba(255,255,255,.5)}
.hero{height:202px;background:#eaf6fd url(/mis/images/bg2.png) center 62%/cover no-repeat;padding:80px 15px 0;text-align:center}
.hero h1{margin:0 auto;font:700 40px/48px "Niramit",sans-serif;letter-spacing:8px;text-transform:uppercase;color:#212529}.hero hr{max-width:888px;margin:22px auto 0;border:0;border-top:2px solid #09f}
.wrap{max-width:1140px;margin:6px auto 40px;padding:0 15px}
.box{background:var(--bg);padding:20px;border-radius:2px}
.card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:4px;padding:16px 18px;margin-bottom:18px}
.card:last-child{margin-bottom:0}
.card h4,.ttl{margin:0 0 12px;font-size:24px;line-height:1.2;font-weight:500}.card h4 i,.ttl i{margin-right:6px}
.row{display:grid;gap:18px;margin-bottom:18px}.row:last-child{margin-bottom:0}.row>.card{margin:0}.r2{grid-template-columns:1fr 1fr}.rside{grid-template-columns:200px 1fr}
.kv{display:grid;grid-template-columns:1fr 1fr;gap:9px 24px;margin:14px 0 12px;padding-left:24px;font-size:16px;line-height:24px}.kv div b{font-weight:700}
.kv1{grid-template-columns:1fr}
.photo{text-align:center}.photo img{width:150px;height:180px;object-fit:contain;border:1px solid var(--line);border-radius:4px;padding:4px;background:#fff}
.btn{display:inline-block;border:1px solid transparent;border-radius:4px;padding:6px 14px;font:inherit;font-size:14px;line-height:1.4;cursor:pointer;color:#fff;background:var(--blue);text-align:center}
.btn.sm{padding:4px 8px;font-size:14px;line-height:1.5;border-radius:3.2px}.btn.info{background:var(--info)}.btn.ok{background:var(--ok)}.btn.bad{background:var(--bad)}.btn.warn{background:var(--warn);color:#212529}.btn.gray{background:#6c757d}.btn.lt{background:#f8f9fa;color:#212529;border-color:#ddd}
.btn:hover{filter:brightness(.93)}.btn[disabled]{opacity:.55;cursor:default}
table.tb{width:100%;border-collapse:collapse;font-size:15px;background:#fff}
table.tb th{background:var(--teal);color:#fff;font-weight:700;padding:7px 8px;border:1px solid #fff;text-align:center}
table.tb td{padding:7px 8px;border:1px solid var(--line);vertical-align:middle}
table.tb td.c{text-align:center}table.tb td.r{text-align:right}table.tb tr.cap th{background:var(--teal2);font-size:15px}
table.tb.st tbody tr:nth-child(odd) td{background:#f7f9fa}
.msg{padding:12px 16px;border-radius:4px;margin:0 0 14px}.msg.ok{background:#d4edda;color:#155724}.msg.no{background:#f8d7da;color:#721c24}.msg.wa{background:#fff3cd;color:#856404}.msg.in{background:#d1ecf1;color:#0c5460}.msg.ok2{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.empty{text-align:center;font-size:18px;padding:30px 10px}
.mut{color:var(--mut)}.c-ok{color:#1e8449}.c-risk{color:#e67e22}.c-bad{color:#c0392b}.lv{font-weight:700}
.in,select.in,textarea.in{display:block;width:100%;height:36px;padding:5px 10px;border:1px solid #ced4da;border-radius:4px;font:inherit;font-size:14px;background:#fff}
textarea.in{height:76px}.in:focus{outline:none;border-color:#80bdff;box-shadow:0 0 0 3px rgba(0,123,255,.18)}
label.l{display:block;font-size:13px;color:#495057;margin:6px 0 3px}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:4px 14px}.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:4px 14px}.g2{display:grid;grid-template-columns:1fr 1fr;gap:4px 14px}.s2{grid-column:span 2}.s4{grid-column:1/-1}
.grp{display:flex}.grp span{display:flex;align-items:center;padding:0 10px;border:1px solid #ced4da;border-right:0;border-radius:4px 0 0 4px;background:#e9ecef;font-size:12px;white-space:nowrap}.grp .in{border-radius:0 4px 4px 0}
.q{padding:8px 0;border-bottom:1px dashed #ddd}.q .tx{margin-bottom:4px}.q label{margin-right:16px;white-space:nowrap;display:inline-block}
.rad td.c input{width:18px;height:18px;accent-color:var(--blue)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.who{display:none}.back{display:inline-block;margin:0 0 10px}
/* ป๊อปอัป */
.mo{display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.5);overflow:auto;padding:28px 12px}
.mo.on{display:block}.mo .dlg{background:#fff;border-radius:6px;max-width:800px;margin:0 auto;box-shadow:0 10px 40px rgba(0,0,0,.3)}
.mo .hd{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--line);font-size:19px}
.mo .hd.blue{background:var(--blue);color:#fff;border-radius:6px 6px 0 0}.mo .hd .x{background:none;border:0;font-size:24px;line-height:1;cursor:pointer;color:inherit;opacity:.7}
.mo .bd{padding:16px}.mo .ft{padding:12px 16px;border-top:1px solid var(--line);text-align:right}.mo .ft .btn{margin-left:6px}
.mo .dlg.lg{max-width:1100px}.mo .dlg.sm{max-width:560px}
/* ตารางสอน */
table.tt td{height:74px;text-align:center;font-size:13px;vertical-align:middle}table.tt td.day{color:#fff;font-weight:700;width:90px}table.tt .code{color:#138496;font-weight:700}
/* หน้าเข้าสู่ระบบ */
.lg-bg{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 12px;background:#eaf6fd url(/mis/images/bg2.png) center/cover no-repeat}
.lgbox{display:grid;grid-template-columns:1fr 1fr;width:880px;max-width:100%;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 15px 50px rgba(0,0,0,.25)}
.lgbox .l1{background:linear-gradient(180deg,rgba(214,240,251,.95),rgba(255,255,255,.96)),url("data:image/svg+xml,'.$hex.'");background-size:auto,112px 194px;padding:40px 30px;text-align:center}
.lgbox .l1 img{width:130px;height:130px;object-fit:contain}.lgbox .l1 h3{font-size:30px;margin:10px 0 6px;font-weight:700;letter-spacing:2px}.lgbox .l1 p{margin:0;color:#444;font-size:14px}
.lgbox .l2{padding:46px 40px}.lgbox .l2 h3{font-size:24px;margin:0 0 6px;text-align:center}.lgbox .l2 .sub{text-align:center;color:var(--mut);font-size:13px;margin-bottom:20px}
.lgbox .l2 .in{height:44px;margin-bottom:14px}.lgbox .l2 .btn{width:100%;height:44px;font-size:16px}
.lgbox .help{font-size:13px;color:var(--mut);margin-top:16px}.lgbox table.tb{margin-top:14px;font-size:12px}
/* หน้า Loading (แบบต้นฉบับ: พื้นดำโปร่ง + แท่งคลื่น 5 แท่ง) */
.ldr{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center}.ldr.on{display:flex}
.ldr .bg{position:absolute;inset:0;background:#000;opacity:.5}.ldr .ib{position:relative;z-index:2;text-align:center;color:#fff;font-size:16px}
.sk-wave{width:62.5px;height:50px;margin:0 auto 30px;font-size:10px}.sk-wave div{background:#fff;height:100%;width:6px;margin:0 1px;display:inline-block;animation:skw 1.2s infinite ease-in-out}
.sk-wave div:nth-child(2){animation-delay:-1.1s}.sk-wave div:nth-child(3){animation-delay:-1s}.sk-wave div:nth-child(4){animation-delay:-.9s}.sk-wave div:nth-child(5){animation-delay:-.8s}.sk-wave div:nth-child(1){animation-delay:-1.2s}
@keyframes skw{0%,40%,100%{transform:scaleY(.4)}20%{transform:scaleY(1)}}
@media (prefers-reduced-motion:reduce){.sk-wave div{animation:none}}
@media (max-width:860px){.nav ul{display:none;position:absolute;left:0;right:0;top:56px;flex-direction:column;align-items:stretch;background:#343a40;padding:6px 0}.nav ul.show{display:flex}.tog{display:block}
 .dd{position:static;box-shadow:none;border:0;border-radius:0;margin:0 0 6px}.nav li>a,.nav li>button{padding:9px 18px;text-align:left;width:100%}
 .r2,.rside,.lgbox{grid-template-columns:1fr}.g4,.g3{grid-template-columns:1fr 1fr}.kv{grid-template-columns:1fr}.hero h1{font-size:22px;letter-spacing:4px;padding:0 10px 10px}.tbw{overflow-x:auto}}
@media print{.nav,.hero,.noprint,.ldr{display:none!important}.box{background:none;padding:0}.wrap{margin:0}}
</style>';
}

/** หน้า Loading: แสดงตอนเปิดหน้า และตอนกดเมนู/ส่งฟอร์ม จนกว่าหน้าใหม่จะโหลดเสร็จ */
function st_loader(): string
{
    return '<div class="ldr on" id="ldr" role="status" aria-live="polite"><div class="bg"></div><div class="ib"><div class="sk-wave"><div></div> <div></div> <div></div> <div></div> <div></div></div>Loading...</div></div>';
}

function st_js(): string
{
    return '<script>
(function(){
 var L=document.getElementById("ldr");
 function hide(){if(L)L.classList.remove("on");}
 function show(){if(L)L.classList.add("on");}
 if(document.readyState==="complete"){hide();}else{window.addEventListener("load",hide);setTimeout(hide,8000);}
 window.addEventListener("pageshow",hide);
 var N=document.querySelector(".nav");function nv(){if(N)N.classList.toggle("dark",window.pageYOffset>50);}nv();window.addEventListener("scroll",nv,{passive:true});
 document.addEventListener("submit",function(e){if(!e.defaultPrevented&&e.target.checkValidity&&e.target.checkValidity()){show();}});
 document.addEventListener("click",function(e){
  var a=e.target.closest("a[href]");
  if(a&&!e.defaultPrevented&&!e.ctrlKey&&!e.metaKey&&!e.shiftKey&&a.target!=="_blank"&&!a.hasAttribute("data-mo")&&!a.hasAttribute("download")){var h=a.getAttribute("href");if(h&&h.charAt(0)!=="#"&&h.indexOf("javascript:")!==0&&a.origin===location.origin){show();}}
  var b=e.target.closest(".nav li>button");
  document.querySelectorAll(".nav li.open").forEach(function(li){if(!b||li!==b.parentNode)li.classList.remove("open");});
  if(b){b.parentNode.classList.toggle("open");e.preventDefault();}
  var t=e.target.closest(".tog");if(t){document.querySelector(".nav ul").classList.toggle("show");document.querySelector(".nav").classList.toggle("mopen");}
  var o=e.target.closest("[data-mo]");if(o){var m=document.getElementById(o.getAttribute("data-mo"));if(m){m.classList.add("on");document.body.style.overflow="hidden";}e.preventDefault();}
  if(e.target.closest("[data-x]")||e.target.classList.contains("mo")){var mm=e.target.closest(".mo");if(mm){mm.classList.remove("on");document.body.style.overflow="";}}
 });
 document.addEventListener("keydown",function(e){if(e.key==="Escape"){document.querySelectorAll(".mo.on").forEach(function(m){m.classList.remove("on");});document.body.style.overflow="";}});
 var h=location.hash.replace("#","");if(h&&document.getElementById(h)&&document.getElementById(h).classList.contains("mo")){document.getElementById(h).classList.add("on");}
})();
</script>';
}

/** กล่องป๊อปอัป */
function st_modal(string $id, string $title, string $body, string $foot = '', string $size = '', string $icon = '', bool $blue = false): string
{
    return '<div class="mo" id="'.$id.'"><div class="dlg '.$size.'"><div class="hd'.($blue ? ' blue' : '').'"><span>'.($icon ? '<i class="fa '.$icon.'"></i> ' : '').$title.'</span><button type="button" class="x" data-x="1" aria-label="ปิด">&times;</button></div>'
        .'<div class="bd">'.$body.'</div>'.($foot !== '' ? '<div class="ft">'.$foot.'</div>' : '').'</div></div>';
}

function st_card(string $title, string $icon, string $body): string
{
    return '<div class="card"><h4>'.($icon ? '<i class="fa '.$icon.'"></i>' : '').h($title).'</h4>'.$body.'</div>';
}

function st_doc(string $title, string $body): string
{
    return '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'.h($title).'</title>'
        .'<link rel="shortcut icon" href="/mis/images/favicon.ico"><link rel="stylesheet" href="/mis/root/css/font-awesome.css">'.st_css().'</head><body>'.st_loader().$body.st_js().'</body></html>';
}

/* ================================================================ หน้าเข้าสู่ระบบ */

function st_login_page(string $err, bool $loggedOther = false): void
{
    mis_headers();
    $terms = '';
    foreach (array_slice(array_reverse(md_rows('calendar')), 0, 3) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $terms .= '<tr><td class="c">'.h(implode('/', array_reverse(explode('/', (string) $r['r1'])))).'</td><td class="c">'.(!empty($d['grade_date']) ? th_date($d['grade_date'], ' ') : '-').'</td><td class="c">'.(!empty($d['fail_date']) ? th_date($d['fail_date'], ' ') : '-').'</td></tr>';
    }
    $phone = (string) cfg('phone');
    $body = '<div class="lg-bg"><div class="lgbox"><div class="l1"><img src="'.h(school_logo_abs()).'" alt="logo"><h3>Welcome</h3><p><b>'.h(st_school()).'</b><br>'.h(st_school_address()).($phone !== '' ? '<br>โทร. '.h($phone) : '').'</p></div>'
        .'<div class="l2"><form method="post" action="./"><h3>Student System Login</h3><div class="sub">ระบบนักเรียน '.h(st_school()).'</div>'
        .($err !== '' ? '<div class="msg no">'.h($err).($loggedOther ? ' (<a href="?p=logout">ออกจากระบบบัญชีนี้</a>)' : '').'</div>' : '')
        .'<input type="hidden" name="login" value="1"><input class="in" name="username" inputmode="numeric" placeholder="รหัสประจำตัวนักเรียน *" autocomplete="username" required>'
        .'<input class="in" type="password" name="password" placeholder="รหัสบัตรประชาชนนักเรียน *" autocomplete="current-password" required>'
        .'<button class="btn" type="submit"><i class="fa fa-sign-in"></i> Enter Login</button></form>'
        .'<div class="help">ชื่อผู้ใช้ = เลขประจำตัวนักเรียน, รหัสผ่าน = เลขประจำตัวประชาชน 13 หลัก<br><a href="/mis/"><i class="fa fa-home"></i> กลับหน้าระบบสารสนเทศ</a></div>'
        .($terms ? '<table class="tb"><tr><th>ภาคเรียน</th><th>ประกาศผลการเรียน</th><th>ประกาศผลสอบไม่ผ่าน</th></tr>'.$terms.'</table>' : '')
        .'</div></div></div>';
    echo st_doc('Student System | '.st_school(), $body);
}

/* ================================================================ หน้าหลังเข้าสู่ระบบ */

function st_nav(string $cur): string
{
    $off = st_menu_off();
    $o = '<li><a href="./"><i class="fa fa-book"></i> หน้าหลัก</a></li>';
    foreach (ST_MENU as $g => [$icon, $items]) {
        $links = '';
        $prevSep = true;
        foreach ($items as $k => $l) {
            if ($l === '-') {
                if (!$prevSep) {
                    $links .= '<hr>';
                    $prevSep = true;
                }
                continue;
            }
            if (in_array($k, $off, true)) {
                continue;
            }
            $links .= '<a href="?p='.$k.'"'.($k === $cur ? ' class="on"' : '').'>'.h($l).'</a>';
            $prevSep = false;
        }
        if ($links !== '') {
            $o .= '<li><button type="button"><i class="fa '.$icon.'"></i> '.h($g).'</button><div class="dd">'.preg_replace('#<hr>$#', '', $links).'</div></li>';
        }
    }
    $o .= '<li><a href="?p=logout"><i class="fa fa-power-off"></i> Logout</a></li>';
    return '<nav class="nav"><div class="nin"><a class="brand" href="./">DRW-MIS</a><button type="button" class="tog" aria-label="เมนู"><i class="fa fa-bars"></i></button><ul>'.$o.'</ul></div></nav>';
}

function st_layout(string $p, string $title, string $body, array $S): void
{
    mis_headers();
    $inner = $title === '' ? $body : '<div class="card"><h4 class="ttl"><i class="fa fa-filter"></i>'.h($title).'</h4>'.$body.'</div>';
    echo st_doc(($title ?: 'Information Student').' | Student System', st_nav($p).'<header class="hero"><h1>Information Student</h1><hr></header><main class="wrap"><div class="box">'.$inner.'</div></main>');
}
