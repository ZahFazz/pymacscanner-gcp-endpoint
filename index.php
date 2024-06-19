<?php
/*
 * Copyright (c) 2024. Yoppy Yunhasnawa, Politeknik Negeri Malang.
 * This software is available under MIT License.
 * Contact me at: yunhasnawa@polinema.ac.id
 */

use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Google\Cloud\BigQuery\BigQueryClient;

// Register the function with Functions Framework.
// This enables omitting the `FUNCTIONS_SIGNATURE_TYPE=http` environment
// variable when deploying. The `FUNCTION_TARGET` environment variable should
// match the first parameter.
FunctionsFramework::http('pymacscannerEndpoint', 'pymacscannerEndpoint');

function pymacscannerEndpoint(ServerRequestInterface $request): string
{
    $body = $request->getBody()->getContents();
    $scannerId = '';
    $scanTime = '';
    $macAddresses = [];

    // Mengambil data dengan method POST
    if (!empty($body))
    {
        $json = json_decode($body, true);
        if (json_last_error() != JSON_ERROR_NONE)
        {
            throw new RuntimeException(sprintf(
                'Could not parse body: %s',
                json_last_error_msg()
            ));
        }
        $scannerId = $json['scannerId'];
        $scanTime = $json['scanTime'];
        $macAddresses = $json['macAddresses'];
    }

    // Jika menggunakan GET
    // $queryString = $request->getQueryParams();
    // $scannerId = $queryString['scannerId'];
    // $timestamp = $queryString['timestamp'];
    // $macAddresses = $queryString['macAddresses'];

    return (new MacSaver($scannerId, $scanTime, $macAddresses))->save();
}

class MacSaver
{
    const KEY_FILE_PATH = 'lecturer-attending-2dd976c96c7a.json';
    const PROJECT_ID = 'lecturer-attending';
    const DATASET_ID = 'scannmap';
    const TABLE_ID = 'scanned';

    private string $_scannerId;
    private string $_scanTime;
    private array $_macAddresses;

    private BigQueryClient $_bq;

    public function __construct($scannerId, $scanTime, $macAddresses)
    {
        $this->_scannerId = $scannerId;
        $this->_scanTime = $scanTime;
        $this->_macAddresses = $macAddresses;
    }

    public function bq(): BigQueryClient
    {
        if (empty($this->_bq))
        {
            $this->_bq = new BigQueryClient([
              //  'keyFilePath' => self::KEY_FILE_PATH, // Hapus baris ini jika function akan di-deploy ke GCP
                'projectId' => self::PROJECT_ID
            ]);
        }
        return $this->_bq;
    }

    public static function okResponse($message): string
    {
        return json_encode(['status' => 'ok', 'message' => $message]);
    }

    public static function failedResponse($message): string
    {
        return json_encode(['status' => 'failed', 'message' => $message]);
    }

    public static function createResponse($insertResponse): string
    {
        if ($insertResponse->isSuccessful())
        {
            return self::okResponse('Data streamed into BigQuery successfully' . PHP_EOL);
        }
        else
        {
            $message = 'Data failed to stream into BigQuery' . PHP_EOL;

            foreach ($insertResponse->failedRows() as $row)
            {
                foreach ($row['errors'] as $error)
                {
                    $message .= printf('%s: %s' . PHP_EOL, $error['reason'], $error['message']);
                }
            }

            return self::failedResponse($message);
        }
    }

    public function save(): string
    {
        $bigQuery = $this->bq();

        // Get an instance of a previously created table.
        $dataset = $bigQuery->dataset(self::DATASET_ID);
        $table = $dataset->table(self::TABLE_ID);

        // Prepare the data
        $data = [];
        foreach ($this->_macAddresses as $macAddress)
        {
            $row = [
                'timestamp' => date('Y-m-d H:i:s'),
                'scannerId' => $this->_scannerId,
                'scanTime' => $this->_scanTime,
                'macAddress' => $macAddress
            ];
            $data[] = ['data' => $row];
        }

        // Create insert command
        $insertResponse = $table->insertRows($data);
        $replaceViewConfig = $bigQuery->query(
            'CREATE OR REPLACE VIEW `lecturer-attending.scannmap.scanned_latest`
            AS
            SELECT *
            FROM `lecturer-attending.scannmap.scanned`
            WHERE timestamp = (
                SELECT MAX(timestamp) 
                FROM `lecturer-attending.scannmap.scanned`
            );'
        );
        $replaceView = $bigQuery->runQuery($replaceViewConfig);

        // Return proper response based on insert response
        return self::createResponse($insertResponse);
    }
}

// References:
// https://cloud.google.com/functions/docs/create-deploy-http-php?hl=id
// https://cloud.google.com/bigquery/docs/streaming-data-into-bigquery#bigquery_table_insert_rows-php
// https://www.rudderstack.com/guides/send-data-from-your-php-codebase-to-google-bigquery-4/
