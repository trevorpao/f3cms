<?php
namespace F3CMS;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Core\Exception\GoogleException;

class BigQueryHelper extends Helper
{
    private $bigQuery;
    private $projectId;
    private $datasetName;

    /**
     * Constructor to initialize BigQuery client and dataset.
     *
     * @param string $projectId
     * @param string $datasetName
     * @param string $serviceAccountKeyPath
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
     * Uploads a CSV file to a BigQuery table.
     *
     * @param string $tableName
     * @param string $csvFilePath
     * @return bool
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
     * Fetches 100 records from a BigQuery table.
     *
     * @param string $tableName
     * @return array
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
