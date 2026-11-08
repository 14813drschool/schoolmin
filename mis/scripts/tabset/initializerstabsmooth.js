// Run javascript after DOM is initialized
$(document).ready(function() {

	$('#body').waypoint('sticky');

	$('.tabs_default').tabslet();

	$('.tabs_hover').tabslet({
		mouseevent: 'hover',
		attribute: 'href',
		animation: false
	});

	$('.tabs_animate').tabslet({
		mouseevent: 'click',
		attribute: 'href',
		animation: true
	});

	$('.tabs_rotate').tabslet({
		autorotate: true,
		delay: 3000
	});

	$('.tabs_controls').tabslet();

	$('.before_event').tabslet();

	$('.after_event').tabslet({
		animation: true
	});

});