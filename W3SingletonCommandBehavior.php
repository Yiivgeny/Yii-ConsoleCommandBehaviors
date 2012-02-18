<?php
/**
 * Console command behavior blocked double action running at same time
 * 
 * @author Evgeny Blinov <e.a.blinov@gmail.com>
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */

/**
 * @author Evgeny Blinov <e.a.blinov@gmail.com>
 * @package W3ConsoleCommandBehaviors
 */
class W3SingletonCommandBehavior extends CConsoleCommandBehavior{

    protected function getFilename($event){
        return Yii::app()->getRuntimePath().'/ConsoleCommand_'.get_class($event->sender).'-'.$event->action.'.action';
    }
    protected function isActivePid($pid) {
        return (@pcntl_getpriority($pid) !== false);
    }

    protected function beforeAction($event) {
        $filename = $this->getFilename($event);
        if (
            file_exists($filename) &&
            ($pid = file_get_contents($filename)) &&
            $this->isActivePid($pid)
        ) {
            Yii::log('Console command action already running as '.$pid, CLogger::LEVEL_WARNING, 'ext.W3ConsoleCommandBehaviors.'.__CLASS__);
            $event->continue = false;
            return;
        }
        file_put_contents($filename, getmypid());
    }

    protected function afterAction($event) {
        @unlink($this->getFilename($event));
    }

}
