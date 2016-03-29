function initErrorTree() {
	// Mark subtrees
	$('ul').parents('li').addClass('has-subtree');

	// Set up click delegate for opening/closing folders
	$('body').on('click', '.key', function() {
		if (this.id && history.replaceState) history.replaceState(null, null, '#' + this.id);
		$(this).closest('li').toggleClass('open');
	});

	// Links to variable references - ensure target is visible (browser will natively jump to the element using anchors)
	function open(hash) {
		$(hash).closest('li').addClass('highlighted').parents('li').andSelf().addClass('open');
	}
	$('a.objreflink').click(function() {
		open($(this).attr('href'));
	});

	// Open to a folder if an anchor is supplied on querystring

	window.onpopstate = function() {
		open(location.hash)
	};

	if (location.hash) {
		window.onpopstate();
	}

	// When highlight animations end, remove the animation class so animation will run again next time
	$('body').on('animationend webkitAnimationEnd MSAnimationEnd', 'li.highlighted', function() {
		$(this).removeClass('highlighted');
	})

	// Open up parents of anything already open
	$('li.open').parents('li').addClass('open');
};

function sublime(path, line) {
	var mappings = localStorage['mappings'];
	if (!mappings) {
		mappings = [];
	} else {
		mappings = JSON.parse(mappings);
	}

	for(var i=0; i < mappings.length; i++) {
		var mapping = mappings[i];
		if (path.substring(0,mapping.from.length) == mapping.from) {
			path = mapping.to + path.substring(mapping.from.length);
			console.log('found',mapping, path)
			location.href = 'txmt://open?url=file://' + escape(path) + '&line=' + line;
			return false;
		}
	}

	alert("I don't know where " + path + " is on your local disk.\n\nPlease modify the path and then click 'Save'. You need TextMate or github.com/dhoulb/subl installed.");
	var path_container = $('#filepath');
	var newpath_input = $('<input>').val(path).css('min-width',(path_container.width()+5)+'px').appendTo(path_container.empty()).focus();
	$('<input type=button>').val("Save").appendTo(path_container).click(function(){
		var oldcomponents = path.split('/');
		var newcomponents = newpath_input.val().split('/');
		while(newcomponents.length > 2 && oldcomponents.length > 2 && newcomponents[newcomponents.length-1] == oldcomponents[oldcomponents.length-1]) {
			newcomponents.pop();
			oldcomponents.pop();
		}
		mappings.push({
			from:oldcomponents.join('/'), to:newcomponents.join('/')
		})
		localStorage['mappings'] = JSON.stringify(mappings);
		path_container.text(newpath_input.val());
		sublime(path, line);
		return false;
	});
	return false;
}
