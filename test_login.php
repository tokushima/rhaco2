<?php require dirname(__FILE__)."/rhaco2.php"; app(); ?>
<app nomatch_redirect="/">
	<handler>
		<map url="" name="A" class="test.CoreTestA" method="index" template="login/index.html" />
		<map url="b" name="B" class="test.CoreTestB" method="index" template="login/index.html" />
		<map url="c" name="C" class="test.CoreTestC" method="index" />
		
		<map url="d" name="D" method="index" />
		<map url="e" name="E" method="method_not_allowed" />		

		<map url="login" name="login" method="do_login" template="login/login.html">
			<arg name="login_redirect" value="A" />
		</map>
		<map url="logout" method="do_logout">
			<arg name="logout_redirect" value="login" />
		</map>
		<module class="test.CoreTestL" />
		<module class="test.flow.module.CoreTestLoginModule" />
		

		<map name="module" url="module" class="test.CoreTestA" method="index" template="module_index.html">

		</map>
	</handler>
</app>

<!---
# a
$b = test_browser();
$b->do_get(test_map_url("A"));
eq(test_map_url("login"),$b->url());

$b->submit();
eq(test_map_url("A"),$b->url());
eq(200,$b->status());
meq("hoge",$b->body());

$b->do_get(test_map_url("B"));
eq(200,$b->status());
meq("hoge",$b->body());
-->

<!---
# b
$b = test_browser();
$b->do_get(test_map_url("B"));
eq(test_map_url("login"),$b->url());

$b->submit();
eq(test_map_url("B"),$b->url());
eq(200,$b->status());
meq("hoge",$b->body());

$b->do_get(test_map_url("B"));
eq(200,$b->status());
meq("hoge",$b->body());
-->

<!---
# c
$b = test_browser();
$b->do_get(test_map_url("A"));
eq(test_map_url("login"),$b->url());

$b->submit();
eq(test_map_url("A"),$b->url());
eq(200,$b->status());
meq("hoge",$b->body());

$b->do_get(test_map_url("C"));
eq(test_map_url("C"),$b->url());
eq('<error><message group="exceptions" type="InvalidArgumentException">user is not a CoreTestN value</message></error>',$b->body());

$b->do_get(test_map_url("A"));
eq(test_map_url("login"),$b->url());

-->

<!---
# d
$b = test_browser();
$b->do_get(test_map_url("D"));
eq(test_map_url("D"),$b->url());
eq('<error><message group="exceptions" type="LogicException">::index not found</message></error>',$b->body());
-->

<!---
# e
$b = test_browser();
$b->do_get(test_map_url("E"));
eq(test_map_url("E"),$b->url());
eq(405,$b->status());
-->

<!---
# module

$b = test_browser();
$b->do_post(test_map_url("login"));

$b->do_get(test_map_url("module"));
eq(200,$b->status());
meq("INDEX",$b->body());

// ログイン処理とは別のFlow
meq("BEGIN_FLOW_HANDLE",$b->body());

meq("INIT_FLOW_HANDLE",$b->body());
meq("BEFORE_FLOW_HANDLE",$b->body());
meq("AFTER_FLOW_HANDLE",$b->body());
meq("INIT_TEMPLATE",$b->body());
meq("BEFORE_TEMPLATE",$b->body());
meq("AFTER_TEMPLATE",$b->body());
meq("BEFORE_EXEC_TEMPLATE",$b->body());
meq("AFTER_EXEC_TEMPLATE",$b->body());

// ログイン処理とは別のFlow
meq("BEFORE_FLOW_PRINT_TEMPLATE",$b->body());
-->

