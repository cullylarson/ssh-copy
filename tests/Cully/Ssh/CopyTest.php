<?php

namespace Test\Cully\Ssh;

use Cully\Ssh\Copier;
use Cully\Ssh;
use Cully\Local;

class CopyTest extends \PHPUnit_Framework_TestCase {
    private $sourceSsh;
    private $destSsh;

    public function setUp() {
        /*
         * Create source connection
         */

        $sourceSsh = ssh2_connect(getenv("SOURCE_SSH_HOST"), getenv("SOURCE_SSH_PORT"), array('hostkey'=>'ssh-rsa'));

        if( !is_resource($sourceSsh) ) {
            $this->markTestSkipped("Could not connect to source.");
        }

        if( !@ssh2_auth_agent($sourceSsh, getenv("SOURCE_SSH_USER")) ) {
            $this->markTestSkipped("Couldn't authenticate on source. You might need to: eval `ssh-agent -s` && ssh-add");
        }

        $this->sourceSsh = $sourceSsh;

        /*
         * Create destination connection
         */

        $destSsh = ssh2_connect(getenv("DEST_SSH_HOST"), getenv("DEST_SSH_PORT"), array('hostkey'=>'ssh-rsa'));

        if( !is_resource($destSsh) ) {
            $this->markTestSkipped("Could not connect to destination.");
        }

        if( !@ssh2_auth_agent($destSsh, getenv("DEST_SSH_USER")) ) {
            $this->markTestSkipped("Couldn't authenticate on destination. You might need to: eval `ssh-agent -s` && ssh-add");
        }

        $this->destSsh = $destSsh;
    }

    private function createRemoteSourceFile($name, $contents="") {
        $command = new Ssh\Command($this->sourceSsh);

        $filePath = $this->getSourcePath($name);

        $command->exec("echo -n " . escapeshellarg($contents) . " >" . escapeshellarg($filePath));

        if($command->failure()) {
            $this->markTestSkipped("Could not create remote source file.");
        }

        return $filePath;
    }

    private function createRemoteSourceFolder($name) {
        $command = new Ssh\Command($this->sourceSsh);

        $folderPath = $this->getSourcePath($name);

        $command->exec("mkdir " . escapeshellarg($folderPath));

        if($command->failure()) {
            $this->markTestSkipped("Could not create remote source folder.");
        }

        return $folderPath;
    }

    private function removeRemoteSourceFolder($folderPath) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("rmdir " . escapeshellarg($folderPath));
    }

    private function removeRemoteSourceFile($filePath) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("rm " . escapeshellarg($filePath));
    }

    private function createRemoteDestFile($name, $contents="") {
        $command = new Ssh\Command($this->destSsh);

        $filePath = $this->getDestPath($name);

        $command->exec("echo -n " . escapeshellarg($contents) . " >" . escapeshellarg($filePath));

        if($command->failure()) {
            $this->markTestSkipped("Could not create remote dest file.");
        }

        return $filePath;
    }

    private function createRemoteDestFolder($name) {
        $command = new Ssh\Command($this->destSsh);

        $folderPath = $this->getDestPath($name);

        $command->exec("mkdir " . escapeshellarg($folderPath));

        if($command->failure()) {
            $this->markTestSkipped("Could not create remote dest folder.");
        }

        return $folderPath;
    }

    private function removeRemoteDestFolder($folderPath) {
        $command = new Ssh\Command($this->destSsh);
        $command->exec("rmdir " . escapeshellarg($folderPath));
    }

    private function removeRemoteDestFile($filePath) {
        $command = new Ssh\Command($this->destSsh);
        $command->exec("rm " . escapeshellarg($filePath));
    }

    private function createLocalFile($name, $contents="") {
        $command = new Local\Command();
        $filePath = $this->getLocalPath($name);
        $command->exec("echo -n " . escapeshellarg($contents) . " >" . escapeshellarg($filePath));

        if($command->failure()) {
            $this->markTestSkipped("Could not create local file.");
        }

        return $filePath;
    }

    private function assertLocalFileExists($filePath, $contents=null) {
        $command = new Local\Command();
        $command->exec("cat " . escapeshellarg($filePath));
        $this->assertTrue($command->success());

        if($contents !== null) {
            $this->assertEquals($command->getOutput(), $contents);
        }
    }

    private function assertLocalFileDoesntExist($filePath) {
        $command = new Local\Command();
        $command->exec("cat " . escapeshellarg($filePath));
        $this->assertFalse($command->success());
    }

    private function assertSourceFileExists($filePath, $contents=null) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("cat " . escapeshellarg($filePath));
        $this->assertTrue($command->success());

        if($contents !== null) {
            $this->assertEquals($command->getOutput(), $contents);
        }
    }

    private function assertSourceFileDoesntExist($filePath) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("cat " . escapeshellarg($filePath));
        $this->assertFalse($command->success());
    }

    private function assertDestFileExists($filePath, $contents=null) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("cat " . escapeshellarg($filePath));
        $this->assertTrue($command->success());

        if($contents !== null) {
            $this->assertEquals($command->getOutput(), $contents);
        }
    }

    private function assertDestFileDoesntExist($filePath) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("cat " . escapeshellarg($filePath));
        $this->assertFalse($command->success());
    }

    private function removeLocalFile($filePath) {
        $command = new Local\Command();
        $command->exec("rm " . escapeshellarg($filePath));
    }

    private function getLocalPath($name) {
        $path = getenv("LOCAL_TMP");

        return "{$path}/{$name}";
    }

    private function getSourcePath($name) {
        $path = getenv("SOURCE_TMP");

        return "{$path}/{$name}";
    }

    private function getDestPath($name) {
        $path = getenv("DEST_TMP");

        return "{$path}/{$name}";
    }

    public function testLocalToLocal() {
        $copier = new Copier();
        $localFile1 = $this->createLocalFile("blah.txt");
        $localFile2 = $this->getLocalPath("blah2.txt");

        $success = $copier->copy($localFile1, $localFile2);

        $this->assertLocalFileExists($localFile2);

        $this->removeLocalFile($localFile1);
        $this->removeLocalFile($localFile2);

        $this->assertTrue($success);
    }

    public function testLocalToRemote() {
        $copier = new Copier(null, $this->destSsh);
        $localFile = $this->createLocalFile("blah.txt");
        $destFile = $this->getDestPath(basename($localFile));

        $success = $copier->copy($localFile, $destFile);

        $this->assertDestFileExists($destFile);

        $this->removeLocalFile($localFile);
        $this->removeRemoteDestFile($destFile);

        $this->assertTrue($success);
    }

    public function testRemoteToLocal() {
        $copier = new Copier($this->sourceSsh, null);
        $sourceFile = $this->createRemoteSourceFile("blah.txt");
        $localFile = $this->getLocalPath(basename($sourceFile));

        $success = $copier->copy($sourceFile, $localFile);

        $this->assertLocalFileExists($localFile);

        $this->removeLocalFile($localFile);
        $this->removeRemoteSourceFile($sourceFile);

        $this->assertTrue($success);
    }

    public function testRemoteToRemote() {
        $copier = new Copier($this->sourceSsh, $this->destSsh, getenv("LOCAL_TMP"));
        $sourceFile = $this->createRemoteSourceFile("blah.txt");
        $destFile = $this->getDestPath(basename($sourceFile));

        $success = $copier->copy($sourceFile, $destFile);

        $this->assertDestFileExists($destFile);

        $this->removeRemoteSourceFile($sourceFile);
        $this->removeRemoteDestFile($destFile);

        $this->assertTrue($success);
    }

    public function testLocalToLocalArray() {
        $copier = new Copier();
        $localFile1 = [$this->createLocalFile("blah.txt"), $this->createLocalFile("blah2.txt")];
        $localFile2 = [$this->getLocalPath("foo.txt"), $this->getLocalPath("foo2.txt")];

        $success = $copier->copy($localFile1, $localFile2);

        $this->assertLocalFileExists($localFile2[0]);
        $this->assertLocalFileExists($localFile2[1]);

        $this->removeLocalFile($localFile1[0]);
        $this->removeLocalFile($localFile1[1]);
        $this->removeLocalFile($localFile2[0]);
        $this->removeLocalFile($localFile2[1]);

        $this->assertTrue($success);
    }

    public function testLocalToRemoteArray() {
        $copier = new Copier(null, $this->destSsh);
        $localFile = [ $this->createLocalFile("blah.txt"), $this->createLocalFile("blah2.txt") ];
        $destFile = [ $this->getDestPath(basename($localFile[0])), $this->getDestPath(basename($localFile[1])) ];

        $success = $copier->copy($localFile, $destFile);

        $this->assertDestFileExists($destFile[0]);
        $this->assertDestFileExists($destFile[1]);

        $this->removeLocalFile($localFile[0]);
        $this->removeLocalFile($localFile[1]);
        $this->removeRemoteDestFile($destFile[0]);
        $this->removeRemoteDestFile($destFile[1]);

        $this->assertTrue($success);
    }

    public function testRemoteToLocalArray() {
        $copier = new Copier($this->sourceSsh, null);
        $sourceFile = [$this->createRemoteSourceFile("blah.txt"), $this->createRemoteSourceFile("blah2.txt")];
        $localFile = [$this->getLocalPath(basename($sourceFile[0])), $this->getLocalPath(basename($sourceFile[1]))];

        $success = $copier->copy($sourceFile, $localFile);

        $this->assertLocalFileExists($localFile[0]);
        $this->assertLocalFileExists($localFile[1]);

        $this->removeLocalFile($localFile[0]);
        $this->removeLocalFile($localFile[1]);
        $this->removeRemoteSourceFile($sourceFile[0]);
        $this->removeRemoteSourceFile($sourceFile[1]);

        $this->assertTrue($success);
    }

    public function testRemoteToRemoteArray() {
        $copier = new Copier($this->sourceSsh, $this->destSsh, getenv("LOCAL_TMP"));
        $sourceFile = [$this->createRemoteSourceFile("blah.txt"), $this->createRemoteSourceFile("blah2.txt")];
        $destFile = [$this->getDestPath(basename($sourceFile[0])), $this->getDestPath(basename($sourceFile[1]))];

        $success = $copier->copy($sourceFile, $destFile);

        $this->assertDestFileExists($destFile[0]);
        $this->assertDestFileExists($destFile[1]);

        $this->removeRemoteSourceFile($sourceFile[0]);
        $this->removeRemoteSourceFile($sourceFile[1]);
        $this->removeRemoteDestFile($destFile[0]);
        $this->removeRemoteDestFile($destFile[1]);

        $this->assertTrue($success);
    }

    public function testRemoteToRemoteAssoc() {
        $copier = new Copier($this->sourceSsh, $this->destSsh, getenv("LOCAL_TMP"));
        $sourceAndDest = [
            $this->createRemoteSourceFile("blah.txt")  => $this->getDestPath("blah.txt"),
            $this->createRemoteSourceFile("blah2.txt") => $this->getDestPath("blah2.txt"),
        ];

        $success = $copier->copyAssoc($sourceAndDest);

        foreach($sourceAndDest as $sourceFile => $destFile) {
            $this->assertDestFileExists($destFile);
            $this->removeRemoteSourceFile($sourceFile);
            $this->removeRemoteDestFile($destFile);
        }

        $this->assertTrue($success);
    }

    public function testRemoteToRemoteArrayNoFilenameClash() {
        $copier = new Copier($this->sourceSsh, $this->destSsh, getenv("LOCAL_TMP"));
        $sourceFolder = $this->createRemoteSourceFolder("foo");
        $sourceFile = [$this->createRemoteSourceFile("blah.txt", "blah1"), $this->createRemoteSourceFile("foo/blah.txt", "blah2")];
        $destFolder = $this->createRemoteDestFolder("foo");
        $destFile = [$this->getDestPath("blah.txt"), $this->getDestPath("foo/blah.txt")];

        $success = $copier->copy($sourceFile, $destFile);

        $this->assertDestFileExists($destFile[0], "blah1");
        $this->assertDestFileExists($destFile[1], "blah2");

        $this->removeRemoteSourceFile($sourceFile[0]);
        $this->removeRemoteSourceFile($sourceFile[1]);
        $this->removeRemoteDestFile($destFile[0]);
        $this->removeRemoteDestFile($destFile[1]);
        $this->removeRemoteSourceFolder($sourceFolder);
        $this->removeRemoteDestFolder($destFolder);

        $this->assertTrue($success);
    }

    public function testSourceNotResource() {
        $this->setExpectedException('InvalidArgumentException', 'Source must be null or a resource.');
        new Copier("asdf", null);
    }

    public function testDestNotResource() {
        $this->setExpectedException('InvalidArgumentException', 'Destination must be null or a resource.');
        new Copier(null, "asdf");
    }

    public function testRemoteToRemoteNoLocalTmp() {
        $this->setExpectedException('InvalidArgumentException', 'Must provide a localTmp, if both source and destination are remote.');
        new Copier($this->sourceSsh, $this->destSsh);
    }

    public function testSourceArrayNoDestArray() {
        $this->setExpectedException('InvalidArgumentException', 'Destination must be an array if source is an array.');
        $copier = new Copier();
        $copier->copy(["blah.txt"], "empty.txt");
    }

    public function testDestArrayNoSourceArray() {
        $this->setExpectedException('InvalidArgumentException', 'Source must be an array if destination is an array.');
        $copier = new Copier();
        $copier->copy("empty.txt", ["blah.txt"]);
    }

    public function testSourceDestArraysNoSameSize() {
        $this->setExpectedException('InvalidArgumentException', 'Length of source and destination arrays must be the same.');
        $copier = new Copier();
        $copier->copy(["foo1.txt", "foo2.txt"], ["blah1.txt"]);
    }

    public function testLocalToLocalFail() {
        $copier = new Copier();
        $localSource = $this->getLocalPath("hopefully/doesnt/exist/here.source");
        $localDest = $this->getLocalPath("hopefully/doesnt/exist/here.source");

        // if the files don't exist, the copy should fail
        $success = $copier->copy($localSource, $localDest);

        $this->assertLocalFileDoesntExist($localDest);
        $this->assertFalse($success);
    }

    public function testRemoteToRemoteFail() {
        $copier = new Copier($this->sourceSsh, $this->destSsh, getenv("LOCAL_TMP"));

        $sourceFile = $this->createRemoteSourceFile("blah.txt");
        $destFile = $this->getDestPath("please/let/this/file/not/exist");

        $success = $copier->copyAssoc([$sourceFile => $destFile]);

        $this->assertDestFileDoesntExist($destFile);
        $this->removeRemoteSourceFile($sourceFile);

        $this->assertFalse($success);
    }

    public function testRemoteToLocalFail() {
        $copier = new Copier($this->sourceSsh, null);
        $sourceFile = [$this->getSourcePath("hopefully/doesnt/exist/blah.txt"), $this->getSourcePath("hopefully/doesnt/exist/blah2.txt")];
        $localFile = [$this->getLocalPath(basename($sourceFile[0])), $this->getLocalPath(basename($sourceFile[1]))];

        $success = $copier->copy($sourceFile, $localFile);

        $this->assertLocalFileDoesntExist($localFile[0]);
        $this->assertLocalFileDoesntExist($localFile[1]);

        $this->assertFalse($success);
    }

    public function testLocalToRemoteFail() {
        $copier = new Copier(null, $this->destSsh);
        $localFile = $this->getLocalPath("hopefully/not/here/blah.txt");
        $destFile = $this->getDestPath(basename($localFile));

        $success = $copier->copy($localFile, $destFile);

        $this->assertDestFileDoesntExist($destFile);

        $this->assertFalse($success);
    }
}
