/*global jQuery*/
(function($){
	window.FacebookResponse = function(data) {
		if(data.name) {
			$('.connect-facebook').replaceWith('Connected to Facebook user ' + data.name + '. <a href="' + data.removeLink + '" class="unconnect-facebook">Disconnect</a>');

			// add all the possible pages the user could select
			if(typeof data.pages === "object") {
				var container = $('.facebook-groups');
				if(container.length > 0) {
					container.html('');

					var i = 0;
					for(var value in data.pages) {
						var label = data.pages[value];
						var name = "PostToFacebookPages[" + value + "]";
						var item = $('<li></li>');
						++i;

						if(label) {
							item.append($("<input type='checkbox' />").val(value).attr('name', name).attr('id', 'checkbox-fb-'+i));
							item.append($("<label></label>").attr('for', 'checkbox-fb-'+i).html(label));

							container.append(item);
						}
					}
				}
			}
		}

		$("body").trigger("authchanged");
	};
	$('.connect-facebook').livequery('click', function (e) {
		var url = $("base").get(0).href;
		url += 'FacebookCallback/FacebookConnect';

		window.open(url).focus();

		e.stopPropagation();
		return false;
	});
	$('.unconnect-facebook').livequery('click', function (e) {
		$.get($(this).attr('href'));

		$('.unconnect-facebook').each(function (i, elem) {
			$(elem).parent().html('connect to facebook');
		});
		$("body").trigger("authchanged");
		return false;
	});
}(jQuery));
