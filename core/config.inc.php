<?php
define("SL_BASE",dirname(dirname(__FILE__)));
define("SL_CORE",SL_BASE."/core/");
define("SL_TEMPLATES",SL_BASE."/templates/");
define("SL_CACHE",SL_CORE."/cache/");
define("SL_TESTS",SL_CORE."/tests/");
define("SL_MODULES",SL_CORE."/modules/");
define("SL_SECRET","Change me to something else");
//define("SL_TEMPLATES_CACHE",SL_CACHE.'templates');
define("SL_TEMPLATES_CACHE",false);
define("SL_DB_TYPE","mysql");
define("SL_DB_HOST","localhost");
define("SL_DB_USER","username");
define("SL_DB_PASS","database_name");
define("SL_DB_NAME","database_name");
define("SL_DB_PREFIX","sl_");
