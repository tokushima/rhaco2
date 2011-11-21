<?php require dirname(__FILE__)."/rhaco2.php"; app(); ?>
<app nomatch_redirect="/" name="rhaco2 repository" summary="rhaco2のライブラリ群">
	<description>
		さまざまなライブラリ
		これまでもこれからも
	</description>
	<handler class="org.rhaco.flow.parts.Developer" url="">
		<module class="org.rhaco.flow.module.TwitterBootstrapPagination" />
	</handler>
	<handler>
		<map url="aaa" template="index.html" />
	</handler>
</app>
