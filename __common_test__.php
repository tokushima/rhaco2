<?php
C(Log)->add_module(R("org.rhaco.io.log.LogFile"));
C(Log)->add_module(R("org.rhaco.io.log.LogGrowl"));

// ロール root/rootでDBを作成する
// rhacotest1-9までDBを作る
$db = "mysql";

switch($db){
	case "mysql":
		def("org.rhaco.storage.db.Dbc@test_1","type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest1,user=root,password=root,encode=utf8");
		def("org.rhaco.storage.db.Dbc@test_2","type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest2,user=root,password=root,encode=utf8");
		
		def("org.rhaco.storage.db.Dbc@test_3"
			,"type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest3,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest4,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest5,user=root,password=root,encode=utf8"
		);
		
		def("org.rhaco.storage.db.Dbc@test_4#master","type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest6,user=root,password=root,encode=utf8");
		def("org.rhaco.storage.db.Dbc@test_4#slave"
			,"type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest7,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest8,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcMysql,dbname=rhacotest9,user=root,password=root,encode=utf8"
		);
		break;
	case "mysqlb":
		def("org.rhaco.storage.db.Dbc@test_1","type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest1,user=root,password=root,encode=utf8");
		def("org.rhaco.storage.db.Dbc@test_2","type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest2,user=root,password=root,encode=utf8");
		
		def("org.rhaco.storage.db.Dbc@test_3"
			,"type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest3,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest4,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest5,user=root,password=root,encode=utf8"
		);
		
		def("org.rhaco.storage.db.Dbc@test_4#master","type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest6,user=root,password=root,encode=utf8");
		def("org.rhaco.storage.db.Dbc@test_4#slave"
			,"type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest7,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest8,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcMysqlB,dbname=rhacotest9,user=root,password=root,encode=utf8"
		);
		break;
	case "sqlite":
		def("org.rhaco.storage.db.Dbc@test_1","type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest1,user=root,password=root,encode=utf8,host=".work_path());
		def("org.rhaco.storage.db.Dbc@test_2","type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest2,user=root,password=root,encode=utf8,host=".work_path());

		def("org.rhaco.storage.db.Dbc@test_3"
			,"type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest1,user=root,password=root,encode=utf8,host=".work_path()
			,"type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest1,user=root,password=root,encode=utf8,host=".work_path()
			,"type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest1,user=root,password=root,encode=utf8,host=".work_path()
		);
		
		def("org.rhaco.storage.db.Dbc@test_4#master","type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest4,user=root,password=root,encode=utf8,host=".work_path());
		def("org.rhaco.storage.db.Dbc@test_4#slave"
			,"type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest5,user=root,password=root,encode=utf8,host=".work_path()
			,"type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest6,user=root,password=root,encode=utf8,host=".work_path()
			,"type=org.rhaco.storage.db.module.DbcSqlite,dbname=rhacotest7,user=root,password=root,encode=utf8,host=".work_path()
		);
		break;
	case "postgres":
		// @see http://www.enterprisedb.com/products/pgdownload.do#osx
		def("org.rhaco.storage.db.Dbc@test_1","type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest1,user=root,password=root,encode=utf8");
		def("org.rhaco.storage.db.Dbc@test_2","type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest2,user=root,password=root,encode=utf8");
		
		def("org.rhaco.storage.db.Dbc@test_3"
			,"type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest3,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest4,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest5,user=root,password=root,encode=utf8"
		);
		
		def("org.rhaco.storage.db.Dbc@test_4#master","type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest6,user=root,password=root,encode=utf8");
		def("org.rhaco.storage.db.Dbc@test_4#slave"
			,"type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest7,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest8,user=root,password=root,encode=utf8"
			,"type=org.rhaco.storage.db.module.DbcPgsql,dbname=rhacotest9,user=root,password=root,encode=utf8"
		);
		break;
}
