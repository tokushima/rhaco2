<?php require dirname(__FILE__)."/rhaco2.php"; app(); ?>
<app>
	<handler error_template="handler_error.html">
		<var name="abc" value="ABC" />
		<var name="def" value="DEF" />

		<map class="test.CoreApp" method="raise" name="handler_vars" template="empty.html" />
		<map class="test.CoreApp" method="raise" name="handler_map_vars" template="empty.html">
			<var name="def" value="123" />
			<var name="ghi" value="999" />
		</map>
		
		<map class="test.CoreApp" method="raise" name="map_error_handler_vars" template="empty.html" error_template="handler_error_map.html" />
		<map class="test.CoreApp" method="raise" name="map_error_handler_map_vars" template="empty.html" error_template="handler_error_map.html">
			<var name="def" value="123" />
			<var name="ghi" value="999" />
		</map>
	</handler>
</app>


<!---
$browser = test_browser();
$browser->do_get(test_map_url("handler_vars"));
eq(200,$browser->status());
eq("ABCDEFhoge",$browser->body());

$browser = test_browser();
$browser->do_get(test_map_url("handler_map_vars"));
eq(200,$browser->status());
eq("ABCDEF999hoge",$browser->body());

$browser = test_browser();
$browser->do_get(test_map_url("map_error_handler_vars"));
eq(200,$browser->status());
eq("MAPABCDEFhogeMAP",$browser->body());

$browser = test_browser();
$browser->do_get(test_map_url("map_error_handler_map_vars"));
eq(200,$browser->status());
eq("MAPABCDEF999hogeMAP",$browser->body());
-->


