	var timer
	var menuid = new Array("sbmenu00","sbmenu01","sbmenu02","sbmenu03","sbmenu04","sbmenu05","sbmenu06","sbmenu07","sbmenu08","sbmenu09","sbmenu10","sbmenu11","sbmenu12");

	function openSub(submenu, position) {

		if (timer)
		{
			clearTimeout(timer);
		}
	
		for(i = 0; i < menuid.length; i++) {
			if (menuid[i] != submenu)
				closeSub(menuid[i]);
		}
	
		if (document.getElementById(submenu)) {
			box = document.getElementById(submenu);
			pos = document.getElementById(position);
			$("div#"+submenu).fadeIn(100);
			box.style.display="inline" ;
			box.style.visibility = "visible" ;
			
			boxbg = document.getElementById(submenu+"bg");
			$("div#"+submenu+"bg").fadeIn("slow");
			boxbg.style.display="inline" ;
			boxbg.style.visibility = "visible" ;
		}
	}
	
	function getAbsX(elt) { return (elt.x) ? elt.x : getAbsPos(elt,"Left"); }
	function getAbsY(elt) { return (elt.y) ? elt.y : getAbsPos(elt,"Top"); }
	function getAbsPos(elt,which) {
	 iPos = 0;
	 while (elt != null) {
	  iPos += elt["offset" + which];
	  elt = elt.offsetParent;
	 }
	 return iPos;
	}
	
	function closeSubTimeout(submenu, timeout){
		timer = setTimeout('closeSub(\''+submenu+'\')', timeout);
	}
	
	function closeSub(submenu){
		if (document.getElementById(submenu)){
			box = document.getElementById(submenu);
			box.style.visibility = "hidden";
			box.style.display="none";

			boxbg = document.getElementById(submenu+"bg");
			boxbg.style.visibility = "hidden";
			boxbg.style.display="none";
		}
	}
