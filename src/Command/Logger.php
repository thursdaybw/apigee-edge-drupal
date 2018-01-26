<?php

namespace Drupal\apigee_edge\Command;

use Drush\Log\LogLevel;
use Psr\Log\AbstractLogger;
use Robo\Log\RoboLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Drush\Utils\StringUtils;

/**
 * This is the actual Logger for Drupal Console.
 */
class Logger extends RoboLogger {

    public function __construct(OutputInterface $output) {
        parent::__construct($output);
    }

    public function log($level, $message, array $context = [])
    {
        // Convert to old $entry array for b/c calls
        $entry = $context + [
            'type' => $level,
            'message' => StringUtils::interpolate($message, $context),
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(),
        ];

        // Drush\Log\Logger should take over all of the responsibilities
        // of drush_log, including caching the log messages and sending
        // log messages along to backend invoke.
        // TODO: move these implementations inside this class.
        $log =& drush_get_context('DRUSH_LOG', []);
        $log[] = $entry;
        if ($level != LogLevel::DEBUG_NOTIFY) {
            drush_backend_packet('log', $entry);
        }

        if ($this->output->isDecorated()) {
            $red = "\033[31;40m\033[1m[%s]\033[0m";
            $yellow = "\033[1;33;40m\033[1m[%s]\033[0m";
            $green = "\033[1;32;40m\033[1m[%s]\033[0m";
        } else {
            $red = "[%s]";
            $yellow = "[%s]";
            $green = "[%s]";
        }

        $verbose = \Drush\Drush::verbose();
        $debug = drush_get_context('DRUSH_DEBUG');
        $debugnotify = drush_get_context('DRUSH_DEBUG_NOTIFY');

        $oldStyleEarlyExit = drush_get_context('DRUSH_LEGACY_CONTEXT');

        // Save the original level in the context name, then
        // map it to a standard log level.
        $context['name'] = $level;
        switch ($level) {
            case LogLevel::WARNING:
            case LogLevel::CANCEL:
                $type_msg = sprintf($yellow, $level);
                $level = LogLevel::WARNING;
                break;
            case 'failed': // Obsolete; only here in case contrib is using it.
            case LogLevel::EMERGENCY: // Not used by Drush
            case LogLevel::ALERT: // Not used by Drush
            case LogLevel::ERROR:
                $type_msg = sprintf($red, $level);
                break;
            case LogLevel::OK:
            case 'completed': // Obsolete; only here in case contrib is using it.
            case LogLevel::SUCCESS:
            case 'status': // Obsolete; only here in case contrib is using it.
                // In quiet mode, suppress progress messages
                if ($oldStyleEarlyExit && drush_get_context('DRUSH_QUIET')) {
                    return true;
                }
                $type_msg = sprintf($green, $level);
                $level = LogLevel::NOTICE;
                break;
            case LogLevel::NOTICE:
                $type_msg = sprintf("[%s]", $level);
                break;
            case 'message': // Obsolete; only here in case contrib is using it.
            case LogLevel::INFO:
                if ($oldStyleEarlyExit && !$verbose) {
                    // print nothing. exit cleanly.
                    return true;
                }
                $type_msg = sprintf("[%s]", $level);
                $level = LogLevel::INFO;
                break;
            case LogLevel::DEBUG_NOTIFY:
                $level = LogLevel::DEBUG; // Report 'debug', handle like 'preflight'
            case LogLevel::PREFLIGHT:
                if ($oldStyleEarlyExit && !$debugnotify) {
                    // print nothing unless --debug AND --verbose. exit cleanly.
                    return true;
                }
                $type_msg = sprintf("[%s]", $level);
                $level = LogLevel::DEBUG;
                break;
            case LogLevel::BOOTSTRAP:
            case LogLevel::DEBUG:
            default:
                if ($oldStyleEarlyExit && !$debug) {
                    // print nothing. exit cleanly.
                    return true;
                }
                $type_msg = sprintf("[%s]", $level);
                $level = LogLevel::DEBUG;
                break;
        }

        // When running in backend mode, log messages are not displayed, as they will
        // be returned in the JSON encoded associative array.
        if (\Drush\Drush::backend()) {
            return;
        }

        $columns = drush_get_context('DRUSH_COLUMNS', 80);

        $width[1] = 11;
        // Append timer and memory values.
        if ($debug) {
            $timer = sprintf('[%s sec, %s]', round($entry['timestamp']-DRUSH_REQUEST_TIME, 2), drush_format_size($entry['memory']));
            $entry['message'] = $entry['message'] . ' ' . $timer;
            $message = $message . ' ' . $timer;
        }

/*
      // Drush-styled output

      $message = $this->interpolate(
          $message,
          $this->getLogOutputStyler()->style($context)
      );

      $width[0] = ($columns - 11);

      $format = sprintf("%%-%ds%%%ds", $width[0], $width[1]);

      // Place the status message right aligned with the top line of the error message.
      $message = wordwrap($message, $width[0]);
      $lines = explode("\n", $message);
      $lines[0] = sprintf($format, $lines[0], $type_msg);
      $message = implode("\n", $lines);
      $this->getErrorStreamWrapper()->writeln($message);
*/
      // Robo-styled output
        parent::log($level, $message, $context);
    }

    public function error($message, array $context = [])
    {
        $error_log =& drush_get_context('DRUSH_ERROR_LOG', []);
        $error_log[$message][] = $message;
        parent::error($message, $context);
    }
}
