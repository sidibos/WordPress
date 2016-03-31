Logger
======

Implements `Psr\Log\AbstractLogger` ([PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md)) interface.

Logger has several logging methods for various severities. Severity controls whether the message is logged, reported to the Error Aggregator, etc. However, on dev errors are only logged and displayed locally (not sent to the Error Aggregator).

	$logger->debug("NOT logged by default, set TRACE to enable", $context); // debug level is output only if TRACE env var is set
    $logger->info("Message (is logged by default)");
    $logger->notice("Here's a context", array('foo' => $bar));
	$logger->warning("Warnings are sent to Error Aggregator"); // all data kept, but it won't end up on the status board
    $logger->error("Sky is falling", get_defined_vars()); // errors are sent to the bug tracker (Redmine)
	$logger->critical("Sky has been caught", array('exception' => $e));
    $logger->alert("Special magic tags are supported", array('eh:tolerance'=>'3/day'));
	$logger->emergency($message);

Calls to `Logger->debug()` are ignored unless `TRACE` env variable is set (e.g. run `export TRACE=1`).

Avoid using the generic `$logger->log()` method, as it's unneccesarily verbose to use. Use the methods listed above instead.

## Constructor

To use it generally you need only:

	$logger = Logger::init();

The above will configure logger automatically appropriately for dev/prod environment, set up error handlers, etc.

If you need to configure it:

	$logger = Logger::init([
		'page_type' => Logger::PAGE_TYPE_TEXT, // Disables HTML output
		'buffering' => false, // no ob_start()
		'error_handler' => false, // no set_error_handler(),

		// To report to Sentry instead of FT Error Aggregator
		'sentry_dsn' => "https://$user:$pass@app.getsentry.com/$appid",
	]);

You *should* call `Logger::init()` only once.

If `PROJECT_NAMESPACE` constant is defined the app will have its own log file in `/var/log/apps` (otherwise it's `/var/log/apps/php-dev.log`).

To create a custom log you can construct logger in one of 3 ways:

<pre>
new Logger('log_name'); // logs to /var/log/apps/log_name.log
</pre>
<pre>
new Logger(new StdoutLogHandler()); // logs to stdout
</pre>
<pre>
new Logger(array(      // different handlers depending on severity
	'log'  =>  array(
		'handler'  =>  new FileLogHandler('warnings'),
		'min_severity' => \Psr\Log\LogLevel::DEBUG,
		'max_severity' => \Psr\Log\LogLevel::WARNING,
	),
	'report' => array(
		'handler' => new ErrorReportHandler(),
		'min_severity' => \Psr\Log\LogLevel::ERROR,
	),
));
</pre>

## The rest

To handle an exception (this will take actions depending on severity: log, abort the script, etc.):

	catch(SomeException $e) {
		$logger->logException($e); // defaults to ERROR level, may stop the script
		// or
		$logger->logException($e, \Psr\Log\LogLevel::WARNING);
	}

You can add information to context of all logged messages:

	$logger->setInstanceVariables(array('pid'=>getmypid()));

To silence error reporting (or more specifically—set severity at which a certain handler is used):

	$previous_severity = $logger->setHandlerMinSeverity('report', \Psr\Log\LogLevel::CRITICAL);
	// silently run broken code
	$logger->setHandlerMinSeverity('report', $previous_severity);

You can re-open log files, flush output, etc.

	$logger->reinitialise();

## Tags

To specify a tag add `eh:tagname` key to context array or use `eh:tagname=value` in message of `trigger_error()`.

	new FTLabs\InvalidArgumentException("Bad arguments", array('eh:caller'=>true, 'context'=>get_defined_vars()));
	$logger->warning("Bork", array('eh:tolerance'=>'100/hour'));
	trigger_error('Silent Killer eh:noreport');

Supported tags are:

* `eh:noreport` — don't send the report to the error aggregator. It doesn't prevent logging to a local file.
* `eh:tolerance` = "x/day" or "x/hour" — report to bug tracker only if occurs more than *x* times a day or per hour.
* `eh:caller` — in error report show caller of this function (one level up in backtrace) instead of the place where the error was logged (e.g. if you're throwing exception because of bad arguments, it'll be useful to blame call supplying the arguments rather than guts of argument-checking code).
* `eh:httpresponse` = number — HTTP status (default 500) to send to the browser if this exception is not caught.
* `eh:hashcode` = string — override error aggregation hash. Invent some unique string to group different errors together.
* `eh:nostop` — don't stop and don't display error to the user. Execution will continue if possible (or the page will fail silently with no output).
* `eh:timestamp` — unix timestamp overriding message's time (for saving log messages gathered in the past).
* `eh:postsync` — block until the error is sent to the Error Aggregator (use when reporting multiple errors in a loop).

## Checking errors in production

    javascript:document.cookie='is_dev=9108c15623472d21'

This will switch `Logger` to dev mode regardless of environment.

## Architecture overview

 * `Logger` is the main interface for logging messages, errors and exceptions. You can configure what is logged, and control how it's logged by specifying custom `LogHandler`s (`Logger` doesn't do any actual logging itself, it delegates dirty work to `LogHandler`s).

 * `LogHandler`s perform arbitrary actions when messages/errors are logged, such as writing to a file, e-mailing or displaying an error page. You can create new handlers to perform custom actions (error reporting or recovery) or output logs in other ways (syslog, UDP, etc.)

 * `LogFormatter`s help `LogHandler`s convert structured log messages (with context) and `ErrorLog`s into serialized/string format that will be logged (e.g. text, HTML, CSV, json, Splunk, etc.)

	    new Logger(new FileLogHandler('splunk', new SplunkLogFormatter()));

 * `ErrorLog` encapsulates helpdesk's error report format. It's used internally to give error information to `LogHandler`s and `LogFormatter`s. The class has convenience function for building error report with data gathered from the current process/environment.
