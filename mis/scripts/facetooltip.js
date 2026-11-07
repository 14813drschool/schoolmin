/*
 * Facebox (for jQuery)
 * version: 1.2 (05/05/2008)
 * @requires jQuery v1.2 or later
 *
 * Examples at http://famspam.com/facetooltip/
 *
 * Licensed under the MIT:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Copyright 2007, 2008 Chris Wanstrath [ chris@ozmm.org ]
 *
 * Usage:
 *
 *  jQuery(document).ready(function() {
 *    jQuery('a[rel*=facetooltip]').facetooltip()
 *  })
 *
 *  <a href="#terms" rel="facetooltip">Terms</a>
 *    Loads the #terms div in the box
 *
 *  <a href="terms.html" rel="facetooltip">Terms</a>
 *    Loads the terms.html page in the box
 *
 *  <a href="terms.png" rel="facetooltip">Terms</a>
 *    Loads the terms.png image in the box
 *
 *
 *  You can also use it programmatically:
 *
 *    jQuery.facetooltip('some html')
 *    jQuery.facetooltip('some html', 'my-groovy-style')
 *
 *  The above will open a facetooltip with "some html" as the content.
 *
 *    jQuery.facetooltip(function($) {
 *      $.get('blah.html', function(data) { $.facetooltip(data) })
 *    })
 *
 *  The above will show a loading screen before the passed function is called,
 *  allowing for a better ajaxy experience.
 *
 *  The facetooltip function can also display an ajax page, an image, or the contents of a div:
 *
 *    jQuery.facetooltip({ ajax: 'remote.html' })
 *    jQuery.facetooltip({ ajax: 'remote.html' }, 'my-groovy-style')
 *    jQuery.facetooltip({ image: 'stairs.jpg' })
 *    jQuery.facetooltip({ image: 'stairs.jpg' }, 'my-groovy-style')
 *    jQuery.facetooltip({ div: '#box' })
 *    jQuery.facetooltip({ div: '#box' }, 'my-groovy-style')
 *
 *  Want to close the facetooltip?  Trigger the 'close.facetooltip' document event:
 *
 *    jQuery(document).trigger('close.facetooltip')
 *
 *  Facebox also has a bunch of other hooks:
 *
 *    loading.facetooltip
 *    beforeReveal.facetooltip
 *    reveal.facetooltip (aliased as 'afterReveal.facetooltip')
 *    init.facetooltip
 *    afterClose.facetooltip
 *
 *  Simply bind a function to any of these hooks:
 *
 *   $(document).bind('reveal.facetooltip', function() { ...stuff to do after the facetooltip and contents are revealed... })
 *
 */
(function($) {
  $.facetooltip = function(data, klass) {
    $.facetooltip.loading()

    if (data.ajax) fillFaceboxFromAjax(data.ajax, klass)
    else if (data.image) fillFaceboxFromImage(data.image, klass)
    else if (data.div) fillFaceboxFromHref(data.div, klass)
    else if ($.isFunction(data)) data.call($)
    else $.facetooltip.reveal(data, klass)
  }

  /*
   * Public, $.facetooltip methods
   */

  $.extend($.facetooltip, {
    settings: {
      opacity      : 0.08,
      overlay      : true,
      loadingImage : '../images/fbloader.gif',
      imageTypes   : [ 'png', 'jpg', 'jpeg', 'gif' ],
      facetooltipHtml  : '\
    <div id="facetooltip" style="display:none;"> \
      <div class="popup"> \
        <div class="content"> \
        </div> \
      </div> \
    </div>'
    },

    loading: function() {
      init()
      if ($('#facetooltip .loading').length == 1) return true
      showOverlay()

     $('#facetooltip .content').empty().append('<div class="loading"><img src="'+$.facetooltip.settings.loadingImage+'"/></div>')


      $('#facetooltip').show().css({
        top:	$(window).height() / 2 - (500 / 2),
        left:	$(window).width() / 2 - ($('#facetooltip .popup').outerWidth() / 2)
      })

      $(document).bind('keydown.facetooltip', function(e) {
        if (e.keyCode == 27) $.facetooltip.close()
        return true
      })
      $(document).trigger('loading.facetooltip')
    },

    reveal: function(data, klass) {

      $(document).trigger('beforeReveal.facetooltip')
      if (klass) $('#facetooltip .content').addClass(klass)
      $('#facetooltip .content').empty().append(data)

      $('#facetooltip .popup').children().fadeIn('normal')
      $('#facetooltip').css('left', $(window).width() / 2 - ($('#facetooltip .popup').outerWidth() / 2))
      $(document).trigger('reveal.facetooltip').trigger('afterReveal.facetooltip')
    },

    close: function() {
      $(document).trigger('close.facetooltip')
      return false
    }
  })

  /*
   * Public, $.fn methods
   */

  $.fn.facetooltip = function(settings) {
    if ($(this).length == 0) return

    init(settings)

    function clickHandler() {
      $.facetooltip.loading(true)

      // support for rel="facetooltip.inline_popup" syntax, to add a class
      // also supports deprecated "facetooltip[.inline_popup]" syntax
      var klass = this.rel.match(/facetooltip\[?\.(\w+)\]?/)
      if (klass) klass = klass[1]

      fillFaceboxFromHref(this.href, klass)
      return false
    }

    return this.bind('click.facetooltip', clickHandler)
  }

  /*
   * Private methods
   */

  // called one time to setup facetooltip on this page
  function init(settings) {
    if ($.facetooltip.settings.inited) return true
    else $.facetooltip.settings.inited = true

    $(document).trigger('init.facetooltip')
    makeCompatible()

    var imageTypes = $.facetooltip.settings.imageTypes.join('|')
    $.facetooltip.settings.imageTypesRegexp = new RegExp('\\.(' + imageTypes + ')(\\?.*)?$', 'i')

    if (settings) $.extend($.facetooltip.settings, settings)
    $('body').append($.facetooltip.settings.facetooltipHtml)

    var preload = [ new Image(), new Image() ]
    preload[0].src = $.facetooltip.settings.closeImage
    preload[1].src = $.facetooltip.settings.loadingImage

    $('#facetooltip').find('.b:first, .bl').each(function() {
      preload.push(new Image())
      preload.slice(-1).src = $(this).css('background-image').replace(/url\((.+)\)/, '$1')
    })

    $('#facetooltip .close')
      .click($.facetooltip.close)
      .append('<img src="'
              + $.facetooltip.settings.closeImage
              + '" class="close_image" title="close">')
  }

  // getPageScroll() by quirksmode.com
  function getPageScroll() {
    var xScroll, yScroll;
    if (self.pageYOffset) {
      yScroll = self.pageYOffset;
      xScroll = self.pageXOffset;
    } else if (document.documentElement && document.documentElement.scrollTop) {	 // Explorer 6 Strict
      yScroll = document.documentElement.scrollTop;
      xScroll = document.documentElement.scrollLeft;
    } else if (document.body) {// all other Explorers
      yScroll = document.body.scrollTop;
      xScroll = document.body.scrollLeft;
    }
    return new Array(xScroll,yScroll)
  }

  // Adapted from getPageSize() by quirksmode.com
  function getPageHeight() {
    var windowHeight
    if (self.innerHeight) {	// all except Explorer
      windowHeight = self.innerHeight;
    } else if (document.documentElement && document.documentElement.clientHeight) { // Explorer 6 Strict Mode
      windowHeight = document.documentElement.clientHeight;
    } else if (document.body) { // other Explorers
      windowHeight = document.body.clientHeight;
    }
    return windowHeight
  }

  // Backwards compatibility
  function makeCompatible() {
    var $s = $.facetooltip.settings

    $s.loadingImage = $s.loading_image || $s.loadingImage
    $s.closeImage = $s.close_image || $s.closeImage
    $s.imageTypes = $s.image_types || $s.imageTypes
    $s.facetooltipHtml = $s.facetooltip_html || $s.facetooltipHtml
  }

  // Figures out what you want to display and displays it
  // formats are:
  //     div: #id
  //   image: blah.extension
  //    ajax: anything else
  function fillFaceboxFromHref(href, klass) {
    // div
    if (href.match(/#/)) {
      var url    = window.location.href.split('#')[0]
      var target = href.replace(url,'')
      if (target == '#') return
      $.facetooltip.reveal($(target).html(), klass)

    // image
    } else if (href.match($.facetooltip.settings.imageTypesRegexp)) {
      fillFaceboxFromImage(href, klass)
    // ajax
    } else {
      fillFaceboxFromAjax(href, klass)
    }
  }

  function fillFaceboxFromImage(href, klass) {
    var image = new Image()
    image.onload = function() {
      $.facetooltip.reveal('<div class="image"><img src="' + image.src + '" /></div>', klass)
    }
    image.src = href
  }

  function fillFaceboxFromAjax(href, klass) {
    $.get(href, function(data) { $.facetooltip.reveal(data, klass) })
  }

  function skipOverlay() {
    return $.facetooltip.settings.overlay == false || $.facetooltip.settings.opacity === null
  }

  function showOverlay() {
    if (skipOverlay()) return

    if ($('#facetooltip_overlay').length == 0)
      $("body").append('<div id="facetooltip_overlay" class="facetooltip_hide"></div>')

    $('#facetooltip_overlay').hide().addClass("facetooltip_overlayBG")
      .css('opacity', $.facetooltip.settings.opacity)
      .click(function() { $(document).trigger('close.facetooltip') })
      .fadeIn(50)
    return false
  }

  function hideOverlay() {
    if (skipOverlay()) return

    $('#facetooltip_overlay').fadeOut(50, function(){
      $("#facetooltip_overlay").removeClass("facetooltip_overlayBG")
      $("#facetooltip_overlay").addClass("facetooltip_hide")
      $("#facetooltip_overlay").remove()
    })

    return false
  }

  /*
   * Bindings
   */

  $(document).bind('close.facetooltip', function() {
	 $(document).unbind('keydown.facetooltip')
	 $('#facetooltip').fadeOut(function() {
		  $('#facetooltip .content').removeClass().addClass('content')
		  $('#facetooltip .loading').remove()
		  $(document).trigger('afterClose.facetooltip')
    })
    hideOverlay()
  })

})(jQuery);
