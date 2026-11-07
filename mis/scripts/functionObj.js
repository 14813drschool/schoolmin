function OpenHelp(hurl){
	var width = screen.width-20;
	var height = screen.height-110;
	var left = 0;
	var top = 0;
	window.open(hurl,'ช่วยเหลือ','toolbar=no, status=yes, scrollbars=yes,fullscreen=no, location=no, resizable=no, width='+width+', height='+height+', top='+top+', left='+left);
}

function OpenPrintPreview(purl){
	var width = screen.width;
	var height = screen.height;
	var left = (screen.width/2)-(width/2);
	var top = (screen.height/2)-(height/2);
	window.open(purl,'ตัวอย่างก่อนพิมพ์','menubar=yes, toolbar=no, status=yes, scrollbars=yes,fullscreen=no, location=no, resizable=no, width='+width+', height='+630+', top='+0+', left='+left);
}


function ReportPortrait(gotopath, content){
	var width = 1024;
	var height = 630;
	var left = (screen.width/2)-(width/2);
	var top = (screen.height/2)-(height/2);
	window.open(gotopath, content ,'menubar=yes, toolbar=no, status=yes, scrollbars=yes,fullscreen=no, location=no, resizable=no, width='+width+', height='+height+', top='+0+', left='+left);
}

function ReportLandscape(gotopath, content){
	var width = 1200;
	var height = screen.height-110;
	var left = (screen.width/2)-(width/2);
	var top = 0;
	window.open(gotopath, content ,'menubar=yes, toolbar=no, status=yes, scrollbars=yes,fullscreen=no, location=no, resizable=no, width='+width+', height='+height+', top='+0+', left='+left);
}

function GetLinkHeight(goto){
 	jQuery.facebox({ ajax: goto})
}

function GetLink(goto){
 	jQuery.facebox({ ajax: goto})
}

function GetToolTip(goto){
  	jQuery.facetooltip({ ajax: goto }, 'my-groovy-style')
}


function confirm_single(goto_path,c_del) {
	
	AlertBox.confirm(c_del, '', { onComplete: 
		function(returnvalue) {
			  if(returnvalue){
				location.href="index.php"+goto_path;
			  }else{
				return false;
			  }
		}
	});
	
}


function confirm_print(goto_path,c_print) {
	
	AlertBox.confirm(c_print, '', { onComplete: 
		function(returnvalue) {
			  if(returnvalue){
				location.href = "print_pdf.php"+goto_path;
			  }else{
				return false;
			  }
		}
	});
	
}

function confirm_del(msg,locations) {
		AlertBox.confirm(msg, '', { onComplete: 
			function(returnvalue) {
				  if(returnvalue){
					location.href="index.php"+locations;
				  }
			}
		});
}

var flag = true;
function handler(e_fields){

	var search_status = getCookie("currentsearch");
	var e_fieldname = e_fields;
	var eLength = document.list.elements.length;
	var chkall = document.list.checkall.checked;

	for (b=0; b<eLength; b++){
		var b_types = list.elements[b].type;
		var b_names = list.elements[b].name;

		if (b_types=="checkbox" && b_names==e_fieldname){
			var begining = b-1;
			setCookie("currentbegin",begining);
			break;
		}
	}
	
	if(search_status==null){

		var start_num = parseInt(startrecord.innerHTML)+begining;
		var end_num = parseInt(endrecord.innerHTML)+begining;

		//for(i=parseInt(start_num); i<=parseInt(end_num); i++){
		for (i=0; i<eLength; i++){
			var types = list.elements[i].type;
			var names = list.elements[i].name;
			if (types=="checkbox" && names==e_fieldname){
				list.elements[i].checked = chkall;
			}
		}

	}else{

		var start_num = parseInt(startrecord.innerHTML)+begining;
		var newdisplayrecord=new Array();
		var chkrows = getCookie("currentsize");


		for (a=0; a<start_num; a++){	
			newdisplayrecord[a] = 0;
		}
		

		for (e=0; e<eLength; e++){	
			var ep = e+start_num;
			newdisplayrecord[ep] = displayrecord[e];
		}
		
		var rows = 0;
		for (i=0; i<eLength; i++){
			if(	newdisplayrecord[i]==1){
				var types = list.elements[i].type;
				var names = list.elements[i].name;
				if (types=="checkbox" && names==e_fieldname){
					rows++;
					list.elements[i].checked = chkall;
				}
			}
			
			if(rows==chkrows){
				break;
			}
			
		}
	}

	flag = !flag;
}

function checkIt(evt) {
    evt = (evt) ? evt : window.event
    var charCode = (evt.which) ? evt.which : evt.keyCode
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false
    }
    return true
}

function doCheckVal(t, v, n, val){
	if(isFinite(v)){
		return false;
	}else{
		document.getElementById(n).value=val;
		AlertBox.alert("<br>โปรดระบุเฉพาะจำนวนเต็มหรือตัวเลขทศนิยมเท่านั้น",n);
		event.cancelBubble();
	}
}

function CheckId(t, v, n, val){
	if(isFinite(v)){
		return false;
	}else{
		document.getElementById(n).value=val;
		AlertBox.alert("<br>กรุณาระบุเป็นรหัสนักเรียนเท่านั้น",n);
		event.cancelBubble();
	}
}

function CheckCheckboxes(fname,mod,opt,func,para_msg){
	
	var variable = para_msg;
	var this_msg = "";

	
	if(typeof variable == undefined || variable == null || variable == ""){
		this_msg = "ลบ";
	}else{
		this_msg = variable;
	}
	
	var c_msg = "<br>ยืนยันการ"+this_msg+"ข้อมูล";
	var a_msg = "<br>กรุณาเลือกข้อมูลที่ต้องการ"+this_msg+"อย่างน้อย 1 รายการ";
	
	var field = fname;
    var elLength = document.list.elements.length;
	var num_checked=0;

    for (i=0; i<elLength; i++){
        var type = list.elements[i].type;
		var name = list.elements[i].name;
		
        if (type=="checkbox" && name==field){
			if(list.elements[i].checked){
				num_checked++;
			}
		}
    }//for
	
	
	
	if (num_checked>=1){
		
		AlertBox.confirm(c_msg, '', { onComplete: 
			function(returnvalue) {
				  if(returnvalue){
					document.forms.list.action="?module="+mod+"&option="+opt+"&func="+func;
					document.forms.list.submit();
				  }
			}
		});
		
	}else {
		AlertBox.alert(a_msg);
	}
	
}

function CheckSelectboxes(c_msg,a_msg,fname,mod,opt,func){

	var field = fname;
    var elLength = document.list.elements.length;
	var num_selected=0;

    for (i=0; i<elLength; i++){
		
		var type = list.elements[i].type;
		var name = list.elements[i].name;
        if (type=="checkbox" && name==field){
			if(list.elements[i].checked){
				num_selected++;
			}
		}else if(type!="checkbox" && name==field){
			if(list.elements[i].value!=""){
				num_selected++;
			}	
		}
    }//for
	
	if (num_selected>=1){
		AlertBox.confirm(c_msg, '', {onComplete: 
		function(returnvalue) { 
		  if(returnvalue){
			document.forms.list.action="?module="+mod+"&option="+opt+"&func="+func;
			document.forms.list.submit();
		  }
		}
		}); 
	}else {
		AlertBox.alert(a_msg);
	}
	
}


function PrintPdfCheckbox(fname,print_url,para_msg){
	
	var variable = para_msg;
	var this_msg = "";

	
	if(typeof variable == undefined || variable == null || variable == ""){
		this_msg = "พิมพ์";
	}else{
		this_msg = variable;
	}
	
	var c_msg = "<br>ยืนยันการ"+this_msg;
	var a_msg = "<br>กรุณาเลือกรายการที่ต้องการ"+this_msg+"อย่างน้อย 1 รายการ";
	
	var field = fname;
    var elLength = document.list.elements.length;
	var num_checked=0;

    for (i=0; i<elLength; i++){
        var type = list.elements[i].type;
		var name = list.elements[i].name;
		
        if (type=="checkbox" && name==field){
			if(list.elements[i].checked){
				num_checked++;
			}
		}
    }//for
	
	
	
	if (num_checked>=1){
			
		AlertBox.confirm(c_msg, '', { onComplete: 
			function(returnvalue) {
				  if(returnvalue){
					document.forms.list.action = "print_pdf.php"+print_url;
					document.forms.list.submit();
				  }else{
					return false;
				  }
			}
		});
		
	}else {
		AlertBox.alert(a_msg);
	}
	
}


function move_to(page,page_value){
	
	var elLength = document.list.elements.length;
    for (ck=0; ck<elLength; ck++){
		
		var ck_type = list.elements[ck].type;
		var ck_name = list.elements[ck].name;
		
		if(ck_type == "checkbox"){
			list.elements[ck].checked = false;
		}
		
	}//for (ck=0; ck<elLength; ck++){
	
	$("#table").find('tr:not(#chkrow)').removeClass("active");
	sorter.move(page,page_value)

}

function curr_size(size){
	
	var elLength = document.list.elements.length;
    for (ck=0; ck<elLength; ck++){
		
		var ck_type = list.elements[ck].type;
		var ck_name = list.elements[ck].name;
		
		if(ck_type == "checkbox"){
			list.elements[ck].checked = false;
		}
		
	}//for (ck=0; ck<elLength; ck++){
	
	$("#table").find('tr:not(#chkrow)').removeClass("active");
	
	setCookie("currentsize",size);
	sorter.size(getCookie("currentsize"));

}

function shiftFocus(ev,a) {
	var key;
	ev = ev || event;
	key = ev.keyCode;
	if (key == 13) { // Enter key
		document.add.elements[a+1].focus();
		return false;
	}
}


/* ---------------------- setcookie ---------------------- */

function setCookie(name, value, expires, path, domain, secure) {
	document.cookie= name + "=" + escape(value) +
	((expires) ? "; expires=" + expires.toGMTString() : "") +
	((path) ? "; path=" + path : "") +
	((domain) ? "; domain=" + domain : "") +
	((secure) ? "; secure" : "");
}

function getCookie(name) {
var dc = document.cookie;
var prefix = name + "=";
var begin = dc.indexOf("; " + prefix);
if (begin == -1) {
begin = dc.indexOf(prefix);
if (begin != 0) return null;
} else {
begin += 2;
}
var end = document.cookie.indexOf(";", begin);
if (end == -1) {
end = dc.length;
}
return unescape(dc.substring(begin + prefix.length, end));
}

function deleteCookie(name, path, domain) {
	if (getCookie(name)) {
		document.cookie = name + "=" +
		((path) ? "; path=" + path : "") +
		((domain) ? "; domain=" + domain : "") +
		"; expires=Thu, 01-Jan-70 00:00:01 GMT";
	}
} 

/* ----------------------  findObj ----------------------  */

function MM_findObj(n, d) { //v4.01
  var p,i,x;  if(!d) d=document; if((p=n.indexOf("?"))>0&&parent.frames.length) {
    d=parent.frames[n.substring(p+1)].document; n=n.substring(0,p);}
  if(!(x=d[n])&&d.all) x=d.all[n]; for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
  for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=MM_findObj(n,d.layers[i].document);
  if(!x && d.getElementById) x=d.getElementById(n); return x;
}
function MM_swapImage() { //v3.0
  var i,j=0,x,a=MM_swapImage.arguments; document.MM_sr=new Array; for(i=0;i<(a.length-2);i+=3)
   if ((x=MM_findObj(a[i]))!=null){document.MM_sr[j++]=x; if(!x.oSrc) x.oSrc=x.src; x.src=a[i+2];}
}
function MM_swapImages() { //v3.0
  var i,j=0,x,a=MM_swapImage.arguments; document.MM_sr=new Array; for(i=0;i<(a.length-2);i+=3)
   if ((x=MM_findObj(a[i]))!=null){document.MM_sr[j++]=x; if(!x.oSrc) x.oSrc=x.src; x.src=a[i+2];}
}
function MM_swapImgRestore() { //v3.0
  var i,x,a=document.MM_sr; for(i=0;a&&i<a.length&&(x=a[i])&&x.oSrc;i++) x.src=x.oSrc;
}
function MM_swapImgRestores() { //v3.0
  var i,x,a=document.MM_sr; for(i=0;a&&i<a.length&&(x=a[i])&&x.oSrc;i++) x.src=x.oSrc;
}
function MM_preloadImages() { //v3.0
  var d=document; if(d.images){ if(!d.MM_p) d.MM_p=new Array();
    var i,j=d.MM_p.length,a=MM_preloadImages.arguments; for(i=0; i<a.length; i++)
    if (a[i].indexOf("#")!=0){ d.MM_p[j]=new Image; d.MM_p[j++].src=a[i];}}
}

/* ---------------------- SwabImage --------------------------- */

$(document).ready(function () {
    	
//rollover swap images with rel
var img_src = "";
var new_src = "";

$(".overlay").hover(function(){
//mouseover
img_src = $(this).attr('src'); //grab original image
new_src = $(this).attr('rel'); //grab rollover image
$(this).attr('src', new_src); //swap images
$(this).attr('rel', img_src); //swap images
},
function(){
//mouse out
$(this).attr('src', img_src); //swap images
$(this).attr('rel', new_src); //swap images
});

//preload images
var cache = new Array();
//cycle through all rollover elements and add rollover img src to cache array
$(".overlay").each(function(){
var cacheImage = document.createElement('img');
cacheImage.src = $(this).attr('rel');
cache.push(cacheImage);
});
    	
});


/* ---------------------- IE radius --------------------------- */

jQuery(document).ready(function($){
    $('a, div, span, p, li, button, td, tr').each(function() {
        // check each css property, we need a border radius and filter
        if ((parseInt($(this).css('borderTopLeftRadius')) > 0 ||
            parseInt($(this).css('borderTopRightRadius')) > 0 ||
            parseInt($(this).css('borderBottomLeftRadius')) > 0 ||
            parseInt($(this).css('borderBottomRightRadius')) > 0) &&
            $(this).css('filter') != '' &&
            $(this).css('filter').match(/DXImageTransform\.Microsoft\.gradient/i) != null) {
                
            // carry over the border radius
            var s = 'border-top-left-radius: ' + parseInt($(this).css('borderTopLeftRadius')) + 'px;';
            s += 'border-top-right-radius: ' + parseInt($(this).css('borderTopRightRadius')) + 'px;';
            s += 'border-bottom-left-radius: ' + parseInt($(this).css('borderBottomLeftRadius')) + 'px;';
            s += 'border-bottom-right-radius: ' + parseInt($(this).css('borderBottomRightRadius')) + 'px;';
            
            // find the start and end colors
            var c1 = $(this).css('filter').match(/startcolorstr\=\"?\'?\#([0-9a-fA-F]{6})\'?\"?/i);
            var c2 = $(this).css('filter').match(/endcolorstr\=\"?\'?\#([0-9a-fA-F]{6})\'?\"?/i);
            if (c1 != null) { if (c1.length == 2) { c1 = c1[1]; } else { c1 = null; } }
            if (c2 != null) { if (c2.length == 2) { c2 = c2[1]; } else { c2 = null; } }
            if (c1 == null && c2 != null) { c1 = c2; }
            else if (c2 == null && c1 != null) { c2 = c1; }
            
            // form the filter rule
            var g = '';
            if (c1 != null) { var g = 'filter: progid:DXImageTransform.Microsoft.gradient(startColorStr=\'#'+c1+'\', EndColorStr=\'#'+c2+'\');'; }
            
            var id = 'ie9gradius_'+parseInt(Math.random() * 100000);
            
            // we need to remove the current filter because this is spilling outside the border radius
            // relative position is needed for proper positioning of the gradient
            $(this).css('filter', '').css('position', 'relative').css('z-index', '0')
            
            // add support for adding hover styling
            $(this).mouseenter(function() { $('#'+id).addClass('gradiusover'); }).mouseleave(function() { $('#'+id).removeClass('gradiusover'); });
            
            // we need this so that the contents show on top
            $(this).find('> *:not(ul)').css('position', 'relative');
            
            // the magic is all here
            $(this).prepend('\
            <div style="position: absolute; width: 100%; height: 100%; left: 0; top: 0; z-index:-1"> \
                <div style="'+s+' height: 100%; overflow: hidden;"> \
                    <div id="'+id+'" style="'+g+' height: 100%; width: 100%; z-index:-1"> \
                    </div></div></div>');
        }
    });
});