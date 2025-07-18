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

class DbInit extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        // 指令配置
        $this->setName('app\command\dbinit')
            ->setDescription('Init database');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $sqlFile = root_path('database') . 'md_intel.sql';
        $output->writeln($sqlFile);
        $sqlLine = '';
        $fd      = fopen($sqlFile, 'r');
        $table   = new ConsoleTable();
        $table->setHeaders(['SQL', 'Result']);
        while (!feof($fd)) {
            $sqlLine .= fgets($fd);
            if (stripos($sqlLine, ';') !== false) {
                $sqlLine = trim($sqlLine);
                $sql     = explode(PHP_EOL, $sqlLine)[0];
                $count   = Db::execute($sqlLine);
                $table->addRow([$sql, var_export($count, true)]);
                $sqlLine = '';
            }
        }
        $output->writeln($table->getTable());
        fclose($fd);
        $output->writeln('Init database done');
    }
}
