<?php require dirname(__FILE__)."/rhaco2.php"; app(); ?>
<app>
	<handler>
		<map url="init_count" class="test.SupportTestFlow" method="init_count" name="init_count" />
		<map url="init_count_noext" class="test.SupportTestFlowNoExt" method="init_count" name="init_count_noext" />
		<map url="check_session" class="test.SupportTestFlow" method="check_session" name="check_session" />		
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

<!---
# check_session
$browser = test_browser();
$browser->do_get(test_map_url("check_session"));
eq(200,$browser->status());
eq('<result><count>1</count></result>',$browser->body());

$browser->do_get(test_map_url("check_session"));
eq(200,$browser->status());
eq('<result><count>2</count></result>',$browser->body());

$browser->do_get(test_map_url("check_session"));
eq(200,$browser->status());
eq('<result><count>3</count></result>',$browser->body());

$browser = test_browser();
$browser->do_get(test_map_url("check_session"));
eq(200,$browser->status());
eq('<result><count>1</count></result>',$browser->body());
-->



