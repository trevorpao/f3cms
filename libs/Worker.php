<?php

namespace F3CMS;

class Worker extends Helper
{
    public $class;
    public $method;
    public $cuClass;
    public $logger;

    // ! Instantiate class
    /**
     * @param $module
     * @param $method
     * @param $logger
     */
    public function __construct($module = '', $method = '', $logger = null)
    {
        parent::__construct();
        if ('' != $module && '' != $method) {
            $this->_register($module, $method);
        } else {
            $module = 'worker';
        }

        if (null != $logger) {
            $this->logger = $logger;
        } else {
            $this->logger = new \Log(str_replace('\\', '_', $module) . '_' . $method . '.log');
        }
    }

    /**
     * @param array $obj
     * @param       $mode
     */
    public function startWorker($obj = [''], $mode = 'All')
    {
        $i        = 0;
        $children = [];
        $doneAry  = [];
        foreach ($obj as $k => $v) {
            $this->logger->write('param====>' . $v . PHP_EOL);
            $pid = pcntl_fork();
            ++$i;
            $this->logger->write('pid====>' . $pid . PHP_EOL);
            switch ($pid) {
                case -1:
                    exit('Could not fork');
                case 0:
                    // child
                    $this->_runChild($v);
                    exit(0);
                default:
                    // parent
                    $children[] = $pid;
            }

            // -- check process one by one
            if ('All' !== $mode) {
                while (-1 != pcntl_waitpid(0, $status)) {
                    $this->logger->write('Child ' . $v . ' completed' . PHP_EOL);
                    $doneAry[] = $v;
                }
            } else {
                $doneAry[] = $v;
            }
        }

        // -- check after start all processes
        if ('All' === $mode) {
            while (count($children) > 0) {
                foreach ($children as $key => $pid) {
                    $res = pcntl_waitpid($pid, $status, WNOHANG);

                    // If the process has already exited
                    if (-1 == $res || $res > 0) {
                        unset($children[$key]);
                        $this->logger->write("Child {$pid} completed" . PHP_EOL);
                    }
                }
            }
        }

        return $doneAry;
    }

    /**
     * @param $module
     * @param $method
     */
    private function _register($module, $method)
    {
        // Create an instance of the module class.
        $class  = ucfirst($module);
        $method = sprintf('%s', $method);

        // Check if the action has a corresponding method.
        if (!method_exists($class, $method)) {
            exit('1004::' . $class . '->' . $method . PHP_EOL);
        }

        $this->cuClass = $class . '::' . $method;

        // Create a reflection instance of the module, and obtaining the action method.
        $reflectionClass = new \ReflectionClass($class);

        $this->class  = $reflectionClass->newInstance();
        $this->method = $reflectionClass->getMethod($method);
    }

    /**
     * @param $value
     */
    private function _runChild($value)
    {
        // $this->logger->write("In child {$value} ".PHP_EOL);

        try {
            usleep(5000); // sleep 0.005s
            // throw new Exception("Error Processing Request ({$value })", 1);

            // Invoke module action.
            $this->logger->write($this->cuClass . ' return ' . $this->method->invokeArgs(
                $this->class,
                [$value]
            ));
        } catch (\Exception $e) {
            $this->logger->write('Caught exception: ' . $e->getMessage() . PHP_EOL);
        }
    }
}
