<?php

class Shopware_Components_Migration_DbDecorator {

    /**
     * The decorated Zend_Db instance
     * @var
     */
    protected $instance;

    /**
     * Logable method calls
     * @var array
     */
    protected $logable = array(
        'fetchOne',
        'fetchCol',
        'fetchPairs',
        'fetchRow',
        'fetchAll',
        'fetchAssoc',
        'query',
        'execute'
    );

    /**
     * @param $instance
     */
    public function __construct($instance)
    {
        $this->instance = $instance;
    }

    /**
     * @param $args
     * @return string
     * @throws \Exception If explain certain statements is not possible
     */
    public function explain($args)
    {
        $sql = 'EXPLAIN ' . $args[0];

        $rows = $this->instance->fetchAll($sql, $args);

        // Get the column headers and put them first
        $head = array_keys($rows[0]);
        array_unshift($rows, $head);

        // Remove associative keys
        foreach ($rows as &$row) {
            $row = array_values($row);
        }

        // Determine longest row
        $length = array();
        foreach ($rows as $r) {
            foreach ($r as $c => $column) {
                $length[$c] =  strlen($column) > $length[$c] ? strlen($column) : $length[$c];
            }
        }

        // format the rows
        $result = array();
        foreach($rows as &$row) {
            foreach ($row as $c => &$column) {
                $column = sprintf("%-{$length[$c]}s", $column);
            }
            $result[] = implode(" | ", $row);
        }
        // Concatenate the rows with newline chars
        return implode("\r\n", $result);

    }

    /**
     * Main wrapper method which decorated the actual query with some debug output
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        // Print header
        if (in_array($method, $this->logable)) {
            $this->printBeginBlock($args);
        }

        // Run the actual query and measure the time
        $start = microtime();
        $result = call_user_func_array(array($this->instance, $method), $args);
        $duration = microtime() - $start;

        // Print footer (explain, duration, separator)
        if (in_array($method, $this->logable)) {
            $this->printEndBlock($args, $result, $duration);
        }

        return $result;

    }

    public function __get($key) {
        return $this->instance->$key;
    }

    public function __set($key, $value) {
        return $this->instance->$key = $value;
    }

    /**
     * Simple logger which writes all queries to the file system
     * @param $data
     * @param $suffix
     */
    public function debug($data, $suffix = null)
    {
        $base = Shopware()->DocPath('media_' . 'temp');
        $path = $base . 'migration';
        if ($suffix) {
            $path .= '_' . $suffix;
        }
        $path .= '.log';


        error_log(print_r($data, true)."\r\n", '3', $path);

    }

    /**
     * @param $args
     * @return string
     */
    public function printBeginBlock($args)
    {
        $callers = debug_backtrace();
        $caller = array_map(
            function ($arr) {
                return $arr['function'];
            },
            array_reverse(array_slice($callers, 2, 5))
        );
        $caller = implode('=>', $caller);

        $begin_line = '>>> ' . $caller;
        $this->debug($begin_line);
        $this->debug($args[0]);
    }

    /**
     * @param $args
     * @param $result
     * @param $duration
     * @param $begin_line
     */
    public function printEndBlock($args, $result, $duration)
    {
        $rows = 'Unknown';
        if (method_exists($result, 'rowCount')) {
            $rows = $result->rowCount();
        }

        $explained = $this->explain($args);

        try {
            $this->debug("\r\nExplain:\r\n" . print_r($explained, true) . "\r\n");
        } catch(Exception $e) {
            // Query is not explainable
        }

        $this->debug("Duration: " . $duration);
        $this->debug("RowCount: " . $rows);
        $this->debug("\r\n\r\n");
    }
}