<!-- parent controller should be Member -->
<% if LinkedinButton %>
<div id="LinkedinButton">
	<% control LinkedinButton %>
	<p>
		<% if IsConnected %>
		<% if ConnectedImageURL %><img src="$ConnectedImageURL" alt="$ConnectedName.ATT" /><% end_if %>
		You are connected to Linkedin<% if ConnectedName %> as <i>$ConnectedName</i><% end_if %>.
		<a href="$Link" class="button">Disconnect</a>
		<% else %>
		<a href="$Link" class="button">Connect</a>
		<% end_if %>
	</p>
	<% end_control %>
</div>
<% end_if %>
