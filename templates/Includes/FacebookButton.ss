<!-- parent controller should be Member -->
<% if FacebookButton %>
<div id="FacebookButton">
	<% control FacebookButton %>
	<p>
		<% if IsConnected %>
		<% if ConnectedImageURL %><img src="$ConnectedImageURL" alt="$ConnectedName.ATT" /><% end_if %>
		You are connected to Facebook<% if ConnectedName %> as <i>$ConnectedName</i><% end_if %>.
		<a href="$Link" class="button">Disconnect</a>
		<% else %>
		<a href="$Link" class="button">Connect</a>
		<% end_if %>
	</p>
	<% end_control %>
</div>
<% end_if %>
