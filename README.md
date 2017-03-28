# Amazon CloudWatch adaptor for Flow

This Flow package allows you to use AWS CloudWatch as a LogBackend for Flow.

## Key features

- Usable as every log backend for Flow
- Can create the log stream automatically


## Installation

The Wazisera AWS CloudWatch is installed as a regular Flow package via Composer.
For your existing project, simply include `wazisera/flow-aws-cloudwatch` into the dependencies of your Flow or Neos distribution:

```bash
    $ composer require wazisera/flow-aws-cloudwatch:1.*
```


## Configuration

In order to communicate with the AWS web service, you need to provide the credentials of an account which has access
to CloudWatch.
Add the following configuration to the `Settings.yaml` for your desired Flow context and make sure
to replace logGroupName, logStreamName, key, secret and region with your own data:

```yaml
Neos:
  Flow:
    log:
      systemLogger:
        # Backend and Backend options for the AWS CloudWatch
        backend: Wazisera\Aws\CloudWatch\CloudWatchBackend
        backendOptions:
          profile:
            credentials:
              key: 'ABCD123EFG456HIJ7890'
              secret: 'aBc123DEf456GHi789JKlMNopqRsTuVWXyz12345'
            version: 'latest'
            region: 'eu-central-1'
          logGroupName: 'TestLogGroup'
          logStreamName: 'TestLogStream'
          autoCreateLogStream: true

```


## Licence

This package is licensed under the MIT licence.