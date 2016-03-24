<?php

return array(
    "auth_file" => "~/.tinypub",
    "servers" => array(
        "s1" => array(
            "host" => "node288v.add.bjcc.qihoo.net",
            "user" => "open",
            "php_bin" => "/bin/php",
            "base_dir" => "/home/open/tinypub",
        )
    ),
    "projects" => array(
        "openbox" => array(
            "server" => "s1",
        ),
        "zhushou-api" => array(
            "server" => "s1",
        )
    ),
);
