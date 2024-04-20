# pymacscanner-gcp-endpoint
Sample endpoint for pymacscanner that store MAC addresses data to BigQuery via Cloud Function

---

## Description
This is a cloud function script that process the data from the [pymacscanner](https://github.com/yunhasnawa/pymacscanner) and store it to BigQuery.

## Requirements
1. Active Google Cloud account.
2. Google cloud SDK installed and configured.
3. [Composer](https://getcomposer.org)
4. Necessary BigQuery IAM permission to perform data insertions.
5. JSON file containing the BigQuery service account key.
6. A BigQuery dataset and table to store the data.

## Table Schema
To store the data, you need to create a table with the following schema:

| Field       | Type      |
| ----------- | --------- |
| timestamp   | TIMESTAMP |
| scannerId   | STRING    |
| scanTime    | DATETIME  |
| macAddress  | STRING    |

## Usage

### A. Local Development
1. Clone this repository.
2. Install the dependencies by running `composer install`.
3. Run the script by executing `composer start`.
4. The script will start a local server that listens to the `POST` request on `http://localhost:8080`.

### B. Cloud Function Deployment
1. Perform steps A1 until A2.
2. Run `deploy.sh` script to deploy the cloud function.
