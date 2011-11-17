<?php require dirname(__FILE__)."/rhaco2_core.php"; app(); ?>
<app nomatch_redirect="/">
	<handler>
		<map url="" redirect="dev" />
	</handler>
	<handler class="org.rhaco.flow.parts.Developer" url="dev">
	</handler>
</app>