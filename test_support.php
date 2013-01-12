<?php require dirname(__FILE__)."/rhaco2.php"; app(); ?>
<app>
	<handler>
		<map url="runcount" class="test.CoreTestSupportFlow" method="init_count" name="init_count" />
	</handler>
</app>


<!---
# init_count
$browser = test_browser();
$browser->do_get(test_map_url("init_count"));
eq(200,$browser->status());
eq('<result><init_count>2</init_count></result>',$browser->body());
-->


