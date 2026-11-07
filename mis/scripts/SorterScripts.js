$(document).ready(function () {
	 /*
	 $('.tinytable thead').not('.tinaytable tr').click(function (event){
			if (event.target.type!=='checkbox'){
				$(".tinytable").find('tr:not(#chkrow)').removeClass("active");
				$('input:checkbox:not(#checkall)').prop('checked',false);
				$("#checkall").attr('checked',false);
			}
    });
	*/
	
    $("#checkall").click(function(event){
        if ($(this).is(':checked') == true) {
            $(".tinytable").find('tr:not(#chkrow)').addClass("active");
        } else  {
            $(".tinytable").find('tr:not(#chkrow)').removeClass("active");
        }
		
    });//$("#checkall")

});
	
function SelecteachCheckbox(thisrow,checked) {
	
	if(checked == true){
		
		thisrow.closest('tr').addClass('active');
		
		var flag = true;
		var eLength = document.list.elements.length;
		var search_status = getCookie("currentsearch");

		for (b=0; b<eLength; b++){
			var b_types = list.elements[b].type;
			var b_names = list.elements[b].name;
			if (b_types=="checkbox" && b_names!="checkall"){
				var begining = b-1;
				break;
			}
		}
	
		if(search_status==null){
			
			var start_num = parseInt(startrecord.innerHTML)+begining;
			var end_num = parseInt(endrecord.innerHTML)+begining;
			
			for(i=parseInt(start_num); i<=parseInt(end_num); i++){
				if(list.elements[i].checked==false){
					flag = false;
					break;
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
					if (types=="checkbox" && names!="checkall"){
						rows++;
						if(list.elements[i].checked==false){
							flag = false;
							break;
						}
					}
				}
				
				if(rows==chkrows){ break; }
				
			}
	
		}//if(search_status==null){

		$("#checkall").attr('checked',flag);
		
	}else{
		
		thisrow.closest('tr').removeClass('active');
		$("#checkall").attr('checked',false);
		
	}//if(checked == true){

}//function SelecteachCheckbox(thisrow,checked) {

function SelecteachRow(evt,thisrow) {

    evt = (evt) ? evt : window.event;
	
	var idname = thisrow.attr("id");
	
	if(idname!='chkrow' && evt.target.type!=='checkbox' && evt.target.tagName.toLowerCase()!=='a' && evt.target.tagName.toLowerCase()!=='input' && evt.target.tagName.toLowerCase()!=='select' && evt.target.tagName.toLowerCase()!=='img' && evt.target.tagName.toLowerCase()!=='i'){

		var classname = thisrow.attr("class");
		var arrclass = classname.split(" ");
		var flag = true;
		var eLength = document.list.elements.length;
		var search_status = getCookie("currentsearch");
		var curcheck = thisrow.find("input[type='checkbox']").is(':checked');
		var checkboxlength = thisrow.find("input[type='checkbox']").length;

		if(curcheck==false && checkboxlength>=1) {
			thisrow.find("input[type='checkbox']").prop("checked", true);
			thisrow.closest('tr').addClass('active');
		}else if(curcheck==true && checkboxlength>=1){
			thisrow.find("input[type='checkbox']").prop("checked", false);
			thisrow.closest('tr').removeClass('active');
		}
		
		for (b=0; b<eLength; b++){
			var b_types = list.elements[b].type;
			var b_names = list.elements[b].name;
			if (b_types=="checkbox" && b_names!="checkall"){
				var begining = b-1;
				break;
			}
		}
		
		if(search_status==null){
			
				var start_num = parseInt(startrecord.innerHTML)+begining;
				var end_num = parseInt(endrecord.innerHTML)+begining;
				
				//alert(start_num+"-"+end_num);
				
				for(i=parseInt(start_num); i<=parseInt(end_num); i++){
					if(list.elements[i].checked==false){
						flag = false;
						break;
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
						if (types=="checkbox" && names!="checkall"){
							rows++;
							if(list.elements[i].checked==false){
								//alert(i+"="+list.elements[i].checked);
								flag = false;
								break;
							}
						}
					}
					
					if(rows==chkrows){
						break;
					}
					
				}
		
		}//if(search_status==null){

		if(arrclass.length<=1) {
			thisrow.closest('tr').addClass('active');
			$("#checkall").prop("checked", flag);
			//jQuery 1.6+
		}else{
			thisrow.closest('tr').removeClass('active');
			$("#checkall").prop("checked", false);
		}
		
	}//if(evt.target.type!=='checkbox' && evt.target.name!== 'img'){
	
}//function SelecteachRow(evt,thisrow) {
