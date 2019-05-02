<?php

namespace Aslv\Cron\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Cron\Model\ConfigInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * CronRemoveCommand removes Magento cron tasks
 */
class CronCommand extends Command
{
    const COMMAND_NAME = 'cron:run-now';
    const JOB_CODE = 'job-code';

    /**
     * @var float
     */
    private $startTime = 0.0;

    /**
     * @var float
     */
    private $endTime = 0.0;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * CronCommand constructor.
     * @param ObjectManagerInterface $objectManager
     * @param ConfigInterface $config
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ConfigInterface $config
    ) {
        $this->objectManager = $objectManager;
        $this->config = $config;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(static::COMMAND_NAME)
            ->setDescription('Run cron job by code immediately')
            ->setDefinition([
                new InputArgument(static::JOB_CODE, InputArgument::REQUIRED, 'Cron job code')
            ]);

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->start();
        $jobCode = $input->getArgument(static::JOB_CODE);
        $jobConfig = $this->getJobConfig($jobCode);
        if (!$jobConfig) {
            $output->writeln(
                '<error>' . sprintf('Invalid job code <%s>, can\'t find it in config', $jobCode) . '</error>'
            );
            return Cli::RETURN_FAILURE;
        }

        try {
            $model = $this->objectManager->create($jobConfig['instance']);
            $callback = [$model, $jobConfig['method']];
            if (!is_callable($callback)) {
                throw new LocalizedException(
                    __('Invalid callback: %1::%2 can\'t be called', $jobConfig['instance'], $jobConfig['method'])
                );
            }
            call_user_func($callback);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $this->end();
        $elapsedTime = $this->getElapsedTime();
        $output->writeln(
            '<info>' . sprintf('Magento cron task has been executed, spent time: %s sec', $elapsedTime) . '</info>'
        );

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param $jobCode
     * @return array|null
     */
    private function getJobConfig($jobCode)
    {
        $groupedJobs = $this->config->getJobs();
        foreach ($groupedJobs as $group => $jobs) {
            foreach ($jobs as $job) {
                if ($job['name'] === $jobCode) {
                    return $job;
                }
            }
        }

        return null;
    }

    /**
     * Starts the time tracking.
     *
     * @return void
     */
    private function start()
    {
        $this->startTime = microtime(true) * 1000.0;
    }

    /**
     * Stops the time tracking.
     *
     * @return void
     */
    private function end()
    {
        $this->endTime = microtime(true) * 1000.0;
    }

    /**
     * Returns the total time elapsed.
     *
     * @return float
     */
    private function getElapsedTime()
    {
        return round($this->endTime - $this->startTime, 2) / 1000;
    }
}
