<?php
namespace Wazisera\Aws\CloudWatch;


use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\Result;
use Neos\Flow\Log\Backend\AbstractBackend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManager;

/**
 * Class CloudWatchBackend
 * @package Wazisera\Aws\CloudWatch
 */
class CloudWatchBackend extends AbstractBackend {

    /**
     * @var array
     */
    protected $profile = array();

    /**
     * @var string
     */
    protected $logGroupName = '';

    /**
     * @var string
     */
    protected $logStreamName = '';

    /**
     * An array of severity labels, indexed by their integer constant
     * @var array
     */
    protected $severityLabels = array();

    /**
     * @var null
     */
    protected $nextSequenceToken = null;

    /**
     * @var CloudWatchLogsClient
     */
    protected $client;

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
        if($this->client == null) {
            return;
        }
        try {
            $severityLabel = (isset($this->severityLabels[$severity])) ? $this->severityLabels[$severity] : 'UNKNOWN  ';
            $message = $severityLabel . ' - ' . str_pad($packageKey, 20) . ' - ' . $message;

            $logData = [
                'logGroupName' => $this->logGroupName,
                'logStreamName' => $this->logStreamName,
                'logEvents' => [
                    [
                        'message' => $message,
                        'timestamp' => round(microtime(true) * 1000),
                    ]
                ],
            ];
            if($this->nextSequenceToken != null) {
                $logData['sequenceToken'] = $this->nextSequenceToken;
            }
            $result = $this->client->putLogEvents($logData);
            $this->nextSequenceToken = $result['nextSequenceToken'];
        } catch (CloudWatchLogsException $e) {
        }
    }

    /**
     * Does nothing
     *
     * @return void
     */
    public function close() {
    }

    /**
     *
     */
    protected function prepareLog() {
        try {
            /** @var Result $result */
            $result = $this->client->describeLogStreams(['logGroupName' => $this->logGroupName, 'logStreamNamePrefix' => $this->logStreamName]);
            foreach($result['logStreams'] as $logStream) {
                if($logStream['logStreamName'] === $this->logStreamName) {
                    $this->nextSequenceToken = $logStream['uploadSequenceToken'];
                }
            }
        } catch (CloudWatchLogsException $e) {
        }
    }
}
