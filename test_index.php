<?php require dirname(__FILE__)."/rhaco2_core.php"; app(); ?>
<app name="application name" summary="summary">
	<description>description</description>
	<installation>
		htaccessが必要
	</installation>
	<handler>
		<map name="index" url="" template="index.html">
			<var name="xml_var" value="hogehoge_xml_var" />
			<var name="xml_var_value">
				hogehoge_xml_var_value
			</var>
			<var name="xml_var_array" value="A1" />
			<var name="xml_var_array" value="A2" />
			<var name="xml_var_array" value="A3" />
			<var name="xml_var_obj" class="test.CoreTestFlowVar" />
			<var name="xml_var_objA" class="test.CoreTestFlowVar" method="aaa" />
			<var name="xml_var_objB" class="test.CoreTestFlowVar" method="bbb" />
			<var name="xml_var_objC" class="test.CoreTestFlowVar" method="ccc" />
		</map>
		<maps class="test.CoreApp">
			<map name="under_var" url="under_var"method="under_var" template="under_var.html" />
			<map name="module_throw_exception" url="module_throw_exception" method="noop">
				<module class="test.flow.module.CoreTestExceptionModule" />
			</map>
			<map name="noop" url="noop" method="noop" />
			<map name="method_not_allowed" url="method_not_allowed" method="method_not_allowed />
		</maps>
		
		<map url="redirect_test_a" class="test.CoreTestRedirectMapA" method="redirect_by_map_method_a" name="redirect_by_map_method_a" />
		<map url="redirect_test_call_a" template="redirect_test_call_a.html" name="redirect_by_map_method_call_a" />

		<map url="redirect_test_b" class="test.CoreTestRedirectMapA" method="redirect_by_map_method_b" name="redirect_by_map_method_b">
			<arg name="redirect_by_map_method_call_b" value="redirect_by_map_method_call_alias_b" />
		</map>
		<map url="redirect_test_call_b" template="redirect_test_call_b.html" name="redirect_by_map_method_call_alias_b" />
		
		<map url="redirect_test_c" class="test.CoreTestRedirectMapA" method="redirect_by_map_method_c" name="redirect_by_map_method_c">
			<arg name="redirect_by_map_method_call_c" value="redirect_by_map_method_call_alias_c" />
		</map>
		<map url="redirect_test_call_c" template="redirect_test_call_c.html" name="redirect_by_map_method_call_alias_c" />
		<map url="redirect_test_call_c_e" template="redirect_test_call_c_e.html" name="redirect_by_map_method_call_c" />		
	</handler>
	<handler>
		<module class="test.flow.module.CoreTestModule" />
		<map name="module" url="module" template="module_index.html" />
	</handler>
	<handler>
		<map name="module_map" url="module_map" template="module_index.html">
			<module class="test.flow.module.CoreTestModule" />
		</map>
	</handler>
	<handler>
		<maps>
			<map name="module_maps" url="module_maps" template="module_index.html" />
			<module class="test.flow.module.CoreTestModule" />
		</maps>
	</handler>
	
	<handler error_template="module_exception.html">
		<module class="test.flow.module.CoreTestModule" />
		<map class="test.CoreApp" method="raise" name="module_raise" url="module_raise" template="module_index.html" />
		<map class="test.CoreApp" method="add_exceptions" name="module_add_exceptions" url="module_add_exceptions" template="module_index.html" />
	</handler>
	<handler error_status="403">
		<map class="test.CoreApp" method="raise" name="raise" />
	</handler>
	
	<handler>
		<module class="test.flow.module.CoreTestModuleOrder" />
		<map name="module_order" url="module_order" template="module_order.html" />
	</handler>
	
	<handler>
		<map url="notemplate" name="notemplate" class="test.CoreTestNotTemplate" method="aaa" />
	</handler>
	
	<handler>
		<module class="test.flow.module.CoreTestLogin" />
		<map url="login" class="test.CoreTestLoginFlow" method="do_login" name="login" />
	</handler>

	<handler>
		<map name="not_url_method" class="test.CoreTestNotUrlMethod" />
		<maps url="not" class="test.CoreTestNotUrlMethod">
			<map name="not_method" url="abcd" />
			<map name="not_method_empty_url" url="" />
		</maps>
	</handler>

	<handler>
		<module class="test.flow.module.CoreTestLoginRequiredAlways" />
		<maps class="test.CoreTestLoginFlow">
			<map method="do_login" name="login_required_exception_login" />
			<map method="aaa" name="login_required_exception" />
		</maps>
	</handler>
	
	<handler>
		<map url="vars_xml" vars="test_vars.xml" name="vars_xml" />
	</handler>
	

	<handler>
		<map url="put_block" class="test.CoreTestPutBlock" method="index" template="put_block.html" name="put_block" />
	</handler>

	<handler>
		<map template="abc.html" url="theme" theme_path="custom_theme" class="test.CoreTestTheme" method="index" name="theme" />
	</handler>
	<handler theme_path="custom_theme">
		<map template="abc.html" url="theme_handler" class="test.CoreTestTheme" method="index" name="theme_handler" />
	</handler>
	<handler>
		<maps theme_path="custom_theme">
			<map template="abc.html" url="theme_maps" class="test.CoreTestTheme" method="index" name="theme_maps" />
		</maps>
	</handler>
	
	<handler theme_path="custom_theme_handler">
		<maps theme_path="custom_theme_maps">
			<map template="abc.html" url="theme_parent" theme_path="custom_theme" class="test.CoreTestTheme" method="index" name="theme_parent" />
		</maps>
	</handler>

	<handler>
		<map template="abc.html" url="theme_none" class="test.CoreTestTheme" method="index" name="theme_none" />
	</handler>
	
	<handler template_path="custom_theme_handler">
		<maps template_path="custom_theme_maps/custom_theme">
			<map template="abc.html" url="theme_empty" theme_path="" class="test.CoreTestTheme" method="index" name="theme_empty" />
		</maps>
	</handler>

	<handler>
		<map name="trans" url="trans" template="trans.html" />
	</handler>

	<handler>
		<map name="sample_flow_html" template="sample.html" />
	</handler>
	<handler url="plain_theme" template_path="template_path">
		<map template="index.html" name="template_path_html" />
	</handler>
	<handler url="plain_theme" template_path="template_path" theme_path="theme_path">
		<map template="index.html" name="template_path_theme_html" />
	</handler>
	
	<handler url="sample_flow_theme" theme_path="theme_path">
		<map class="test.SampleFlow" method="index" template="sample.html" name="sample_flow_theme_index" />
		<map class="test.SampleFlow" method="hoge" url="hoge" template="sample.html" name="sample_flow_theme_hoge" />
	</handler>
	<handler url="sample_flow_theme_not_path">
		<map class="test.SampleFlow" method="index" template="sample.html" name="sample_flow_theme_not_index" />
		<map class="test.SampleFlow" method="hoge" url="hoge" template="sample.html" name="sample_flow_theme_not_hoge" />
	</handler>
	<handler url="sample_flow" class="test.SampleFlow" name="sample_flow" />
	

	<handler url="sample_flow_theme_media_plain" media_path="media_path">
		<map class="test.SampleMediaFlow" method="index" template="sample_media.html" name="sample_flow_theme_media_plain_index" />
		<map class="test.SampleMediaFlow" method="hoge" url="hoge" template="sample_media.html" name="sample_flow_theme_media_plain_hoge" />
	</handler>
	<handler url="sample_flow_theme_media" theme_path="theme_path" media_path="media_path">
		<map class="test.SampleMediaFlow" method="index" template="sample_media.html" name="sample_flow_theme_media_index" />
		<map class="test.SampleMediaFlow" method="hoge" url="hoge" template="sample_media.html" name="sample_flow_theme_media_hoge" />
	</handler>
	<handler url="sample_flow_theme_media" theme_path="theme_path" media_path="media_path">
		<map class="test.SampleMediaFlow" method="index" template="sample_media.html" name="sample_flow_theme_media_index" />
		<map class="test.SampleMediaFlow" method="hoge" url="hoge" template="sample_media.html" name="sample_flow_theme_media_hoge" />
	</handler>
	<handler url="sample_media_flow" class="test.SampleMediaFlow" name="sample_media_flow" media_path="media_path" />
	
	
	
	<handler url="sample_flow_exception">
		<map name="sample_flow_exception_throw" url="throw" class="test.SampleExceptionFlow" method="throw_method" error_template="exception_flow/error.html" />
	</handler>
	
	<handler url="extends_block_template">
		<map name="template_super_a" url="extendsA" template="template_super.html" />
		<map name="template_super_b" url="extendsB" template="template_super.html" template_super="template_super_x.html" />
	</handler>
</app>

<!---
# var
$b = test_browser();
$b->do_get(test_map_url("index"));
eq(200,$b->status());
meq("INDEX",$b->body());
meq("hogehoge_xml_var",$b->body());
meq("A1A2A3",$b->body());
meq("	hogehoge_xml_var_value",$b->body());
meq("[AAA]",$b->body());
meq("[BBB]",$b->body());
meq("AAA",$b->body());
meq("BBB",$b->body());
meq("CCC",$b->body());
meq("resources/media",$b->body());
-->

<!---
# module

$b = test_browser();
$b->do_get(test_map_url("module"));
eq(200,$b->status());
meq("INDEX",$b->body());
meq("BEGIN_FLOW_HANDLE",$b->body());
meq("INIT_FLOW_HANDLE",$b->body());
meq("BEFORE_FLOW_HANDLE",$b->body());
meq("AFTER_FLOW_HANDLE",$b->body());
meq("INIT_TEMPLATE",$b->body());
meq("BEFORE_TEMPLATE",$b->body());
meq("AFTER_TEMPLATE",$b->body());
meq("BEFORE_EXEC_TEMPLATE",$b->body());
meq("AFTER_EXEC_TEMPLATE",$b->body());
meq("BEFORE_FLOW_PRINT_TEMPLATE",$b->body());
-->
<!---
# module_map

$b = test_browser();
$b->do_get(test_map_url("module_map"));
eq(200,$b->status());
meq("INDEX",$b->body());
nmeq("BEGIN_FLOW_HANDLE",$b->body());
meq("INIT_FLOW_HANDLE",$b->body());
meq("BEFORE_FLOW_HANDLE",$b->body());
meq("AFTER_FLOW_HANDLE",$b->body());
meq("INIT_TEMPLATE",$b->body());
meq("BEFORE_TEMPLATE",$b->body());
meq("AFTER_TEMPLATE",$b->body());
meq("BEFORE_EXEC_TEMPLATE",$b->body());
meq("AFTER_EXEC_TEMPLATE",$b->body());
nmeq("BEFORE_FLOW_PRINT_TEMPLATE",$b->body());
-->

<!---
# module_maps

$b = test_browser();
$b->do_get(test_map_url("module_maps"));
eq(200,$b->status());
meq("INDEX",$b->body());
nmeq("BEGIN_FLOW_HANDLE",$b->body());
meq("INIT_FLOW_HANDLE",$b->body());
meq("BEFORE_FLOW_HANDLE",$b->body());
meq("AFTER_FLOW_HANDLE",$b->body());
meq("INIT_TEMPLATE",$b->body());
meq("BEFORE_TEMPLATE",$b->body());
meq("AFTER_TEMPLATE",$b->body());
meq("BEFORE_EXEC_TEMPLATE",$b->body());
meq("AFTER_EXEC_TEMPLATE",$b->body());
nmeq("BEFORE_FLOW_PRINT_TEMPLATE",$b->body());
-->

<!---
# module_raise

$b = test_browser();
$b->do_get(test_map_url("module_raise"));
eq(200,$b->status());
nmeq("INDEX",$b->body());

meq("BEGIN_FLOW_HANDLE",$b->body());
meq("INIT_FLOW_HANDLE",$b->body());
meq("BEFORE_FLOW_HANDLE",$b->body());
meq("AFTER_FLOW_HANDLE",$b->body());

meq("INIT_TEMPLATE",$b->body());
meq("BEFORE_TEMPLATE",$b->body());
meq("AFTER_TEMPLATE",$b->body());

meq("BEFORE_FLOW_PRINT_TEMPLATE",$b->body());
meq("FLOW_HANDLE_EXCEPTION",$b->body());
-->

<!---
#raise
$b = test_browser();
$b->do_get(test_map_url("raise"));
eq(403,$b->status());
-->

<!---
# module_add_exceptions

$b = test_browser();
$b->do_get(test_map_url("module_add_exceptions"));
eq(200,$b->status());
meq("INDEX",$b->body());
meq("BEGIN_FLOW_HANDLE",$b->body());
meq("INIT_FLOW",$b->body());
meq("BEFORE_FLOW",$b->body());
meq("AFTER_FLOW",$b->body());
meq("INIT_TEMPLATE",$b->body());
meq("BEFORE_TEMPLATE",$b->body());
meq("AFTER_TEMPLATE",$b->body());
meq("BEFORE_FLOW_PRINT_TEMPLATE",$b->body());
meq("FLOW_HANDLE_EXCEPTION",$b->body());
-->


<!---
# under_var
$b = test_browser();
$b->do_get(test_map_url("under_var"));
eq(200,$b->status());
meq("hogehoge",$b->body());
meq("ABC",$b->body());
-->

<!---
# module_order
$b = test_browser();
$b->do_get(test_map_url("module_order"));
eq(200,$b->status());
eq("12345678910",$b->body());
-->

<!---
# redirect_by_map
$b = test_browser();
$b->do_get(test_map_url("redirect_by_map_method_a"));
eq(200,$b->status());
eq("REDIRECT_A",$b->body());

$b->do_get(test_map_url("redirect_by_map_method_b"));
eq(200,$b->status());
eq("REDIRECT_B",$b->body());

$b->do_get(test_map_url("redirect_by_map_method_c"));
eq(200,$b->status());
eq("REDIRECT_C",$b->body());
-->

<!---
#notemplate
$b = test_browser();
$b->do_get(test_map_url("notemplate"));
eq(200,$b->status());
eq("<result><abc>ABC</abc><newtag><hoge>HOGE</hoge></newtag></result>",$b->body());
-->

<!---

#login
$b = test_browser();
$b->do_get(test_map_url("login"));
eq(200,$b->status());
eq('<result />',$b->body());

$b = test_browser();
$b->do_post(test_map_url("login"));
eq(401,$b->status());
eq('<error><message group="do_login" type="LogicException">Unauthorized</message></error>',$b->body());

$b = test_browser();
$b->vars("user_name","aaaa");
$b->vars("password","bbbb");
$b->do_get(test_map_url("login"));
eq(200,$b->status());
eq('<result><user_name>aaaa</user_name><password>bbbb</password></result>',$b->body());

$b = test_browser();
$b->vars("user_name","aaaa");
$b->vars("password","bbbb");
$b->do_post(test_map_url("login"));
eq(401,$b->status());
eq('<error><message group="do_login" type="LogicException">Unauthorized</message></error>',$b->body());

$b = test_browser();
$b->vars("user_name","hogeuser");
$b->vars("password","hogehoge");
$b->do_post(test_map_url("login"));
eq('<result><user_name>hogeuser</user_name></result>',$b->body());

-->

<!---
#not_url_method
$b = test_browser();
eq("/not_url_method",substr(test_map_url("not_url_method"),-15));
$b->do_get(test_map_url("not_url_method"));
eq(200,$b->status());
eq("<result><hoge>123</hoge></result>",$b->body());
-->

<!---
#not_method
$b = test_browser();
eq("/not/abcd",substr(test_map_url("not_method"),-9));
$b->do_get(test_map_url("not_method"));
eq(200,$b->status());
eq("<result><hoge>456</hoge></result>",$b->body());
-->

<!---
#not_method_empty_url
$b = test_browser();
eq("/not",substr(test_map_url("not_method_empty_url"),-4));
$b->do_get(test_map_url("not_method_empty_url"));
eq(200,$b->status());
eq("<result><hoge>789</hoge></result>",$b->body());
-->

<!---
#module_throw_exception
$b = test_browser();
$b->do_get(test_map_url("module_throw_exception"));
eq(200,$b->status());
eq('<error><message group="exceptions" type="LogicException">flow handle begin exception</message></error>',$b->body());

-->

<!---
#noop
$b = test_browser();
$b->do_get(test_map_url("noop"));
eq(200,$b->status());
eq("<result><init_var>INIT</init_var></result>",$b->body());
-->

<!---
#method_not_allowed
$b = test_browser();
$b->do_get(test_map_url("method_not_allowed"));
eq(405,$b->status());
eq('<error><message group="exceptions" type="LogicException">Method Not Allowed</message></error>',$b->body());
-->

<!---
#login_required_exception
$b = test_browser();
$b->do_get(test_map_url("login_required_exception"));
eq(401,$b->status());
eq('<error><message group="do_login" type="LogicException">Unauthorized</message></error>',$b->body());
-->

<!---
# vars_xml
$b = test_browser();
$b->do_get(test_map_url("vars_xml"));
eq('<result><hoge>123456789</hoge><fuga>ABCDEFG</fuga></result>',$b->body());
-->

<!---
# trans
$b = test_browser();
$b->do_get(test_map_url("trans"));
eq('It\'s a dreams come "true.\\aaa',trim($b->body()));

$b = test_browser();
$b->header("Accept-Language","ja-jp");
$b->do_get(test_map_url("trans"));
eq('ああ\'アアお"オ\\尾',trim($b->body()));
-->


<!---
# put_block
$b = test_browser();
$b->do_get(test_map_url("put_block"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('NONE',$b->body());

$b = test_browser();
$b->vars("hoge","a");
$b->do_get(test_map_url("put_block"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('a',$b->body());
nmeq('CCC',$b->body());

$b = test_browser();
$b->vars("hoge","b");
$b->do_get(test_map_url("put_block"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('b',$b->body());
nmeq('CCC',$b->body());
-->

<!---
# theme
$b = test_browser();
$b->do_get(test_map_url("theme"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('NONE',$b->body());
meq('/resources/media/custom_theme/default/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","aaa");
$b->do_get(test_map_url("theme"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('xxx',$b->body());
meq('/resources/media/custom_theme/aaa/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","bbb");
$b->do_get(test_map_url("theme"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('yyy',$b->body());
meq('/resources/media/custom_theme/bbb/123">a</a>',$b->body());
-->

<!---
# theme_handler
$b = test_browser();
$b->do_get(test_map_url("theme_handler"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('NONE',$b->body());
meq('/resources/media/custom_theme/default/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","aaa");
$b->do_get(test_map_url("theme_handler"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('xxx',$b->body());
meq('/resources/media/custom_theme/aaa/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","bbb");
$b->do_get(test_map_url("theme_handler"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('yyy',$b->body());
meq('/resources/media/custom_theme/bbb/123">a</a>',$b->body());
-->

<!---
# theme_maps
$b = test_browser();
$b->do_get(test_map_url("theme_maps"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('NONE',$b->body());
meq('/resources/media/custom_theme/default/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","aaa");
$b->do_get(test_map_url("theme_maps"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('xxx',$b->body());
meq('/resources/media/custom_theme/aaa/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","bbb");
$b->do_get(test_map_url("theme_maps"));
meq('AAA',$b->body());
meq('BBB',$b->body());
meq('yyy',$b->body());
meq('/resources/media/custom_theme/bbb/123">a</a>',$b->body());
-->

<!---
# theme_parent
$b = test_browser();
$b->do_get(test_map_url("theme_parent"));
meq('aaa',$b->body());
meq('bbb',$b->body());
meq('none',$b->body());
meq('/resources/media/custom_theme_handler/custom_theme_maps/custom_theme/default/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","aaa");
$b->do_get(test_map_url("theme_parent"));
meq('aaa',$b->body());
meq('bbb',$b->body());
meq('XXX',$b->body());
meq('/resources/media/custom_theme_handler/custom_theme_maps/custom_theme/aaa/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","bbb");
$b->do_get(test_map_url("theme_parent"));
meq('aaa',$b->body());
meq('bbb',$b->body());
meq('YYY',$b->body());
meq('/resources/media/custom_theme_handler/custom_theme_maps/custom_theme/bbb/123">a</a>',$b->body());
-->

<!---
# theme_empty
$b = test_browser();
$b->do_get(test_map_url("theme_empty"));
meq('aaa',$b->body());
meq('bbb',$b->body());
meq('none',$b->body());
meq('/resources/media/default/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","aaa");
$b->do_get(test_map_url("theme_empty"));
meq('aaa',$b->body());
meq('bbb',$b->body());
meq('XXX',$b->body());
meq('/resources/media/aaa/123">a</a>',$b->body());

$b = test_browser();
$b->vars("hoge","bbb");
$b->do_get(test_map_url("theme_empty"));
meq('aaa',$b->body());
meq('bbb',$b->body());
meq('YYY',$b->body());
meq('/resources/media/bbb/123">a</a>',$b->body());
-->

<!---
# theme_none
$b = test_browser();
$b->do_get(test_map_url("theme_none"));
meq('error',$b->body());

$b = test_browser();
$b->vars("hoge","aaa");
$b->do_get(test_map_url("theme_none"));
meq('error',$b->body());

$b = test_browser();
$b->vars("hoge","bbb");
$b->do_get(test_map_url("theme_none"));
meq('error',$b->body());
-->

<!---
# sample_flow

$b = test_browser();

$b->do_get(test_map_url("sample_flow_html"));
eq('SAMPLE',$b->body());

$b->do_get(test_map_url("template_path_html"));
eq('INDEX',$b->body());

$b->do_get(test_map_url("template_path_theme_html"));
eq('THEME',$b->body());

$b->do_get(test_map_url("sample_flow_theme_index"));
eq('DEFAULT',$b->body());
$b->do_get(test_map_url("sample_flow_theme_index").'?view=blue');
eq('BLUE',$b->body());
$b->do_get(test_map_url("sample_flow_theme_index").'?view=red');
eq('RED',$b->body());
$b->do_get(test_map_url("sample_flow_theme_index").'?view=green');
eq('DEFAULT',$b->body());

$b->do_get(test_map_url("sample_flow_theme_hoge"));
eq('DEFAULT',$b->body());


$b->do_get(test_map_url("sample_flow_theme_not_index"));
eq('SAMPLE',$b->body());
$b->do_get(test_map_url("sample_flow_theme_not_index").'?view=blue');
eq('blue',$b->body());
$b->do_get(test_map_url("sample_flow_theme_not_index").'?view=red');
eq('red',$b->body());
$b->do_get(test_map_url("sample_flow_theme_not_index").'?view=green');
eq('default',$b->body());

$b->do_get(test_map_url("sample_flow_theme_not_hoge"));
eq('SAMPLE',$b->body());


$b->do_get(test_map_url("sample_flow/index"));
eq('SAMPLE_FLOW_INDEX',$b->body());
$b->do_get(test_map_url("sample_flow/index").'?view=blue');
eq('SAMPLE_FLOW_BLUE',$b->body());
$b->do_get(test_map_url("sample_flow/index").'?view=red');
eq('SAMPLE_FLOW_RED',$b->body());
$b->do_get(test_map_url("sample_flow/index").'?view=green');
eq('SAMPLE_FLOW_DEFAULT',$b->body());

$b->do_get(test_map_url("sample_flow/hoge"));
eq('SAMPLE_FLOW_HOGE',$b->body());
-->

<!---
# sample_media_flow

$b = test_browser();

$b->do_get(test_map_url("sample_media_flow/index"));
if(preg_match("/\/package\/resources\/media\/\d+\/\d+\/hoge.jpg/",$b->body())){
	success();
}else{
	fail();
}
$b->do_get(test_map_url("sample_media_flow/hoge"));
if(preg_match("/\/package\/resources\/media\/\d+\/\d+\/hoge.jpg/",$b->body())){
	success();
}else{
	fail();
}

$b->do_get(test_map_url("sample_flow_theme_media_index"));
meq('resources/media/theme_path/default/hoge.jpg',$b->body());
$b->do_get(test_map_url("sample_flow_theme_media_index")."?view=blue");
meq('resources/media/theme_path/blue/hoge.jpg',$b->body());
$b->do_get(test_map_url("sample_flow_theme_media_index")."?view=red");
meq('resources/media/theme_path/red/hoge.jpg',$b->body());

$b->do_get(test_map_url("sample_flow_theme_media_hoge"));
meq('resources/media/theme_path/default/hoge.jpg',$b->body());


$b->do_get(test_map_url("sample_flow_theme_media_index"));
meq('resources/media/theme_path/default/hoge.jpg',$b->body());

$b->do_get(test_map_url("sample_flow_theme_media_hoge"));
meq('resources/media/theme_path/default/hoge.jpg',$b->body());

$b->do_get(test_map_url("sample_flow_theme_media_plain_index"));
meq('/resources/media/media_path/hoge.jpg',$b->body());

$b->do_get(test_map_url("sample_flow_theme_media_plain_hoge"));
meq('/resources/media/media_path/hoge.jpg',$b->body());
-->

<!---
# sample_exception_flow

$b = test_browser();

$b->do_get(test_map_url("sample_flow_exception_throw"));
eq("ERROR",$b->body());
-->


<!---
# template_super
$b = test_browser();
$b->do_get(test_map_url("template_super_a"));
eq('abcd',$b->body());

$b->do_get(test_map_url("template_super_b"));
eq('xc',$b->body());
-->


