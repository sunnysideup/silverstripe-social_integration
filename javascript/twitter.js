/*global jQuery*/
(function($){
	window.TwitterResponse = function(data) {
		if(data.handle) {
			$('.connect-twitter').replaceWith('Connected to Twitter account @' + data.handle + '. <a href="' + data.removeLink + '" class="unconnect-twitter">Disconnect</a>');
			$("body").trigger("authchanged");
		}
	};
	$('.connect-twitter').livequery('click',  function (e) {
		var url = $("base").get(0).href;
		url += 'TwitterCallback/TwitterConnect';

		window.open(url).focus();
		e.stopPropagation();
		return false;
	}).
	$('.unconnect-twitter').livequery('click', function (e) {
		$.get($(this).attr('href'));
		$('.unconnect-twitter').each(function(i, elem) {
			$(elem).parent().html('connect with twitter');
		});

		$("body").trigger("authchanged");

		e.stopPropagation();
		return false;
	});
}(jQuery));
