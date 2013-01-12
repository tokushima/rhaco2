<?php require dirname(__FILE__)."/rhaco2.php"; app(); ?>
<app>
	<handler>
		<map url="init_count" class="test.SupportTestFlow" method="init_count" name="init_count" />
		<map url="init_count_noext" class="test.SupportTestFlowNoExt" method="init_count" name="init_count_noext" />
	</handler>
</app>


<!---
# init_count
$browser = test_browser();
$browser->do_get(test_map_url("init_count"));
eq(200,$browser->status());
eq('<result><init_count>1</init_count></result>',$browser->body());
-->
<!---
# init_count_noext
$browser = test_browser();
$browser->do_get(test_map_url("init_count_noext"));
eq(200,$browser->status());
eq('<error><message group="exceptions" type="LogicException">class is not Flow</message></error>',$browser->body());
-->


