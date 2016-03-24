<?php
return array(
    "user" => "open",
    "php_bin" => "/bin/php",
    "base_dir" => "/home/open/tinypub", //subdir (data, project, server), need to create

    //tag has permission
    "reserve_tag" => array(
        "tags" => array("t0"),
        "auth" => "__auth__",
    ),

    "projects" => array(
        "openbox" => array(
        ),
        "zhushou-api" => array(
        )
    ),
);
