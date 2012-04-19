<?php
/**
 * Console command bahavior. Hack for protect exiting by memory leaks on console actions 
 * 
 * @author Evgeny Blinov <e.a.blinov@gmail.com>
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */

/**
 * @author Evgeny Blinov <e.a.blinov@gmail.com>
 * @package MemLeaksProtectorCommandBehavior
 */
class MemLeaksProtectorCommandBehavior extends CConsoleCommandBehavior{
    private $memoryLimit;
    private $processId;
    public  $processCount    = 1;
    public  $memoryThreshold = 0.95;
    public  $exitCode;

    protected function beforeAction($event) {
        if ($this->exitCode === null) $this->exitCode = rand(2, 254);
        $this->memoryLimit = self::getIniBytes(ini_get('memory_limit'));
        $iteration = 0;
        do {
            ++$iteration;
            if($iteration === 1 || pcntl_wexitstatus($status) == $this->exitCode){
                for ($i=0;$i<$this->processCount;++$i){
                    $pid = pcntl_fork();
                    $this->afterFork();
                    if ($pid === -1) throw new CException("Can't fork");
                    elseif ($pid) {
                        if ($iteration === 1) Yii::trace("Start action '{$event->action}' as pid ".$pid, 'ext.'.__CLASS__);
                        else                  Yii::log ('Restart action', CLogger::LEVEL_WARNING, 'ext.'.__CLASS__);
                    }
                    else{
                        $this->processId = $i;
                        return;
                    }
                }
                $this->processCount = 1;
            }
        } while (pcntl_wait($status) > 0);
        Yii::trace("Done action '{$event->action}'");
        $event->continue = false;
    }

    public function protectLeaks(){
        if (memory_get_usage()/$this->memoryLimit > $this->memoryThreshold){
            Yii::log('Exit by memory_limit', CLogger::LEVEL_WARNING, 'ext.W3ConsoleCommandBehaviors.'.__CLASS__);
            $this->restartThread();
        }
    }
    public function restartThread(){
        Yii::app()->end($this->exitCode, true);
    }
    public function getProcessId(){
        return $this->processId;
    }

    private static function getIniBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
    private function afterFork(){
        $Db = Yii::app()->getDb();
        $Db->setActive(false);
        $Db->setActive(true);
    }
}