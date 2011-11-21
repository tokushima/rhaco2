<?php require dirname(__FILE__)."/rhaco2.php"; app(); ?>
<app>
	<handler>
		<module class="test.flow.module.CoreTestAddModuleRaise" />
		<map class="test.CoreApp" method="noop" name="add_module_raise" />
	</handler>
</app>


<!---
#add_module_raise

$browser = test_browser();
$browser->do_get(test_map_url("add_module_raise"));
eq(500,$browser->status());
-->


