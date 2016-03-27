<?php
return array(
    "log_file" => "/tmp/tinypub.log",
    "auth_file" => "~/.tinypub", //user password
    "unsync_files" => array(
        ".git*",
        ".svn*",
        "**/.svn*",
        "**/.git*",
    ),
    "servers" => array(
        "s1" => array(
            "host" => "xxx.xxx.xxx.xxx", // server ip
            "user" => "open", //sudo user
            "php_bin" => "/bin/php",
            "base_dir" => "/home/open/tinypub", //storage dir
        )
    ),
    "projects" => array(
        "project1" => array(
            "server" => "s1",
        )
    ),
);
