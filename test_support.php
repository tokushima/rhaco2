<?php require dirname(__FILE__)."/rhaco2.php"; app(); ?>
<app>
	<handler>
		<map url="init_count" class="test.SupportTestFlow" method="init_count" name="init_count" />
		<map url="init_count_noext" class="test.SupportTestFlowNoExt" method="init_count" name="init_count_noext" />
		<map url="check_session" class="test.SupportTestFlow" method="check_session" name="check_session" />		
	</handler>
	<handler>
		<module class="test.SupportTestLoginModule" />
		<map url="login" name="login" method="do_login">
			<arg name="login_redirect" value="check_login" />
		</map>	
		<map url="logout" name="logout" method="do_logout">
		<map url="check_login" class="test.SupportTestFlow" method="check_login" name="check_login" />
		<map url="redirect_check_login" class="test.SupportTestFlow" method="redirect_check_login" name="redirect_check_login" />		
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

<!---
# check_login
$dat_file = basename(__FILE__).'.dat';
$browser = test_browser();

$browser->do_get(test_map_url("check_login"));
eq(200,$browser->status());
eq('<result><is_login>false</is_login><user /></result>',$browser->body());

$browser->vars('user_name','hogeuser');
$browser->vars('password','hogehoge');
$browser->vars('user_data_file',$dat_file);
$browser->do_post(test_map_url("login"));
eq(200,$browser->status());
eq('<result><is_login>true</is_login><user><count>1</count></user></result>',$browser->body());

$browser->do_get(test_map_url("check_login"));
eq(200,$browser->status());
eq('<result><is_login>true</is_login><user><count>1</count></user></result>',$browser->body());

$browser->do_get(test_map_url("check_login"));
eq(200,$browser->status());
eq('<result><is_login>true</is_login><user><count>1</count></user></result>',$browser->body());

$browser->do_get(test_map_url("logout"));


$browser = test_browser();
$browser->do_get(test_map_url("check_login"));
eq(200,$browser->status());
eq('<result><is_login>false</is_login><user /></result>',$browser->body());

$browser->vars('user_name','hogeuser');
$browser->vars('password','hogehoge');
$browser->vars('user_data_file',$dat_file);
$browser->do_post(test_map_url("login"));
eq(200,$browser->status());
eq(test_map_url("check_login"),$browser->url());
eq('<result><is_login>true</is_login><user><count>2</count></user></result>',$browser->body());

$browser->do_get(test_map_url("check_login"));
eq(200,$browser->status());
eq('<result><is_login>true</is_login><user><count>2</count></user></result>',$browser->body());


$browser->do_get(test_map_url("redirect_check_login"));
eq(200,$browser->status());
eq(test_map_url("check_login"),$browser->url());
eq('<result><is_login>true</is_login><user><count>2</count></user></result>',$browser->body());


$dat = App::work($dat_file);
if(File::exist($dat)) File::rm($dat);
-->


