<?php
declare (strict_types=1);

namespace app\command;

use app\util\ConsoleTable;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class Chown extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        // 指令配置
        $this->setName('app\command\chown')
            ->setDescription('Change project directory owner');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $command = sprintf('chown -R nginx:nginx %s', '/var/www/md_intel/*');
        exec($command, $res, $code);
        $output->writeln('Code:' . var_export($code, true));
    }
}
