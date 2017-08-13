<?php

if (!defined("REQUEST_START_TIME")) {
    define("REQUEST_START_TIME", microtime(true));
}

function j($value)
{
    return json_encode($value, JSON_PRETTY_PRINT);
}

function isCommandLineMode()
{
    return (php_sapi_name() == "cli");
}

function __get_called_reference()
{
    $backtrace = debug_backtrace();

    $current_file = file_get_contents(__FILE__);
    preg_match_all('~^\s*function\s*(\w*?)\(~m', $current_file, $matches);
    $functions_to_ignore = $matches[1];
    $functions_to_ignore[] = 'call_user_func_array';

    $backtrace_item = current($backtrace);
    $debug_call = $backtrace_item;
    while (in_array($backtrace_item["function"], $functions_to_ignore)) {
        $debug_call = $backtrace_item;
        $backtrace_item = array_shift($backtrace);
        if (!$backtrace_item) {
            break;
        }
    }

    $reference = @$debug_call['file'] . ":" . @$debug_call['line'];

    return (string)$reference;
}

function __beautifyLocation($text)
{
    $text = str_replace(base_path() . '/', '', $text);
    // $text = preg_replace('~(/[\w]+/[\w]+[/\w\.]+)~', '<a href="php://\1">\1</a>', $text);
    return $text;
}

function mb_lcfirst($value)
{
    return mb_strtolower(mb_substr($value, 0, 1)) . mb_substr($value, 1);
}

function mb_ucfirst($value)
{
    return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
}

function __wrap_output($contents)
{
    static $lastTimestamp;
    $location = __get_called_reference();

    $sinceText = "last dump";
    if (!$lastTimestamp) {
        $lastTimestamp = REQUEST_START_TIME;
        $sinceText = (isCommandLineMode() ? "script" : "request") . " started";
    }
    $message = sprintf("%d ms since %s", 1000 * (microtime(true) - $lastTimestamp), $sinceText);
    if (isCommandLineMode()) {
        echo "\033[1;45m" . PHP_EOL; // set white/violet background
        echo "{$location} ({$message})\n" . $contents;
        echo "\033[0m" . PHP_EOL; // reset bg to silver/black
    } else {
        $location = __beautifyLocation($location);
        echo "</style></script>"; // to see debug even if it's inside style/script or an attribute
        echo <<<HTML
</style></script>
<style type="text/css">
.ddump {
    font: 12px Monospace;
    overflow-y: scroll;
    text-transform: none;
    max-height: 1000px;
    background: #FCC;
    color: #000;
    padding: 5px;
    margin: 2px;
    text-align: left
}
.ddump a {
    color: inherit;
}
.sf-dump {
    line-height: 1.5;
    border: none;
    background: inherit !important;
    padding: 0 !important;
}
.ddump__reference {
    font-size: 110%;
    opacity: 0.5;
    margin-bottom: 1ex;
}
</style>
<pre class="ddump">
<div class="ddump__reference">{$location}</div>{$contents}</pre>
HTML;
    }
    $lastTimestamp = microtime(true);
}

function capture_output(Closure $closure)
{
    ob_start();
    $closure();
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
}

/**
 * return debug_backtrace() stripped from data (only stack of files/functions/line numbers)
 * @param null $debug_backtrace
 * @return array
 */
function simple_debug_backtrace($debug_backtrace = null)
{
    if (!$debug_backtrace) {
        $debug_backtrace = debug_backtrace();
    }
    $simple_backtrace = cleanBacktraceFromObjectsDump($debug_backtrace);
    return $simple_backtrace;
}

/**
 * Recursively substitute all objects dumps with just a name of the class
 * @param $debug_backtrace
 * @param int $depth
 * @return array
 */
function cleanBacktraceFromObjectsDump($debug_backtrace, $depth = 0)
{
    $result = [];
    foreach ($debug_backtrace as $key => $call) {
        if (is_object($call)) {
            $call = "object(" . get_class($call) . ") { ... }";
        } elseif (is_array($call)) {
            if ($depth > 10) { // to avoid max depth of nested function calls
                $call = [];
            } else {
                $call = cleanBacktraceFromObjectsDump($call, $depth + 1);
            }
        }
        $result[$key] = $call;
    }
    return (array)$result;
}

/**
 * Like laravel's dd() but without die()
 */
if (!function_exists('d')) {
    /**
     * Dump the passed variables and end the script.
     *
     * @param  mixed
     * @return void
     */
    function d()
    {
        if (config('app.env') == 'prod') {
            return;
        }
        $args = func_get_args();
        __wrap_output(trim(capture_output(function () use ($args) {
            array_map(function ($x) {
                (new \Illuminate\Support\Debug\Dumper)->dump($x);
            }, $args);
        })));
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the passed variables and end the script.
     *
     * @param  mixed
     * @return void
     */
    function dd()
    {
        if (config('app.env') == 'prod') {
            return;
        }
        d(...func_get_args());
        printf("Died because of dd() on %.3f second of executing\n", microtime(1) - REQUEST_START_TIME);
        die();
    }
}
