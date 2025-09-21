<?php

namespace F3CMS;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

/**
 * S3Helper class provides utility methods for interacting with AWS S3,
 * including uploading, downloading, deleting, and listing objects in an S3 bucket.
 */
class S3Helper extends Helper
{
    /**
     * @var string The name of the S3 bucket.
     */
    private $bucket;

    /**
     * @var string The target directory path for local file operations.
     */
    private $taDirPath;

    /**
     * @var string The base URI for the S3 bucket.
     */
    private $uri;

    /**
     * @var S3Client The AWS S3 client instance.
     */
    private $client;

    /**
     * Constructor initializes the S3 client and sets up bucket-specific configurations.
     *
     * @param string $bucket The name of the bucket to interact with.
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
     * Lists all buckets available in the S3 account.
     *
     * @return array List of buckets.
     */
    public function buckets()
    {
        $buckets = $this->client->listBuckets();

        return $buckets->toArray();
    }

    /**
     * Uploads a file to the S3 bucket.
     *
     * @param string $filePath The path of the file to upload, starting with '/'.
     * @return string|null The URL of the uploaded file or null if an error occurs.
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
        } catch (S3Exception $e) {
            $logger = new \Log('s3.log');
            $logger->write('The put was rejected with ' . $e->getAwsErrorCode());

            return null;
        }

        return $result;
    }

    /**
     * Asynchronously uploads a file to the S3 bucket.
     *
     * @param string $filePath The path of the file to upload.
     * @return \GuzzleHttp\Promise\PromiseInterface|null A promise for the upload operation or null if an error occurs.
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
                    // Handle successful upload
                },
                function ($reason) {
                    // Handle failed upload
                }
            );

            return $promise;
        } catch (S3Exception $e) {
            $logger = new \Log('s3.log');
            $logger->write('The put was rejected with ' . $e->getAwsErrorCode());

            return null;
        }
    }

    /**
     * Downloads a file from the S3 bucket.
     *
     * @param string $filename The name of the file to download, starting with '/'.
     * @return mixed The result of the download operation or an error code if it fails.
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
            $result = $e->getAwsErrorCode();
        }

        return $result;
    }

    /**
     * Deletes a file from the S3 bucket.
     *
     * @param string $filename The name of the file to delete, starting with '/'.
     * @return mixed The result of the delete operation or an error code if it fails.
     */
    public function del($filename)
    {
        try {
            $result = $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => substr($filename, 1),
            ]);
        } catch (S3Exception $e) {
            $result = $e->getAwsErrorCode();
        }

        return $result;
    }

    /**
     * Lists objects in a specific path within the S3 bucket.
     *
     * @param string $path The path to list objects from.
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
     * Checks if a specific object exists in the S3 bucket.
     *
     * @param string $path The path of the object to check.
     * @param int $echo Whether to output debug information (0 or 1).
     * @return int 1 if the object exists, 0 otherwise.
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
     * Checks if a specific object exists in the S3 bucket (alternative method).
     *
     * @param string $path The path of the object to check.
     * @param int $echo Whether to output debug information (0 or 1).
     * @return int 1 if the object exists, 0 otherwise.
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
