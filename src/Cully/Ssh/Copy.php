<?php

namespace Cully\Ssh;

class Copy {
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

        // source is local, destination is remote
        if( $this->sourceIsLocal() && $this->destIsRemote() ) {
            return $this->callCopyArrayOrString("copyLocalToRemote", $sourceFilepath, $destFilepath);
        }
        // source is local, destination is local
        else if( $this->sourceIsLocal() && $this->destIsLocal() ) {
            return $this->callCopyArrayOrString("copyLocalToLocal", $sourceFilepath, $destFilepath);
        }
        // source is remote, destination is local
        else if( $this->sourceIsRemote() && $this->destIsLocal() ) {
            return $this->callCopyArrayOrString("copyRemoteToLocal", $sourceFilepath, $destFilepath);
        }
        // source is remote, destination is remote
        else if( $this->sourceIsRemote() && $this->destIsRemote() ) {
            return $this->callCopyArrayOrString("copyRemoteToRemote", $sourceFilepath, $destFilepath);
        }
        else {
            throw new \UnexpectedValueException("Copy is of unknown type.");
        }
    }

    private function copyLocalToLocal($sourcePath, $destPath) {
        return copy($sourcePath, $destPath);
    }

    private function copyRemoteToLocal($sourcePath, $destPath) {
        return ssh2_scp_recv($this->sshSource, $sourcePath, $destPath);
    }

    private function copyLocalToRemote($sourcePath, $destPath) {
        return ssh2_scp_send($this->sshDestination, $sourcePath, $destPath);
    }

    private function copyRemoteToRemote($sourcePath, $destPath) {
        // Generate the temporary location for the file
        $tempFilename = $this->getLocalTmp() . "/" . basename($sourcePath);

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
