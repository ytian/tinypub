<?php
return array(
    "user" => "open",
    "php_bin" => "/bin/php",
    "base_dir" => "/home/open/tinypub", //subdir (data, project, server), need to create
    "history_limit" => 5, //save last 5 history data
    "port_min" => 8000, //web min port
    "nginx_include_dir" => "/etc/nginx/conf/include",

    //tag has permission
    "reserve_tag" => array(
        "tags" => array("t0"),
        "auth" => "__auth__",
    ),

    "projects" => array(
        "openbox" => array(
            "host" => "openbox.mobilem.360.cn",
            "image_name" => "openbox:test",
        ),
        "zhushou-api" => array(
        )
    ),
);
