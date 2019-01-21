<?php
namespace F3CMS;

use \Aws\S3\Exception\S3Exception;
use \Aws\S3\S3Client;
use \Aws\S3\Sync\DownloadSyncBuilder;

class S3Helper extends Helper
{
    /**
     * @param $bucket
     */
    public function __construct($bucket = '')
    {
        $this->bucket = $bucket;
        $this->taDirPath = f3()->get('ta_dir_path') . $bucket . '/';

        // Establish connection with DreamObjects with an S3 client.
        $this->client = S3Client::factory(array(
            'base_url' => HOST,
            'key'      => AWS_KEY,
            'secret'   => AWS_SECRET_KEY
        ));
    }

    /**
     * @param $path
     */
    public function get($path)
    {
        $check = 0;
        try {
            echo "start download {$path} to target dir" . PHP_EOL;

            DownloadSyncBuilder::getInstance()
                ->setClient($this->client)
                ->setDirectory($this->taDirPath)
                ->setBucket($this->bucket)
                ->setKeyPrefix($path)
                ->allowResumableDownloads()
                ->build()
                ->transfer();

            $check = 1;
            echo $path . ' - v' . PHP_EOL;
        } catch (S3Exception $e) {
            echo $path . '::' . $e->getExceptionCode() . '' . PHP_EOL;
        }
        return $check;
    }

    /**
     * @param $path
     */
    public function checkExist($path)
    {
        echo "start checkExist {$path} on S3" . PHP_EOL;
        $acl = 'public-read';
        $iterator = $this->client->getIterator('ListObjects', array(
            'Bucket' => $this->bucket,
            'Prefix' => $path
        ), array(
            'limit' => 1
        ));
        $check = 0;
        foreach ($iterator as $object) {
            if (count($object) > 0) {
                $check = 1;
                echo $path . ' - v' . PHP_EOL;
                break;
            }
        }

        return $check;
    }
}
