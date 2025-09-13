<?php

namespace F3CMS;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

// use \Aws\S3\Sync\DownloadSyncBuilder;

/**
 * S3Helper 類別提供與 AWS S3 互動的功能，包括檔案上傳、下載、刪除與檢查。
 */
class S3Helper extends Helper
{
    /**
     * 建構子，初始化 S3 客戶端與相關設定。
     *
     * @param string $bucket S3 儲存桶名稱
     */
    public function __construct($bucket = '')
    {
        $aws = f3()->get('aws');

        $this->bucket    = $aws[$bucket . '_bucket'];
        $this->taDirPath = f3()->get('abspath') . $bucket;
        $this->uri       = 'https://' . $aws['s3_domain'] . '/' . $this->bucket;

        // https://s3.ap-southeast-1.amazonaws.com/pa-01.artshow.edu.tw/img/2024/01/664e95aa8e8e39b.webp?jpg

        $this->client = new S3Client([
            'region'      => $aws['region'],
            'version'     => 'latest',
            'credentials' => [
                'key'    => $aws['accessKey'],
                'secret' => $aws['secretKey'],
            ],
        ]);
    }

    /**
     * 列出所有 S3 儲存桶。
     *
     * @return array 儲存桶列表
     */
    public function buckets()
    {
        $buckets = $this->client->listBuckets();

        return $buckets->toArray();
    }

    /**
     * 上傳檔案到 S3。
     *
     * @param string $filePath 檔案路徑，需以 / 開頭
     * @return string 上傳後的檔案 URL
     */
    public function put($filePath)
    {
        try {
            $newPath = str_replace('/upload/', '', $filePath);

            $this->client->putObject([
                'Bucket'     => $this->bucket,
                'Key'        => $newPath,
                'SourceFile' => f3()->get('ROOT') . f3()->get('BASE') . $filePath,
            ]);

            $result = $this->uri . '/' . $newPath;

            // Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 69210112 bytes)
            // $result = $this->client->waitUntil('ObjectExists', array(
            //     'Bucket' => $this->bucket,
            //     'Key'    => $newPath
            // ));
        } catch (S3Exception $e) {
            $logger = new \Log('s3.log');
            $logger->write('The put was rejected with ' . $e->getAwsErrorCode()); // $e->getAwsErrorMessage();
        }

        return $result;
    }

    /**
     * 非同步上傳檔案到 S3。
     *
     * @param string $filePath 檔案路徑
     * @return PromiseInterface 非同步操作的 Promise
     */
    public function putAsync($filePath)
    {
        try {
            $newPath = str_replace('/upload/', '', $filePath);

            $promise = $this->client->putObjectAsync([
                'Bucket'     => $this->bucket,
                'Key'        => $newPath,
                'SourceFile' => $filePath,
            ])->then(
                function ($result) {
                    // echo "File uploaded successfully. ETag: " . $result['ETag'] . PHP_EOL;
                },
                function ($reason) {
                    // echo "Failed to upload file. Reason: " . $reason . PHP_EOL;
                }
            );

            return $promise;
        } catch (S3Exception $e) {
            $logger = new \Log('s3.log');
            $logger->write('The put was rejected with ' . $e->getAwsErrorCode()); // $e->getAwsErrorMessage();

            return null;
        }
    }

    /**
     * 從 S3 下載檔案。
     *
     * @param string $filename 檔案名稱，需以 / 開頭
     * @return mixed 下載結果
     */
    public function get($filename)
    {
        $dir = dirname($filename);

        if (!is_writable($this->taDirPath)) {
            exit('Target Dir is Not writable' . $this->taDirPath);
        }

        FSHelper::mkdir($this->taDirPath . $dir);

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => substr($filename, 1),
                'SaveAs' => $this->taDirPath . $filename,
            ]);
        } catch (S3Exception $e) {
            $result = $e->getAwsErrorCode(); // $e->getAwsErrorMessage();
        }

        return $result;
    }

    /**
     * 刪除 S3 上的檔案。
     *
     * @param string $filename 檔案名稱，需以 / 開頭
     * @return mixed 刪除結果
     */
    public function del($filename)
    {
        try {
            $result = $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => substr($filename, 1),
            ]);
        } catch (S3Exception $e) {
            $result = $e->getAwsErrorCode(); // $e->getAwsErrorMessage();
        }

        return $result;
    }

    /**
     * 列出指定路徑下的所有檔案。
     *
     * @param string $path 路徑
     */
    public function ls($path)
    {
        $iterator = $this->client->getIterator('ListObjects', [
            'Bucket' => $this->bucket,
            'Prefix' => $path,
        ], [
            'limit' => 100,
        ]);

        foreach ($iterator as $object) {
            echo $object['Key'] . '(' . $object['Size'] . ')' . PHP_EOL;
        }
    }

    /**
     * 檢查指定路徑的檔案是否存在於 S3。
     *
     * @param string $path 路徑
     * @param int $echo 是否輸出檢查結果
     * @return int 檢查結果，1 表示存在，0 表示不存在
     */
    public function check($path, $echo = 0)
    {
        $newPath = str_replace('/upload/', '', $path);

        if ($echo) {
            echo "Start checkExist {$newPath} on S3::" . PHP_EOL;
        }
        $iterator = $this->client->getIterator('ListObjects', [
            'Bucket' => $this->bucket,
            'Prefix' => $newPath,
        ], [
            'limit' => 100,
        ]);
        $check = 0;
        foreach ($iterator as $object) {
            if (count($object) > 0) {
                $check = 1;
                if ($echo) {
                    echo $newPath . ' - v' . PHP_EOL;
                }
                break;
            }
        }

        return $check;
    }

    /**
     * 檢查指定路徑的檔案是否存在於 S3（另一種實現）。
     *
     * @param string $path 路徑
     * @param int $echo 是否輸出檢查結果
     * @return int 檢查結果，1 表示存在，0 表示不存在
     */
    public function checkExist($path, $echo = 0)
    {
        if ($echo) {
            echo "Start checkExist {$path} on S3::" . PHP_EOL;
        }
        $iterator = $this->client->getIterator('ListObjects', [
            'Bucket' => $this->bucket,
            'Prefix' => $path,
        ], [
            'limit' => 100,
        ]);
        $check = 0;
        foreach ($iterator as $object) {
            if (count($object) > 0) {
                $check = 1;
                if ($echo) {
                    echo $path . ' - v' . PHP_EOL;
                }
                break;
            }
        }

        return $check;
    }
}
