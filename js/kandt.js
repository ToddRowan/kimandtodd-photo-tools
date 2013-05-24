(function($) {
    $(document).ready(function() {
        $('.cycle_imgs_box').each(function(){$(this).prependTo($(this).parent());});
        window.setTimeout(function() {$('.cycle_imgs').cycle({
            fx: 'fade' // choose your transition type, ex: fade, scrollUp, shuffle, etc...
        });}, 2000);
    
        $('.gallery').each(
            function()
            {
                var tos = $('#'+$(this).first().attr('id') +  ' a').TosRUs({"caption":["data-caption"],'anchors':{'zoomIcon':false}});
                if (location.hash=="#startshow") tos.trigger("open");
                setLoggers(tos);
            });
            
        
        if (!isTouchDevice())
            {
                $('.cycle_imgs_box').hover(function(){$(this).find(".cycle_overlay").fadeIn();}, 
                         function(){$(this).find(".cycle_overlay").fadeOut();});            
            }
            else
            {
                $(".cycle_overlay").css('opacity', '1').show();
            }
      }
    )
 }
)(jQuery);


// This has to be a bug in iOS Safari
// http://stackoverflow.com/questions/10973649/mobile-safari-reflow-without-resize-buggy-behavior
function orientation_change() {
    if (window.orientation == 0 || window.orientation == 180)
        document.getElementById("viewport").setAttribute("content", "width=device-width, maximum-scale=1.0, initial-scale=1.0, user-scalable=no");
    else if (window.orientation == -90 || window.orientation == 90)
        document.getElementById("viewport").setAttribute("content", "width=device-height, maximum-scale=1.0, initial-scale=1.0, user-scalable=no");     
}

function openLog(e, inx, whatami)
{
  // e is the jquery event object
  // inx is the 0-based index of the slide that's opening. 
  // This value is only defined if the show opens on a specific slide.
  // Although undefined is basically = to 0
  // Not sure about whatami, but I think it's the direct value if set.
  console.log("Tos opened. Starting image index " + ( typeof inx === 'undefined' ? "undefined, assuming zero": inx) );
}
function closeLog(e, duration)
{
   console.log("Tos closed. Fadeout duration: " + duration + " ms");
}
function slideLog(e, inx, whatami)
{
  console.log("Tos sliding. To index: " + inx );
}
function loadLog(e, a, div)
{
   // e is a jquery event obj. 
   // a is the $(<A>) element with the image we're loading.
   // div is the $(<DIV>) element that tos wraps the image in. 
   if (typeof a !== 'undefined') console.log("Loading image: " + (a[0].pathname).substr((a[0].pathname).lastIndexOf('/')+1));
}

function setLoggers(tos)
{
  tos.bind("opening.tos", openLog);
  tos.bind("closing.tos", closeLog);
  tos.bind("sliding.tos", slideLog);
  tos.bind("loading.tos", loadLog);
}

function isTouchDevice() {
   var el = document.createElement('div');
   el.setAttribute('ongesturestart', 'return;');
   return typeof el.ongesturestart === "function";
}