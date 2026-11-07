DD_roundies.addRule("#nav", "5px");
DD_roundies.addRule("#nav li", "5px");

$(document).ready(function(){
	$nav_li=$("#nav li");
	$nav_li_selected=$("#nav li.selected");
	$nav_li_a=$nav_li.children("a");
	var animSpeed=450; //fade speed
	var hoverTextColor="#000000"; //text color on mouse over
	var hoverBackgroundColor="#00f0ff"; //background color on mouse over
	var textColor=$nav_li_a.css("color");
	var backgroundColor=$nav_li.css("background-color");
	//init selected
	$nav_li_selected.css("background-color",hoverBackgroundColor).children().css("color",hoverTextColor);
	$nav_li_a.hover(function() {
		var $this=$(this);
		$this.stop().animate({ color: hoverTextColor }, animSpeed).parent().stop().animate({ backgroundColor: hoverBackgroundColor }, animSpeed);
	},function() {
		var $this=$(this);
		if(!$this.parent().is(".selected")){
			$this.stop().animate({ color: textColor }, animSpeed).parent().stop().animate({ backgroundColor: backgroundColor }, animSpeed);
		}
	});
	//selected state
	$nav_li_a.click(function(){
		var $this=$(this);
		$this.stop().animate({ color: hoverTextColor }, animSpeed).parent().stop().animate({ backgroundColor: hoverBackgroundColor }, animSpeed).addClass("selected").siblings(".selected").removeClass("selected").stop().animate({ backgroundColor: backgroundColor }, animSpeed).children().stop().animate({ color: textColor }, animSpeed);
	});
});
