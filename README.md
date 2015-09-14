# SSH Copy

A library for performing remote copies. Copy to/from local, to/from remote,
and even between two remote machines.

## Install

```
curl -s http://getcomposer.org/installer | php
php composer.phar require cullylarson/ssh-copy
```

## Construct

You'll do everything with an instance of `Cully\Ssh\Copier`. Its constructor takes three
parameters:

1. **$sshSource** _(resource|null)_ _(optional, default:null)_ An SSH connection resource.
If null, will assume the source is the local machine. If its a resource, will assume the
source is a remote machine.

1. **$sshDestination** _(resource|null)_ _(optional, default:null)_ An SSH connection resource.
If null, will assume the destination is the local machine. If its a resource, will assume the
destination is a remote machine.

1. **$localTmp** _(string|null)_ _(optional, default:null)_ If copying between two remote machines,
the copy will first transfer the files to from the remote source, to the local machine,
and then to the remote destination.  So, you need to provide a temporary folder to house
the files locally.

## `copy`

The `Cully\Ssh\Copier::copy` function takes two arguments:

1. **$sourceFilepath** _(string|array)_ _(required)_ The path to the file that you want to copy
from the source machine.  Instead of passing a single path, you can pass an array of paths to
files you want to copy.  If an array is provided, the **$destFilepath** parameter must also
be an array of the same length.

1. **$destFilepath** _(string|array)_ _(required)_ The path on the destination machine, where you
want the file copied.  Instead of passing a single path, you can pass an array of paths to
files you want to copy.  If an array is provided, the **$sourceFilepath** parameter must also
be an array of the same length.

**Returns:** _boolean_ True on success, false on fail.

NOTE: Currently the `copy` function DOES NOT create parent folders.  The folders must already exist.
Maybe something for a future release.

## `copyAssoc`

The `Cully\Ssh\Copier::copyAssoc` function is similar to `copy`, except it takes one argument:

1. **$sourceAndDest** _(array)_ _(required)_ An associative array where keys are source file paths,
and values are destination file paths.

**Returns:** _boolean_ True on success, false on fail.

## Examples

### Setup SSH Connections

```
<?php

$sourceSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa'));
ssh2_auth_agent($sourceSsh, "my_username");

$destSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa'));
ssh2_auth_agent($destSsh, "my_username");
```

NOTE:  If you're using RSA for the examples below, and you get an auth error,
you might need to run this command:
       
    $ eval `ssh-agent -s` && ssh-add

### Copy / Local to Local

```
<?php

$copier = new Cully\Ssh\Copier();
$copier->copy("path/to/source", "path/to/dest");
```
    
### Copy / Local to Remote

```
<?php

$copier = new Cully\Ssh\Copier(null, $destSsh);
$copier->copy("path/to/source/on/local", "path/to/dest/on/remote");
```

### Copy / Remote to Local

```
<?php

$copier = new Cully\Ssh\Copier($sourceSsh, null);
$copier->copy("path/to/source/on/remote", "path/to/dest/on/local");
```
    
### Copy / Remote to Remote

```
<?php

$copier = new Cully\Ssh\Copier($sourceSsh, $destSsh, "/local/tmp/folder");
$copier->copy("path/on/remote/source", "path/on/remote/dest");
```
    
### Copy / Remote to Remote with Multiple Files

```
<?php

$copier = new Cully\Ssh\Copier($sourceSsh, $destSsh, "/local/tmp/folder");
$copier->copy(
    [ "path/on/remote/source/file1", "path/on/remote/source/file2" ],
    [ "path/on/remote/dest/file1", "path/on/remote/dest/file2" ]
);
```

### Copy / Remote to Remote with Multiple Files using `copyAssoc`

```
<?php

$copier = new Cully\Ssh\Copier($sourceSsh, $destSsh, "/local/tmp/folder");
$copier->copyAssoc([
    "path/on/remote/source/file1" => "path/on/remote/dest/file1",
    "path/on/remote/source/file2" => "path/on/remote/dest/file2"
]);
```
