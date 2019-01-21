<?php
namespace F3CMS;

class Worker extends Helper
{
    /**
     * @param $module
     * @param $method
     * @param $logger
     */
    public function __construct($module = '', $method = '', $logger = null)
    {
        parent::__construct();
        if ($module != '' && $method != '') {
            $this->_register($module, $method);
        } else {
            $module = 'worker';
        }

        if ($logger != null) {
            $this->logger = $logger;
        } else {
            $this->logger = new \Log($module . '_' . $method . '.log');
        }
    }

    /**
     * @param $obj
     * @param $mode
     */
    public function startWorker($obj, $mode = 'All')
    {
        $i = 0;
        $children = array();
        $doneAry = array();
        foreach ($obj as $k => $v) {
            $pid = pcntl_fork();
            $i++;
            $this->logger->write('pid====>' . $pid . PHP_EOL);
            switch ($pid) {
                case -1:
                    die('Could not fork');
                case 0:
                    // child
                    $this->_runChild($v);
                    die(0);
                default:
                    //parent
                    $children[] = $pid;
            }

            // -- check process one by one
            if ($mode !== 'All') {
                while (pcntl_waitpid(0, $status) != -1) {
                    $this->logger->write('Child ' . $v . ' completed' . PHP_EOL);
                    $doneAry[] = $v;
                }
            } else {
                $doneAry[] = $v;
            }
        }

        // -- check after start all processes
        if ($mode === 'All') {
            while (count($children) > 0) {
                foreach ($children as $key => $pid) {
                    $res = pcntl_waitpid($pid, $status, WNOHANG);

                    // If the process has already exited
                    if ($res == -1 || $res > 0) {
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
        $class = ucfirst($module);
        $method = sprintf('%s', $method);

        // Check if the action has a corresponding method.
        if (!method_exists($class, $method)) {
            die('1004::' . $class . '->' . $method . PHP_EOL);
        }

        // Create a reflection instance of the module, and obtaining the action method.
        $reflectionClass = new \ReflectionClass($class);

        $this->class = $reflectionClass->newInstance();
        $this->method = $reflectionClass->getMethod($method);
    }

    /**
     * @param $value
     */
    private function _runChild($value)
    {
        $this->logger->write("In child {$value} " . PHP_EOL);

        try {
            usleep(5000); // sleep 0.005s
            // throw new Exception("Error Processing Request ({$value })", 1);

            // Invoke module action.
            $this->method->invokeArgs(
                $this->class,
                array($value)
            );
        } catch (\Exception $e) {
            $this->logger->write('Caught exception: ' . $e->getMessage() . PHP_EOL);
        }
    }
}
