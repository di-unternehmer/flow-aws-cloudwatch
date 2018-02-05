<?php
namespace Wazisera\Aws\CloudWatch;

/*                                                                        *
 * This script belongs to the package "Wazisera.Aws.CloudWatch".          *
 *                                                                        *
 *                                                                        */

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\Result;
use Neos\Flow\Log\Backend\AbstractBackend;
use Neos\Flow\Annotations as Flow;

/**
 * Class CloudWatchBackend
 */
class CloudWatchBackend extends AbstractBackend {

    /**
     * Credentials for the AWS account
     * @var array
     */
    protected $profile = array();

    /**
     * CloudWatch LogGroupName
     * @var string
     */
    protected $logGroupName = '';

    /**
     * CloudWatch LogStreamName
     * @var string
     */
    protected $logStreamName = '';

    /**
     * Should create the given logStream if it not exists
     * @var bool
     */
    protected $autoCreateLogStream = true;

    /**
     * An array of severity labels, indexed by their integer constant
     * @var array
     */
    protected $severityLabels = array();

    /**
     * Next sequenceToken for next log event
     * @var null
     */
    protected $nextSequenceToken = null;

    /**
     * @var CloudWatchLogsClient
     */
    protected $client;

    /**
     * @var bool
     */
    protected $useLog = true;

    /**
     * @var array
     */
    protected $messageRecords = array();

    /**
     * @var int
     */
    protected $recordAmountLimit = 1048576;

    /**
     * @var int
     */
    protected $currentRecordAmount = 0;

    /**
     * @var int
     */
    protected $batchSize = 10000;

    /**
     * @param array $profile
     */
    public function setProfile($profile) {
        $this->profile = $profile;
    }

    /**
     * @param string $logGroupName
     */
    public function setLogGroupName($logGroupName) {
        $this->logGroupName = $logGroupName;
    }

    /**
     * @param string $logStreamName
     */
    public function setLogStreamName($logStreamName) {
        $this->logStreamName = $logStreamName;
    }

    /**
     * @param bool $autoCreateLogStream
     */
    public function setAutoCreateLogStream($autoCreateLogStream) {
        $this->autoCreateLogStream = $autoCreateLogStream;
    }

    /**
     * Opens the AWS CloudWatch.
     *
     * @return void
     * @api
     */
    public function open() {
        $this->severityLabels = [
            LOG_EMERG   => 'EMERGENCY',
            LOG_ALERT   => 'ALERT',
            LOG_CRIT    => 'CRITICAL',
            LOG_ERR     => 'ERROR',
            LOG_WARNING => 'WARNING',
            LOG_NOTICE  => 'NOTICE',
            LOG_INFO    => 'INFO',
            LOG_DEBUG   => 'DEBUG',
        ];

        if($this->profile == array()) {
            return;
        }
        $this->client = new CloudWatchLogsClient($this->profile);
        $this->prepareLog();
    }

    /**
     * Puts the message to AWS CloudWatch
     *
     * @param string $message The message to log
     * @param integer $severity One of the LOG_* constants
     * @param mixed $additionalData A variable containing more information about the event to be logged
     * @param string $packageKey Key of the package triggering the log (determined automatically if not specified)
     * @param string $className Name of the class triggering the log (determined automatically if not specified)
     * @param string $methodName Name of the method triggering the log (determined automatically if not specified)
     * @return void
     */
    public function append($message, $severity = 1, $additionalData = null, $packageKey = null, $className = null, $methodName = null) {
        $record = $this->createRecord($message, $severity, $packageKey);

        if ($this->currentRecordAmount + $this->getRecordSize($record) >= $this->recordAmountLimit || count($this->messageRecords) >= $this->batchSize) {
            $this->flushBuffer();
            $this->messageRecords[] = $record;
        } else {
            $this->messageRecords[] = $record;
            $this->currentRecordAmount += $this->getRecordSize($record);
        }
        $this->flushBuffer(); // workaround
    }

    /**
     * @return void
     */
    public function close() {
        $this->flushBuffer();
    }

    /**
     * Prepares the CloudWatch log by fetching the next sequenceToken.
     * If the LogStream does not exist it will be created when autoCreateLogStream is true.
     */
    protected function prepareLog() {
        try {
            /** @var Result $result */
            $result = $this->client->describeLogStreams(['logGroupName' => $this->logGroupName, 'logStreamNamePrefix' => $this->logStreamName]);

            foreach($result['logStreams'] as $logStream) {
                if($logStream['logStreamName'] === $this->logStreamName) {
                    if(array_key_exists('uploadSequenceToken', $logStream)) {
                        $this->nextSequenceToken = $logStream['uploadSequenceToken'];
                    }
                    return;
                }
            }

            // no logStream found, create one
            if($this->autoCreateLogStream === true) {
                try {
                    $this->client->createLogStream([
                        'logGroupName' => $this->logGroupName,
                        'logStreamName' => $this->logStreamName
                    ]);
                } catch (CloudWatchLogsException $e) {
                }
            }

        } catch (CloudWatchLogsException $e) {
        }
    }

    /**
     * @param $message
     * @param $severity
     * @param $packageKey
     * @return array
     */
    protected function createRecord($message, $severity, $packageKey) {
        $severityLabel = (isset($this->severityLabels[$severity])) ? $this->severityLabels[$severity] : 'UNKNOWN  ';
        $message = $severityLabel . ' - ' . str_pad($packageKey, 20) . ' - ' . $message;
        return array(
            'message' => $message,
            'timestamp' => round(microtime(true) * 1000),
        );
    }

    /**
     * @param $record
     * @return int
     */
    protected function getRecordSize($record) {
        return strlen($record['message']) + 26;
    }

    /**
     * Flush the buffer and send it to Cloudwatch
     */
    protected function flushBuffer() {
        if($this->client == null || $this->useLog == false || empty($this->messageRecords)) {
            return;
        }

        $logData = [
            'logGroupName' => $this->logGroupName,
            'logStreamName' => $this->logStreamName,
            'logEvents' => $this->messageRecords
        ];
        if($this->nextSequenceToken != null) {
            $logData['sequenceToken'] = $this->nextSequenceToken;
        }

        try {
            $result = $this->client->putLogEvents($logData);
            $this->nextSequenceToken = $result['nextSequenceToken'];
        } catch (\Aws\CloudWatchLogs\Exception\CloudWatchLogsException $e) {
            $this->useLog = false;
        }

        $this->currentRecordAmount = 0;
        $this->messageRecords = array();
    }
}
