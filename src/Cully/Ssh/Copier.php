<?php

namespace Cully\Ssh;

class Copier {
    private $sshSource;
    private $sshDestination;
    private $localTmp;

    /**
     * @param resource|null $sshSource          The source machine. If local, null.
     *                                          If remote, provide an ssh connection resource.
     *
     * @param resource|null $sshDestination     The destination machine. If local, null.
     *                                          If remote, provide an ssh connection resource.
     *
     * @param string|null $localTmp             If copying from one remote machine to another,
     *                                          you must provide a local, writable folder to
     *                                          store the files between transfer.
     *
     * @throws \InvalidArgumentException        If the source and destination are remote, and
     *                                          a localTmp folder hasn't been provided.
     *
     * @throws \InvalidArgumentException        If source is neither null nor a resource.
     *
     * @throws \InvalidArgumentException        If destination is neither null nor a resource.
     */
    public function __construct($sshSource=null, $sshDestination=null, $localTmp=null) {
        // validate

        if( $sshSource !== null && !is_resource($sshSource) ) {
            throw new \InvalidArgumentException("Source must be null or a resource.");
        }

        if( $sshDestination !== null && !is_resource($sshDestination) ) {
            throw new \InvalidArgumentException("Destination must be null or a resource.");
        }

        if( $sshSource !== null && $sshDestination !== null && empty($localTmp) ) {
            throw new \InvalidArgumentException("Must provide a localTmp, if both source and destination are remote.");
        }

        // set params

        $this->sshSource = $sshSource;
        $this->sshDestination = $sshDestination;
        $this->setLocalTmp($localTmp);
    }

    /**
     * Copies the source file, from the source machine, to the destination machine, at the
     * specified path.
     *
     * The source and destination can be arrays, but if they are, they must be of the same
     * length.
     *
     * @param string|array    $sourceFilepath     One or more full file paths.
     * @param string|array    $destFilepath       One or more full file paths.
     *
     * @return boolean
     *
     * @throws \UnexpectedValueException    If the copy isn't local -> remote, local -> local,
     *                                      remote -> local, or remote -> remote.  I don't think
     *                                      it's possible for this to be thrown.
     *
     * @throws \InvalidArgumentException    If source or remote is an array, and the other parameter
     *                                      is not an array, or is not the same length
     */
    public function copy($sourceFilepath, $destFilepath) {
        // if the source or destination is an array, then they must both be arrays
        // of the same length
        if( is_array($sourceFilepath) || is_array($destFilepath) ) {
            if( !is_array($sourceFilepath) ) {
                throw new \InvalidArgumentException("Source must be an array if destination is an array.");
            }
            else if( !is_array($destFilepath) ) {
                throw new \InvalidArgumentException("Destination must be an array if source is an array.");
            }
            else if( count($sourceFilepath) != count($destFilepath) ) {
                throw new \InvalidArgumentException("Length of source and destination arrays must be the same.");
            }
        }

        $copyFunctionName = null;

        // source is local, destination is remote
        if( $this->sourceIsLocal() && $this->destIsRemote() ) {
            $copyFunctionName = "copyLocalToRemote";
        }
        // source is local, destination is local
        else if( $this->sourceIsLocal() && $this->destIsLocal() ) {
            $copyFunctionName = "copyLocalToLocal";
        }
        // source is remote, destination is local
        else if( $this->sourceIsRemote() && $this->destIsLocal() ) {
            $copyFunctionName = "copyRemoteToLocal";
        }
        // source is remote, destination is remote
        else if( $this->sourceIsRemote() && $this->destIsRemote() ) {
            $copyFunctionName = "copyRemoteToRemote";
        }
        else {
            throw new \UnexpectedValueException("Copy is of unknown type.");
        }

        return $this->callCopyArrayOrString($copyFunctionName, $sourceFilepath, $destFilepath);
    }

    /**
     * Similar to {@link copy()}, except it takes one parameter, an associative array.
     *
     * @param string|array    $sourceAndDest     Keys are source file paths, values are destination file paths
     *
     * @return boolean
     *
     * @throws \UnexpectedValueException    If {@link copy()} throws it.
     *
     * @throws \InvalidArgumentException    If {@link copy()} throws it.
     */
    public function copyAssoc(array $sourceAndDest) {
        return $this->copy(array_keys($sourceAndDest), array_values($sourceAndDest));
    }

    private function copyLocalToLocal($sourcePath, $destPath) {
        return @copy($sourcePath, $destPath);
    }

    private function copyRemoteToLocal($sourcePath, $destPath) {
        return @ssh2_scp_recv($this->sshSource, $sourcePath, $destPath);
    }

    private function copyLocalToRemote($sourcePath, $destPath) {
        return @ssh2_scp_send($this->sshDestination, $sourcePath, $destPath);
    }

    private function copyRemoteToRemote($sourcePath, $destPath) {
        // Generate the temporary location for the file
        $tempFilename = $this->generateUniqueLocalTempFilename();

        // Copy the source file to tmp local
        if( !$this->copyRemoteToLocal($sourcePath, $tempFilename) ) return false;

        // Copy the tmp local file to the destination
        if( !$this->copyLocalToRemote($tempFilename, $destPath) ) {
            // try to delete the local tmp file
            @unlink($tempFilename);
            return false;
        }

        // remove the temp file
        @unlink($tempFilename);

        // done!
        return true;
    }

    /**
     * Generates a unique filename in the temp folder. This is used so that
     * files with the same name (i.e. if they were in different folders on
     * the source machine) don't globber each other.
     *
     * NOTE: Doesn't actually create the file.
     *
     * @return string
     */
    private function generateUniqueLocalTempFilename() {
        do {
            $filename = $this->generateRandomString();
            $filePath = $this->getLocalTmp() . "/" . $filename;
        }
        while(file_exists($filePath));

        return $filePath;
    }

    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Handles the case of whether the source/dest params are arrays or strings,
     * and calls the appropriate copy method to perform the copies.  Basically
     * I just put all the boilerplate code in here so the copy functions would
     * be clean.
     *
     * @param string $copyMethod   The name of the copy function to use
     * @param string|array $sourcePath
     * @param string|array $destPath
     *
     * @return bool
     */
    private function callCopyArrayOrString($copyMethod, $sourcePath, $destPath) {
        // arrays
        if( is_array($sourcePath) ) {
            for($i=0; $i < count($sourcePath); $i++) {
                // do the copy
                if( !call_user_func( [$this, $copyMethod], $sourcePath[$i], $destPath[$i]) ) {
                    return false;
                }
            }

            return true;
        }
        // not arrays
        else {
            return call_user_func( [$this, $copyMethod], $sourcePath, $destPath);
        }
    }

    private function setLocalTmp($localTmp) {
        if( $localTmp !== null ) {
            // make sure it doesn't end with a slash
            $localTmp = preg_replace(';[/\\\]+$;', "", $localTmp);
        }

        $this->localTmp = $localTmp;
    }

    private function getLocalTmp() {
        return $this->localTmp;
    }

    private function sourceIsRemote() {
        return is_resource($this->sshSource);
    }

    private function destIsRemote() {
        return is_resource($this->sshDestination);
    }

    private function sourceIsLocal() {
        return !is_resource($this->sshSource);
    }

    private function destIsLocal() {
        return !is_resource($this->sshDestination);
    }
}
