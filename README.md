# SSH Copy

A library for performing remote copies. Copy to/from local, to/from remote,
and even between two remote machines.

## Install

```
curl -s http://getcomposer.org/installer | php
php composer.phar require cullylarson/ssh-copy
```

## Construct

The constructor takes three parameters:

1. **$sshSource** _(resource)_ _(optional, default:null)_ An SSH connection resource.
If null, will assume the source is the local machine. If non-null, will assume the
source is a remote machine.

1. **$sshDestination** _(resource)_ _(optional, default:null)_ An SSH connection resource.
If null, will assume the destination is the local machine. If non-null, will assume the
destination is a remote machine.

1. **$localTmp** _(string)_ _(optional, default:null)_ If copying between two remote machines,
the copy will first transfer the files to from the remote source, to the local machine,
and then to the remote destination.  So, you need to provide a temporary folder to house
the files locally.

## Copy

The `copy` function takes two arguments:

1. **$sourceFilepath** _(string|array)_ _(required)_ The path to the file that you want to copy
from the source machine.  Instead of passing a single paths, you can pass an array of paths to
files you want to copy.  If an array is provided, the **$destFilepath** parameter must also
be an array of the same length.

1. **$destFilepath** _(string|array)_ _(required)_ The path on the destination machine, where you
want the file copied.  Instead of passing a single paths, you can pass an array of paths to
files you want to copy.  If an array is provided, the **$sourceFilepath** parameter must also
be an array of the same length.

## Examples

NOTE:  If you're using RSA for the examples below, and you got an auth error,
you might need to run this command:
       
    $ eval `ssh-agent -s` && ssh-add

### Local to Local Copy

```
<?php

$copier = new Cully\Ssh\Copier();
$copier->copy("path/to/source", "path/to/dest");
```
    
### Local to Remote Copy

```
<?php

$destSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa')) or die("Couldn't connect.");
ssh2_auth_agent($destSsh, "my_username") or die("Couldn't authenticate.");

$copier = new Cully\Ssh\Copier(null, $destSsh);
$copier->copy("path/to/source/on/local", "path/to/dest/on/remote");
```
    
### Remote to Remote Copy

```
<?php

$sourceSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa')) or die("Couldn't connect.");
ssh2_auth_agent($sourceSsh, "my_username") or die("Couldn't authenticate.");

$destSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa')) or die("Couldn't connect.");
ssh2_auth_agent($destSsh, "my_username") or die("Couldn't authenticate.");

$copier = new Cully\Ssh\Copier($sourceSsh, $destSsh, "/local/tmp/folder");
$copier->copy("path/on/remote/source", "path/on/remote/dest");
```
    
### Remote to Remote Copy of Multiple Files

```
<?php

$sourceSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa')) or die("Couldn't connect.");
ssh2_auth_agent($sourceSsh, "my_username") or die("Couldn't authenticate.");

$destSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa')) or die("Couldn't connect.");
ssh2_auth_agent($destSsh, "my_username") or die("Couldn't authenticate.");

$copier = new Cully\Ssh\Copier($sourceSsh, $destSsh, "/local/tmp/folder");
$copier->copy(
    [ "path/on/remote/source/file1", "path/on/remote/source/file2" ],
    [ "path/on/remote/dest/file1", "path/on/remote/dest/file2" ]
);
```