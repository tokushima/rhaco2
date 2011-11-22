<?php
$b = b();
$b->do_get(test_map_url("vars_xml"));
eq(200,$b->status());
eq('<result><hoge>123456789</hoge><fuga>ABCDEFG</fuga></result>',$b->body());

if(xml($xml,$b->body(),'result')){
	eq('123456789',$xml->f('hoge.value()'));
	eq('ABCDEFG',$xml->f('fuga.value()'));
}else{
	eq(false,true);
}

