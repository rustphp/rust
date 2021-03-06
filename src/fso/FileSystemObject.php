<?php
namespace rust\fso;

use \FilesystemIterator;
use \ErrorException;
use rust\exception\fso\FileNotFoundException;

class FileSystemObject {
    /**
     * Determine if a file exists.
     *
     * @param  string $path
     *
     * @return bool
     */
    public function exists($path) {
        return file_exists($path);
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     *
     * @return string
     * @throws FileNotFoundException
     */
    public function get($path) {
        if ($this->isFile($path)) {
            return file_get_contents($path);
        }
        return '';
        throw new FileNotFoundException("File does not exist at path {$path}");
    }

    /**
     * Get the returned value of a file.
     *
     * @param  string $path
     *
     * @return mixed
     *
     * @throws FileNotFoundException
     */
    public function getRequire($path) {
        if ($this->isFile($path)) {
            return require $path;
        }
        throw new FileNotFoundException("File does not exist at path {$path}");
    }

    /**
     * Write the contents of a file.
     *
     * @param  string $path
     * @param  string $contents
     * @param  bool $lock
     *
     * @return int
     */
    public function put($path, $contents, $lock = FALSE) {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Prepend to a file.
     *
     * @param  string $path
     * @param  string $data
     *
     * @return int
     */
    public function prepend($path, $data) {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }

        return $this->put($path, $data);
    }

    /**
     * Append to a file.
     *
     * @param  string $path
     * @param  string $data
     *
     * @return int
     */
    public function append($path, $data) {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    /**
     * Delete the file at a given path.
     *
     * @param  string|array $paths
     *
     * @return bool
     */
    public function delete($paths) {
        $paths = is_array($paths) ? $paths : func_get_args();
        $success = TRUE;
        foreach ($paths as $path) {
            try {
                if (!@unlink($path)) {
                    $success = FALSE;
                }
            } catch (ErrorException $e) {
                $success = FALSE;
            }
        }

        return $success;
    }

    /**
     * Move a file to a new location.
     *
     * @param  string $path
     * @param  string $target
     *
     * @return bool
     */
    public function move($path, $target) {
        return rename($path, $target);
    }

    /**
     * Copy a file to a new location.
     *
     * @param  string $path
     * @param  string $target
     *
     * @return bool
     */
    public function copy($path, $target) {
        return copy($path, $target);
    }

    /**
     * Extract the file name from a file path.
     *
     * @param  string $path
     *
     * @return string
     */
    public function name($path) {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Extract the file extension from a file path.
     *
     * @param  string $path
     *
     * @return string
     */
    public function extension($path) {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Get the file type of a given file.
     *
     * @param  string $path
     *
     * @return string
     */
    public function type($path) {
        return filetype($path);
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param  string $path
     *
     * @return string|false
     */
    public function mimeType($path) {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    }

    /**
     * Get the file size of a given file.
     *
     * @param  string $path
     *
     * @return int
     */
    public function size($path) {
        return filesize($path);
    }

    /**
     * Get the file's last modification time.
     *
     * @param  string $path
     *
     * @return int
     */
    public function lastModified($path) {
        return filemtime($path);
    }

    /**
     * Determine if the given path is a directory.
     *
     * @param  string $directory
     *
     * @return bool
     */
    public function isDirectory($directory) {
        return is_dir($directory);
    }

    /**
     * Determine if the given path is writable.
     *
     * @param  string $path
     *
     * @return bool
     */
    public function isWritable($path) {
        return is_writable($path);
    }

    /**
     * Determine if the given path is a file.
     *
     * @param  string $file
     *
     * @return bool
     */
    public function isFile($file) {
        return is_file($file);
    }

    /**
     * Find path names matching a given pattern.
     *
     * @param  string $pattern
     * @param  int $flags
     *
     * @return array
     */
    public function glob($pattern, $flags = 0) {
        return glob($pattern, $flags);
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param  string $directory
     *
     * @return array
     */
    public function files($directory) {
        $glob = glob($directory . '/*');
        if ($glob === FALSE) {
            return [];
        }
        return array_filter($glob, function ($file) {
            return filetype($file) == 'file';
        });
    }

    /**
     * Create a directory.
     *
     * @param  string $path
     * @param  int $mode
     * @param  bool $recursive
     * @param  bool $force
     *
     * @return bool
     */
    public function makeDirectory($path, $mode = 0755, $recursive = FALSE, $force = FALSE) {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Copy a directory from one location to another.
     *
     * @param  string $directory
     * @param  string $destination
     * @param  int $options
     *
     * @return bool
     */
    public function copyDirectory($directory, $destination, $options = NULL) {
        if (!$this->isDirectory($directory)) {
            return FALSE;
        }
        $options = $options ?: FilesystemIterator::SKIP_DOTS;
        if (!$this->isDirectory($destination)) {
            $this->makeDirectory($destination, 0777, TRUE);
        }
        $items = new FilesystemIterator($directory, $options);
        foreach ($items as $item) {
            $target = $destination . '/' . $item->getBasename();
            if ($item->isDir()) {
                $path = $item->getPathname();
                if (!$this->copyDirectory($path, $target, $options)) {
                    return FALSE;
                }
            } else {
                if (!$this->copy($item->getPathname(), $target)) {
                    return FALSE;
                }
            }
        }

        return TRUE;
    }

    /**
     * Recursively delete a directory.
     *
     * The directory itself may be optionally preserved.
     *
     * @param  string $directory
     * @param  bool $preserve
     *
     * @return bool
     */
    public function deleteDirectory($directory, $preserve = FALSE) {
        if (!$this->isDirectory($directory)) {
            return FALSE;
        }
        $items = new FilesystemIterator($directory);
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            } else {
                $this->delete($item->getPathname());
            }
        }
        if (!$preserve) {
            @rmdir($directory);
        }

        return TRUE;
    }

    /**
     * Empty the specified directory of all files and folders.
     *
     * @param  string $directory
     *
     * @return bool
     */
    public function cleanDirectory($directory) {
        return $this->deleteDirectory($directory, TRUE);
    }
}