<?php
/**
 * https://symfony.com/doc/current/components/filesystem.html
 */

namespace F3CMS;

use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager as Image;

// The FSHelper class provides file system-related utility methods.
// It includes functions for file manipulation, directory management, and other file system operations.
class FSHelper extends Helper
{
    /**
     * Creates a directory.
     *
     * @param array|string $pathAry The path(s) to the directory(s).
     * @return bool True if the directory was created successfully, false otherwise.
     */
    public static function mkdir($pathAry = [])
    {
        $rtn = true;
        if (is_string($pathAry)) {
            $pathAry = [$pathAry];
        }
        $oldUMask = umask(0027);
        foreach ($pathAry as $path) {
            // Check if the directory already exists.
            if (!file_exists($path)) {
                // Attempt to create the directory.
                @mkdir($path, 0775, true);
            }

            // Check if the directory was created successfully.
            if (!file_exists($path)) {
                $rtn = false;
            }
        }
        umask($oldUMask);

        return $rtn;
    }

    /**
     * Copies a file from one location to another.
     *
     * @param string $orig The path to the original file.
     * @param string $new  The path to the new file.
     * @return bool True if the file was copied successfully, false otherwise.
     */
    public static function copy($orig, $new)
    {
        try {
            // Check if the original file exists.
            if (!file_exists($orig)) {
                throw new \Exception(sprintf('file not exists "%s".', $orig));
            }

            // Check if the new file already exists.
            if (file_exists($new)) {
                throw new \Exception(sprintf('file exists "%s".', $new));
            }

            $destination = fopen($new, 'w+b');
            $handle      = fopen($orig, 'rb');

            // Copy the file contents from the original file to the new file.
            stream_copy_to_stream($handle, $destination);

            fclose($destination);
            fclose($handle);

            // Check if the file was copied successfully.
            if (!file_exists($new)) {
                throw new \Exception(sprintf('Failed to copy file "%s".', $new));
            }

            return true;
        } catch (\Exception $e) {
            // return $e->getMessage(); // log?
            return false;
        }
    }

    /**
     * Renames a file.
     *
     * @param string $orig The path to the original file.
     * @param string $new  The path to the new file.
     * @return void
     */
    public static function rename($orig, $new)
    {
        if (self::copy($orig, $new)) {
            unlink($orig);
        }
    }

    /**
     * Mirrors a directory structure from source to target.
     *
     * @param string $src       The source directory.
     * @param string $tar       The target directory.
     * @param bool   $overwrite Whether to overwrite existing files (default: false).
     * @return void
     */
    public static function mirror($src, $tar, $overwrite = false)
    {
        if (is_link($src)) {
            // do nothing
        } elseif (is_dir($src)) {
            $files = self::ls($src, true);
            foreach ($files as $file) {
                $orig = $src . $file;
                $new  = $tar . $file;
                // Overwrite the existing file if the overwrite flag is set.
                if (file_exists($new) && $overwrite) {
                    unlink($new);
                }
                if (is_link($orig)) {
                    // do nothing
                } elseif (is_dir($orig)) {
                    self::mkdir([$new]);
                } elseif (is_file($orig)) {
                    self::copy($orig, $new);
                } else {
                    throw new \Exception(sprintf('Unable to guess "%s" file type.', $orig));
                }
            }
        } elseif (is_file($src)) {
            self::copy($src, $tar);
        } else {
            throw new \Exception(sprintf('Unable to guess "%s" file type.', $src));
        }
    }

    /**
     * Atomically dumps content into a file.
     *
     * @param string $filename The file to be written to
     * @param string $content  The data to write into the file
     *
     * @throws IOException if the file cannot be written to
     */
    public static function dumpFile($filename, $content)
    {
        // Attempt to write the content to the file.
        if (false === @file_put_contents($filename, $content)) {
            throw new \Exception(sprintf('Failed to write file "%s".', $filename));
        }
    }

    /**
     * adds new contents at the end of some file
     *
     * @param string $filename The file to be written to
     * @param string $content  The data to write into the file
     *
     * @throws IOException if the file cannot be written to
     */
    public static function appendToFile($filename, $content)
    {
        // Attempt to append the content to the file.
        if (false === @file_put_contents($filename, $content, FILE_APPEND)) {
            throw new \Exception(sprintf('Failed to write file "%s".', $filename));
        }
    }

    /**
     * Binary safe to open file
     *
     * @example https://github.com/JBZoo/Utils/blob/master/src/FS.php
     *
     * @param $filepath
     *
     * @return string|null
     */
    public static function openFile($filepath, $size = 0)
    {
        $contents = null;
        // Get the real path of the file.
        if ($realPath = realpath($filepath)) {
            $handle   = fopen($realPath, 'rb');
            // Read the file contents.
            $contents = fread($handle, (0 == $size) ? filesize($realPath) : $size);
            fclose($handle);
        }

        return $contents;
    }

    /**
     * Returns all paths inside a directory.
     *
     * @example https://github.com/JBZoo/Utils/blob/master/src/FS.php
     *
     * @param string $dir
     * @param boolen $isRelative
     *
     * @return array
     */
    public static function ls($dir, $isRelative = false)
    {
        $contents = [];
        $flags    = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO;

        $dirIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, $flags));

        foreach ($dirIterator as $path => $fi) {
            switch ($fi->getFilename()) {
                case '.':
                case '.DS_Store':
                    break;
                case '..':
                    $contents[] = dirname(($isRelative) ? str_replace($dir, '', $path) : $path);
                    break;
                default:
                    $contents[] = ($isRelative) ? str_replace($dir, '', $path) : $path;
                    break;
            }
        }

        natsort($contents);

        return $contents;
    }

    /**
     * Generates thumbnails for an image file.
     *
     * @param string $filename   The base filename for the thumbnails.
     * @param array  $file       The uploaded file information.
     * @param array  $thumbnails An array of thumbnail sizes.
     * @param string $root       The root directory for saving thumbnails (optional).
     * @return array An array containing the paths and dimensions of the generated thumbnails.
     */
    public static function genThumbnails($filename, $file, $thumbnails, $root = '')
    {
        try {
            $path_parts = pathinfo($file['name']);
            $old_fn     = $path_parts['filename'];
            $ext        = $path_parts['extension'];

            if ('' == $root) {
                $root = rtrim(f3()->get('abspath') . 'upload/' . f3()->get('upload_dir'), '/');
            }

            $tmpl = $root . $filename . '%s.' . $ext;

            $manager = Image::withDriver(new Driver());

            $im = $manager->read($file['tmp_name']);

            $webpable = self::is_webpable($file['type']);

            $width  = $im->width();
            $height = $im->height();

            // Generate different sizes of thumbnails.
            if ($width > 1440) {
                $im->save(sprintf($tmpl, '_ori')); // save original img
                // resizing to default size
                $im->scale(width: 1440);
            }

            // TODO: watermark
            // $im->insert('public/watermark.png', 'bottom-right', 10, 10);

            // Save the default thumbnail.
            if (!file_exists(sprintf($tmpl, ''))) {
                $im->save(sprintf($tmpl, ''));
            }

            // Convert to WebP format if possible.
            if ($webpable) {
                self::webp(sprintf($tmpl, ''));
            }

            // Generate and save medium-sized thumbnail.
            $im->scale(width: 720);
            $im->save(sprintf($tmpl, '_md'));
            if ($webpable) {
                self::webp(sprintf($tmpl, '_md'));
            }

            // Generate and save small-sized thumbnail.
            $im->scale(width: 360);
            $im->save(sprintf($tmpl, '_sm'));
            if ($webpable) {
                self::webp(sprintf($tmpl, '_sm'));
            }

            // Generate custom-sized thumbnails.
            foreach ($thumbnails as $ns) {
                // cropping and resizing
                $im->cover($ns[0], $ns[1]);

                $suffix = '_' . $ns[0] . 'x' . $ns[1];

                $im->save(sprintf($tmpl, $suffix));
                if ($webpable) {
                    self::webp(sprintf($tmpl, $suffix));
                }
            }

            $new_fn = '/upload' . $filename . '.' . $ext;
            // Modify the filename for WebP format in non-develop environments.
            if ($webpable && 'develop' != f3()->get('APP_ENV')) {
                $new_fn = str_replace('.' . $ext, '.webp', $new_fn);
                $new_fn .= '?' . $ext;
            }

            return [$new_fn, $width, $height, $old_fn];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()]; // log?
        }
    }

    /**
     * @example
     * Template Name: Health Check
     * Template URI: https://wordpress.org/plugins/health-check/
     * Description: Checks the health of your WordPress install
     * Version: 0.1.0
     * Author: The Health Check Team
     * Author URI: http://health-check-team.example.com
     * Status: Enabled
     *
     * @param $file
     *
     * @return array file info
     */
    public static function getHeader($file)
    {
        $file_data   = str_replace('\r', '\n', self::openFile($file, 8192));
        $all_headers = self::defaultHeaders();

        foreach ($all_headers as $field => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $file_data, $match) && $match[1]) {
                $all_headers[$field] = _cleanup_header_comment($match[1]);
            } else {
                $all_headers[$field] = '';
            }
        }

        return $all_headers;
    }

    protected static function defaultHeaders()
    {
        return [
            'Name'        => 'Template Name',
            'TemplateURI' => 'Template URI',
            'Description' => 'Description',
            'Author'      => 'Author',
            'AuthorURI'   => 'Author URI',
            'Version'     => 'Version',
            'Status'      => 'Status',
        ];
    }

    /**
     * @param $path
     *
     * @return mixed
     */
    public static function webp($path)
    {
        if (file_exists($path)) {
            try {
                $logger     = new \Log('convert.log');
                $path_parts = pathinfo($path);
                $ext        = $path_parts['extension'];

                $ta    = str_replace('.' . $ext, '.webp', $path);
                $ratio = 60;
                // Command to convert the image to WebP format.
                $sh    = 'convert ' . $path . ' -quality ' . $ratio . ' -define webp:lossless=false,method=6,auto-filter=true,partitions=3,image-hint=photo ' . $ta . ';';

                $logger->write($sh);
                shell_exec($sh);

                $path = $ta;
            } catch (Exception $e) {
                $logger = new \Log('convert_error.log');
                $logger->write('failed convert to webp:' . $path);
            }
        }

        return $path;
    }

    /**
     * @param $mimeType
     */
    public static function is_webpable($mimeType)
    {
        $taMimeTypes = [
            'image/jpeg',
            'image/png',
        ];

        return in_array($mimeType, $taMimeTypes);
    }
}
