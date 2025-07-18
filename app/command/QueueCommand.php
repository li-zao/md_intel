<?php

declare(strict_types=1);

namespace app\command;

use app\model\CommonUtil;
use app\model\Jobs;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class QueueCommand extends Command
{
    public const QUEUE_PARAMS = [
        Jobs::QUEUE_URL_SNAPSHOT => ' --timeout=0',
        // Jobs::QUEUE_VECTOR       => ' --timeout=0',
        // Jobs::QUEUE_URL_IMPORT   => ' --timeout=0',
        // Jobs::QUEUE_URL_2_VECTOR => ' --timeout=0',
        // Jobs::QUEUE_URL_2_TRAIN  => ' --timeout=0',
    ];

    protected function configure()
    {
        // 指令配置
        $this->setName('queue_command')
            ->addOption('kill', 'k', Option::VALUE_OPTIONAL, 'Kill all queue', false)
            ->setDescription('队列命令执行');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        if ($input->hasOption('kill')) {
            $this->killQueue();
            return;
        }
        $this->startQueue();
    }

    /**
     * @param $queue
     * @return void
     */
    private function startQueue($queue = [])
    {
        unset($res);
        $res = [];
        if (empty($queue)) {
            $queue = Jobs::ALL_QUEUE;
            for ($i = 1; $i <= Jobs::getVectorQueueThreat(); $i++) {
                $queue[] = sprintf('%s%s', Jobs::QUEUE_VECTOR, $i);
            }
        }
        foreach ($queue as $item) {
            unset($res);
            @exec(sprintf('ps -ef |grep "think queue:listen --queue=%s"|grep -v grep', $item), $res);
            $params = self::QUEUE_PARAMS[$item] ?? ' --timeout=0';
            if (count($res) < 1) {
                $comm = sprintf(
                    '/usr/bin/php /var/www/md_intel/think queue:listen --queue="%s" %s> /dev/null &',
                    $item,
                    $params
                );
                CommonUtil::execShell($comm);
            }
        }
    }

    /**
     * @param $queue
     * @return void
     */
    private function killQueue($queue = [])
    {
        if (empty($queue)) {
            $queue = Jobs::ALL_QUEUE;
            for ($i = 1; $i <= Jobs::getVectorQueueThreat(); $i++) {
                $queue[] = sprintf('%s%s', Jobs::QUEUE_VECTOR, $i);
            }
        }
        foreach ($queue as $item) {
            CommonUtil::execShell(sprintf(
                'for pid in $(ps -ef | awk \'/\/usr\/bin\/php \/var\/www\/md_intel\/think queue:listen --queue=%s/ {print $2}\'); do kill -9 $pid; done',
                $item
            ));
            CommonUtil::execShell(sprintf(
                'for pid in $(ps -ef | awk \'/\/usr\/bin\/php think queue\:work database --once --queue=%s/ {print $2}\'); do kill -9 $pid; done',
                $item
            ));
        }
    }
}
