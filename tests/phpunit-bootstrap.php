<?php

/*
 * Composer Autoloader
 */

call_user_func(function() {
    $autoloadPaths = array(
        __DIR__ . '/../../../autoload.php',  // composer dependency
        __DIR__ . '/../vendor/autoload.php', // stand-alone package
    );

    foreach($autoloadPaths as $path) {
        if(file_exists($path)) {
            require($path);
            break;
        }
    }

    // if we didn't find the autoloader, your autoloader is in a stupid place,
    // and you're on your own
});

/*
 * Config
 */

call_user_func(function() {
    $envParams = [
        "SOURCE_SSH_USER",
        "SOURCE_SSH_HOST",
        "SOURCE_SSH_PORT",
        "SOURCE_TMP",

        "DEST_SSH_USER",
        "DEST_SSH_HOST",
        "DEST_SSH_PORT",
        "DEST_TMP",

        "LOCAL_TMP",
    ];

    foreach($envParams as $param) {
        defined($param) || define($param, getenv($param));
    }
});