<?php

namespace Test\Cully\Ssh;

use Cully\Ssh\Copy;
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

    private function createRemoteSourceFile($name) {
        $command = new Ssh\Command($this->sourceSsh);

        $filePath = $this->getSourcePath($name);

        $command->exec("touch " . escapeshellarg($filePath));

        if($command->failure()) {
            $this->markTestSkipped("Could not create remote source file.");
        }

        return $filePath;
    }

    private function removeRemoteSourceFile($filePath) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("rm " . escapeshellarg($filePath));
    }

    private function createRemoteDestFile($name) {
        $command = new Ssh\Command($this->destSsh);

        $filePath = $this->getDestPath($name);

        $command->exec("touch " . escapeshellarg($filePath));

        if($command->failure()) {
            $this->markTestSkipped("Could not create remote dest file.");
        }

        return $filePath;
    }

    private function removeRemoteDestFile($filePath) {
        $command = new Ssh\Command($this->destSsh);
        $command->exec("rm " . escapeshellarg($filePath));
    }

    private function createLocalFile($name) {
        $command = new Local\Command();
        $filePath = $this->getLocalPath($name);
        $command->exec("touch " . escapeshellarg($filePath));

        if($command->failure()) {
            $this->markTestSkipped("Could not create local file.");
        }

        return $filePath;
    }

    private function assertLocalFileExists($filePath) {
        $command = new Local\Command();
        $command->exec("ls " . escapeshellarg($filePath));

        $this->assertTrue($command->success());
    }

    private function assertSourceFileExists($filePath) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("ls " . escapeshellarg($filePath));

        $this->assertTrue($command->success());
    }

    private function assertDestFileExists($filePath) {
        $command = new Ssh\Command($this->sourceSsh);
        $command->exec("ls " . escapeshellarg($filePath));

        $this->assertTrue($command->success());
    }

    private function removeLocalFile($filePath) {
        $command = new Local\Command();
        $command->exec("rm " . escapeshellarg($filePath));
    }

    private function getLocalPath($otherPath) {
        $name = basename($otherPath);

        return getenv("LOCAL_TMP") . "/{$name}";
    }

    private function getSourcePath($otherPath) {
        $name = basename($otherPath);

        return getenv("SOURCE_TMP") . "/{$name}";
    }

    private function getDestPath($otherPath) {
        $name = basename($otherPath);

        return getenv("DEST_TMP") . "/{$name}";
    }

    public function testLocalToLocal() {
        $copy = new Copy();
        $localFile1 = $this->createLocalFile("blah.txt");
        $localFile2 = $this->getLocalPath("blah2.txt");

        $success = $copy->copy($localFile1, $localFile2);

        $this->assertLocalFileExists($localFile2);

        $this->removeLocalFile($localFile1);
        $this->removeLocalFile($localFile2);

        $this->assertTrue($success);
    }

    public function testLocalToRemote() {
        $copy = new Copy(null, $this->destSsh);
        $localFile = $this->createLocalFile("blah.txt");
        $destFile = $this->getDestPath($localFile);

        $success = $copy->copy($localFile, $destFile);

        $this->assertDestFileExists($destFile);

        $this->removeLocalFile($localFile);
        $this->removeRemoteDestFile($destFile);

        $this->assertTrue($success);
    }

    public function testRemoteToLocal() {
        $copy = new Copy($this->sourceSsh, null);
        $sourceFile = $this->createRemoteSourceFile("blah.txt");
        $localFile = $this->getLocalPath($sourceFile);

        $success = $copy->copy($sourceFile, $localFile);

        $this->assertLocalFileExists($localFile);

        $this->removeLocalFile($localFile);
        $this->removeRemoteSourceFile($sourceFile);

        $this->assertTrue($success);
    }

    public function testRemoteToRemote() {
        $copy = new Copy($this->sourceSsh, $this->destSsh, getenv("LOCAL_TMP"));
        $sourceFile = $this->createRemoteSourceFile("blah.txt");
        $destFile = $this->getDestPath($sourceFile);

        $success = $copy->copy($sourceFile, $destFile);

        $this->assertDestFileExists($destFile);

        $this->removeRemoteSourceFile($sourceFile);
        $this->removeRemoteDestFile($destFile);

        $this->assertTrue($success);
    }

    public function testLocalToLocalArray() {
        $copy = new Copy();
        $localFile1 = [$this->createLocalFile("blah.txt"), $this->createLocalFile("blah2.txt")];
        $localFile2 = [$this->getLocalPath("foo.txt"), $this->getLocalPath("foo2.txt")];

        $success = $copy->copy($localFile1, $localFile2);

        $this->assertLocalFileExists($localFile2[0]);
        $this->assertLocalFileExists($localFile2[1]);

        $this->removeLocalFile($localFile1[0]);
        $this->removeLocalFile($localFile1[1]);
        $this->removeLocalFile($localFile2[0]);
        $this->removeLocalFile($localFile2[1]);

        $this->assertTrue($success);
    }

    public function testLocalToRemoteArray() {
        $copy = new Copy(null, $this->destSsh);
        $localFile = [ $this->createLocalFile("blah.txt"), $this->createLocalFile("blah2.txt") ];
        $destFile = [ $this->getDestPath($localFile[0]), $this->getDestPath($localFile[1]) ];

        $success = $copy->copy($localFile, $destFile);

        $this->assertDestFileExists($destFile[0]);
        $this->assertDestFileExists($destFile[1]);

        $this->removeLocalFile($localFile[0]);
        $this->removeLocalFile($localFile[1]);
        $this->removeRemoteDestFile($destFile[0]);
        $this->removeRemoteDestFile($destFile[1]);

        $this->assertTrue($success);
    }

    public function testRemoteToLocalArray() {
        $copy = new Copy($this->sourceSsh, null);
        $sourceFile = [$this->createRemoteSourceFile("blah.txt"), $this->createRemoteSourceFile("blah2.txt")];
        $localFile = [$this->getLocalPath($sourceFile[0]), $this->getLocalPath($sourceFile[1])];

        $success = $copy->copy($sourceFile, $localFile);

        $this->assertLocalFileExists($localFile[0]);
        $this->assertLocalFileExists($localFile[1]);

        $this->removeLocalFile($localFile[0]);
        $this->removeLocalFile($localFile[1]);
        $this->removeRemoteSourceFile($sourceFile[0]);
        $this->removeRemoteSourceFile($sourceFile[1]);

        $this->assertTrue($success);
    }

    public function testRemoteToRemoteArray() {
        $copy = new Copy($this->sourceSsh, $this->destSsh, getenv("LOCAL_TMP"));
        $sourceFile = [$this->createRemoteSourceFile("blah.txt"), $this->createRemoteSourceFile("blah2.txt")];
        $destFile = [$this->getDestPath($sourceFile[0]), $this->getDestPath($sourceFile[1])];

        $success = $copy->copy($sourceFile, $destFile);

        $this->assertDestFileExists($destFile[0]);
        $this->assertDestFileExists($destFile[1]);

        $this->removeRemoteSourceFile($sourceFile[0]);
        $this->removeRemoteSourceFile($sourceFile[1]);
        $this->removeRemoteDestFile($destFile[0]);
        $this->removeRemoteDestFile($destFile[1]);

        $this->assertTrue($success);
    }

    public function testSourceNotResource() {
        $this->setExpectedException('InvalidArgumentException', 'Source must be null or a resource.');
        new Copy("asdf", null);
    }

    public function testDestNotResource() {
        $this->setExpectedException('InvalidArgumentException', 'Destination must be null or a resource.');
        new Copy(null, "asdf");
    }

    public function testRemoteToRemoteNoLocalTmp() {
        $this->setExpectedException('InvalidArgumentException', 'Must provide a localTmp, if both source and destination are remote.');
        new Copy($this->sourceSsh, $this->destSsh);
    }

    public function testSourceArrayNoDestArray() {
        $this->setExpectedException('InvalidArgumentException', 'Destination must be an array if source is an array.');
        $copy = new Copy();
        $copy->copy(["blah.txt"], "empty.txt");
    }

    public function testDestArrayNoSourceArray() {
        $this->setExpectedException('InvalidArgumentException', 'Source must be an array if destination is an array.');
        $copy = new Copy();
        $copy->copy("empty.txt", ["blah.txt"]);
    }

    public function testSourceDestArraysNoSameSize() {
        $this->setExpectedException('InvalidArgumentException', 'Length of source and destination arrays must be the same.');
        $copy = new Copy();
        $copy->copy(["foo1.txt", "foo2.txt"], ["blah1.txt"]);
    }
}
