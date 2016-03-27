<?php
return array(
    "base_dir" => "/home/open/tinypub", //storage dir. subdir (data, project, server), need to create previously
    "base_domain" => "xxx.xxx.com", //base domain for test
    "log_file" => "/tmp/tinypub.log",
    "user" => "open", //sudo user
    "history_limit" => 5, //save last 5 history data
    "port_min" => 10000, //docker auto port
    "nginx_include_dir" => "/etc/nginx/conf.d",

    "php_bin" => "/bin/php",
    "nginx_bin" => "/sbin/nginx",
    "lsof_bin" => "/sbin/lsof", //check port occupy

    //tag has permission
    "reserve_tag" => array(
        "tags" => array("t0"),
        "auth" => "__auth__", //specail tag password
    ),

    "projects" => array(
        "project1" => array(
            "host" => "xxx.xxx.xxx.org", //realhost
            "image_name" => "proejct1:test", //docker images
            "map_dir" => "/usr/share/nginx/html",
        )
    ),
);
