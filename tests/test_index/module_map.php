<?php
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


