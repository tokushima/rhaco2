<?php
$b = test_browser();
$b->do_get(test_map_url("test_index::index"));
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



$b = test_browser();
$b->do_get(test_map_url("test_login::B"));
eq(test_map_url("test_login::login"),$b->url());

$b->submit();
eq(test_map_url("test_login::B"),$b->url());
eq(200,$b->status());
meq("hoge",$b->body());

$b->do_get(test_map_url("test_login::B"));
eq(200,$b->status());
meq("hoge",$b->body());


