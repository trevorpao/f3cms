<?php
/**
 * https://symfony.com/doc/current/components/filesystem.html
 */

namespace F3CMS;

use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager as Image;

/**
 * FSHelper 類別提供了多種檔案系統操作的功能，包括目錄管理、檔案操作、縮圖生成等。
 */
class FSHelper extends Helper
{
    /**
     * 建立目錄。
     *
     * @param array|string $pathAry 目錄路徑或路徑陣列
     * @return bool 是否成功建立目錄
     */
    public static function mkdir($pathAry = [])
    {
        $rtn = true;
        if (is_string($pathAry)) {
            $pathAry = [$pathAry];
        }
        $oldUMask = umask(0027);
        foreach ($pathAry as $path) {
            if (!file_exists($path)) {
                @mkdir($path, 0775, true);
            }

            if (!file_exists($path)) {
                $rtn = false;
            }
        }
        umask($oldUMask);

        return $rtn;
    }

    /**
     * 複製檔案。
     *
     * @param string $orig 原始檔案路徑
     * @param string $new 新檔案路徑
     * @return bool 是否成功複製
     */
    public static function copy($orig, $new)
    {
        try {
            if (!file_exists($orig)) {
                throw new \Exception(sprintf('file not exists "%s".', $orig));
            }

            if (file_exists($new)) {
                throw new \Exception(sprintf('file exists "%s".', $new));
            }

            $destination = fopen($new, 'w+b');
            $handle      = fopen($orig, 'rb');

            stream_copy_to_stream($handle, $destination);

            fclose($destination);
            fclose($handle);

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
     * 重新命名檔案。
     *
     * @param string $orig 原始檔案路徑
     * @param string $new 新檔案路徑
     */
    public static function rename($orig, $new)
    {
        if (self::copy($orig, $new)) {
            unlink($orig);
        }
    }

    /**
     * 同步目錄內容。
     *
     * @param string $src 原始目錄
     * @param string $tar 目標目錄
     * @param bool $overwrite 是否覆蓋已存在的檔案
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
        if (false === @file_put_contents($filename, $content)) {
            throw new \Exception(sprintf('Failed to write file "%s".', $filename));
        }
    }

    /**
     * 將內容附加到檔案末尾。
     *
     * @param string $filename 檔案名稱
     * @param string $content 附加的內容
     * @throws Exception 如果寫入失敗
     */
    public static function appendToFile($filename, $content)
    {
        if (false === @file_put_contents($filename, $content, FILE_APPEND)) {
            throw new \Exception(sprintf('Failed to write file "%s".', $filename));
        }
    }

    /**
     * 以二進位模式安全地開啟檔案。
     *
     * @param string $filepath 檔案路徑
     * @param int $size 讀取大小
     * @return string|null 檔案內容
     */
    public static function openFile($filepath, $size = 0)
    {
        $contents = null;
        if ($realPath = realpath($filepath)) {
            $handle   = fopen($realPath, 'rb');
            $contents = fread($handle, (0 == $size) ? filesize($realPath) : $size);
            fclose($handle);
        }

        return $contents;
    }

    /**
     * 列出目錄中的所有路徑。
     *
     * @param string $dir 目錄路徑
     * @param bool $isRelative 是否返回相對路徑
     * @return array 路徑列表
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
     * 生成縮圖。
     *
     * @param string $filename 檔案名稱
     * @param array $file 檔案資訊
     * @param array $thumbnails 縮圖尺寸
     * @param string $root 根目錄
     * @return array 縮圖資訊
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

            if ($width > 1440) {
                $im->save(sprintf($tmpl, '_ori')); // save original img
                // resizing to default size
                $im->scale(width: 1440);
            }

            // TODO: watermark
            // $im->insert('public/watermark.png', 'bottom-right', 10, 10);

            if (!file_exists(sprintf($tmpl, ''))) {
                $im->save(sprintf($tmpl, ''));
            }

            if ($webpable) {
                self::webp(sprintf($tmpl, ''));
            }

            $im->scale(width: 720);
            $im->save(sprintf($tmpl, '_md'));
            if ($webpable) {
                self::webp(sprintf($tmpl, '_md'));
            }

            $im->scale(width: 360);
            $im->save(sprintf($tmpl, '_sm'));
            if ($webpable) {
                self::webp(sprintf($tmpl, '_sm'));
            }

            // smaller then 360
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
     * 取得檔案的標頭資訊。
     *
     * @param string $file 檔案路徑
     * @return array 檔案標頭資訊
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
     * 將檔案轉換為 WebP 格式。
     *
     * @param string $path 檔案路徑
     * @return string 轉換後的檔案路徑
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
     * 檢查檔案是否可以轉換為 WebP 格式。
     *
     * @param string $mimeType 檔案的 MIME 類型
     * @return bool 是否可以轉換
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
