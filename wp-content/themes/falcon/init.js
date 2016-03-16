if (typeof FT       === 'undefined') { FT = {}; }
if (typeof pageUUID === 'undefined') { pageUUID = ""; }

Assanka.$(document).ready(function($) {
    if (document.getElementById("navigation")) { FT.preInit.distributeNavItems(document.getElementById("navigation")); }
    FT.preInit.removeNojsClassFromBody();
    FT.preInit.addOSClassToBody(navigator);
    FT.preInit.loginForm();

    // Add the 'on' class to the appropriate element in the blogs drop-down navigation.
    $('ul.subnav li.on ul.subnav a[href="' + document.location.pathname + '"]').parent().addClass('on');

    // Facebook counts
    var urls = [];
    $('a.permalink').each(function() {
        urls.push(this.href);
    });
    if (urls.length) {
        var query = 'SELECT url, share_count, like_count, comment_count, total_count FROM link_stat WHERE url IN("' + urls.join('","') + '")';
        var scriptlink = "https://api.facebook.com/method/fql.query?query=" + encodeURIComponent(query) + "&format=json&callback=?";
        $.getJSON(scriptlink, function(data) {
            $.each(data, function(i, item) {
                if ($('a.facebook-counter').length == 1) {
                    $('a.facebook-counter').html(item.share_count);
                } else {
                    $('h2.entry-title a').each(function(j, el) {
                        if (el.href == item.url)
                            $(el).closest('div.post').find('a.facebook-counter').html(item.share_count);
                    });
                }
            });
        });
    }

    // Handle the popup windows for the share buttons
    $('li.sharelink a').click(function(){
        var width  = screen.width - 100;
        var height = screen.height - 100;
        if ($(this).attr('class') == 'twitter') {
            width  = 560;
            height = 250;
        } else if ($(this).attr('class') == 'facebook') {
            width  = 640;
            height = 300;
        } else if ($(this).attr('class') == 'googleplus') {
            width  = 640;
            height = 360;
        } else if ($(this).attr('class') == 'linkedin') {
            width  = 560;
            height = 400;
        } else if ($(this).attr('class') == 'stumbleupon') {
            width  = 950;
            height = 940;
        } else if ($(this).attr('class') == 'reddit') {
            width  = 800;
            height = 720;
        }

        var left   = (screen.width  - width)/2;
        var top    = (screen.height - height)/4;
        var params = 'width='+width+', height='+height+', top='+top+', left='+left;
        var title  = 'Share on ' + $(this).attr('class');
        share_window = window.open($(this).attr('href'), title, params);
        if (window.focus) {share_window.focus()}
        return false;
     });

    // Display a light box
    Assanka.showLightBox = function(content, elAnchor, classname, title, width) {

        // Set default classname
        if (typeof (classname) == "undefined")
            classname = "";

        // Set lightbox HTML
        var lightbox_element = $('<div class="assankablogs assankalightbox ' + classname + '" style="width:' + width + 'px"><div class="assankalightboxinner"><div class=\'assankalightboxheader\'><span style=\'float:left;\'>' + title + '</span><a style=\'float:right;\' href="javascript:void(0)" onclick="$(this).parents(\'.assankalightbox\').remove();" class=\'assankaLightBoxHideButton\'>Close</a></div>' + content + '</div></div>').hide();

        // Add lightbox to page
        $("body").append(lightbox_element);

        // Set position of lightbox, so that the bottom of the lightbox lines up with the
        // bottom of the anchor element and is centered horizontally on the page
        // (has to be done after the lightbox is added to the page so that the width() and height() are nonzero)
        lightbox_element.css({"position": "absolute","top": ((parseInt(elAnchor.offset().top) - lightbox_element.outerHeight()) + elAnchor.outerHeight()) + "px","left": (($(window).width() / 2) - (lightbox_element.width() / 2)) + "px"});

        // Show lightbox
        $(lightbox_element).show();
    }
});


// Insert the MBA Newslines script into the <head>
(function() {
    var host = "mbanewslines.ft.com";
    var useSsl = location.href.match(/^https\:/);
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'http' + (useSsl ? 's' : '') + '://' + host + '/resources/javascript/bootstrap';
    document.getElementsByTagName("head")[0].appendChild(script);
}());


