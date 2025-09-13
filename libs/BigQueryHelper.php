<?php
namespace F3CMS;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Core\Exception\GoogleException;

/**
 * BigQueryHelper 類別提供與 Google BigQuery 的互動功能，
 * 包括資料上傳與查詢。
 */
class BigQueryHelper extends Helper
{
    private $bigQuery; // BigQuery 客戶端
    private $projectId; // Google Cloud 專案 ID
    private $datasetName; // BigQuery 資料集名稱

    /**
     * 建構子，初始化 BigQuery 客戶端與資料集。
     *
     * @param string $projectId Google Cloud 專案 ID
     * @param string $datasetName BigQuery 資料集名稱
     * @param string $serviceAccountKeyPath 服務帳戶金鑰路徑
     */
    public function __construct($projectId, $datasetName, $serviceAccountKeyPath)
    {
        $this->bigQuery = new BigQueryClient([
            'projectId' => $projectId,
            'keyFilePath' => $serviceAccountKeyPath, // Specify the service account key file path
        ]);
        $this->datasetName = $datasetName;
        $this->projectId = $projectId;
    }

    /**
     * 將 CSV 檔案上傳至 BigQuery 表格。
     *
     * @param string $tableName 表格名稱
     * @param string $csvFilePath CSV 檔案路徑
     * @param array $fields 表格欄位結構
     * @return bool 是否成功上傳
     */
    public function uploadCsvToTable($tableName, $csvFilePath, $fields)
    {
        try {
            echo "Starting upload of {$csvFilePath} to table {$this->datasetName}.{$tableName}" . PHP_EOL;

            // Get the dataset and table reference
            $dataset = $this->bigQuery->dataset($this->datasetName);
            $table = $dataset->table($tableName);

            // Open the CSV file
            if (!file_exists($csvFilePath)) {
                throw new \Exception("File not found: {$csvFilePath}");
            }

            $fileHandle = fopen($csvFilePath, 'r');
            if (!$fileHandle) {
                throw new \Exception("Unable to open file: {$csvFilePath}");
            }

            // Load the data into BigQuery
            $loadConfig = $table->load($fileHandle)->sourceFormat('CSV');

            $loadConfig->schema(['fields' => $fields]);
            // $loadConfig->writeDisposition('WRITE_TRUNCATE');
            // $loadConfig->maxBadRecords(10);
            $loadConfig->skipLeadingRows(1);

            $job = $table->runJob($loadConfig);
            // check if the job is complete
            $job->reload();

            if (!$job->isComplete()) {
                throw new \Exception('Job has not yet completed', 500);
            }
            // check if the job has errors
            if (isset($job->info()['status']['errorResult'])) {
                print_r($job->info());
                $error = $job->info()['status']['errorResult']['message'];
                // printf('Error running job: %s' . PHP_EOL, $error);
                return false;
            } else {
                print('Data imported successfully' . PHP_EOL);
                return true;
            }
        } catch (GoogleException $e) {
            echo "GoogleException: " . $e->getMessage() . PHP_EOL;
            return false;
        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * 從 BigQuery 表格中提取 100 筆記錄。
     *
     * @param string $tableName 表格名稱
     * @return array 提取的記錄陣列
     */
    public function fetchRecordsFromTable($tableName)
    {
        try {
            echo "Fetching 100 records from table {$tableName}" . PHP_EOL;

            // Construct the SQL query
            $query = sprintf(
                'SELECT * FROM `%s.%s.%s` LIMIT 100',
                $this->projectId,
                $this->datasetName,
                $tableName
            );

            // Run the query
            $queryJob = $this->bigQuery->query($query);
            $result = $this->bigQuery->runQuery($queryJob);

            // Fetch the results
            $records = [];
            foreach ($result as $row) {
                $records[] = $row;
            }

            echo "Fetched " . count($records) . " records successfully!" . PHP_EOL;
            return $records;
        } catch (GoogleException $e) {
            echo "GoogleException: " . $e->getMessage() . PHP_EOL;
            return [];
        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            return [];
        }
    }
}
