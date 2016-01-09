<?php
/**
 * Piwik Visit Export and Import Plugin
 *
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\VisitExportAndImport\Commands;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Plugin\ConsoleCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Example:
 * ./console visit-export --startDate=2015-12-20 --endDate=2015-12-20
 */
class VisitExport extends ConsoleCommand
{
    /**
     * @var string|null
     */
    private $startDate;

    /**
     * @var string|null
     */
    private $endDate;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param string|null $name
     * @param LoggerInterface|null $logger
     */
    public function __construct($name = null, LoggerInterface $logger = null)
    {
        // TODO: Replace StaticContainer with DI
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('visit-export')
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->setDescription('Export Piwik visit data for a specific period.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '1024M');

        $this->startDate = $input->getOption('startDate') . ' 00:00:00';
        $this->endDate = $input->getOption('endDate') . ' 23:59:59';

        $visits = $this->exportVisits();
        $this->logger->info('Got ' . count($visits) . ' visits.');

        $visitActionLinks = $this->exportVisitActionLinks($visits);
        $this->logger->info('Got ' . count($visitActionLinks) . ' visit-action-links.');

        $actions = $this->exportActions($visitActionLinks);
        $this->logger->info('Got ' . count($actions) . ' actions.');

        $conversions = $this->exportConversions($visits);
        $this->logger->info('Got ' . count($conversions) . ' conversions.');

        // Store results in visit-export.json
        file_put_contents(
            'visit-export.json',
            json_encode([
                'log_visit' => $visits,
                'log_action' => $actions,
                'log_link_visit_action' => $visitActionLinks,
                'log_conversion' => $conversions,
            ])
        );

        $this->logger->info('Export successful. Stored results in "visit-export.json".');
    }

    /**
     * Exports log_visit.
     * @return array
     * @throws \Exception
     */
    private function exportVisits()
    {
        $visits = DB::fetchAll(
            'SELECT * FROM  ' . Common::prefixTable('log_visit')
                . '  WHERE visit_first_action_time >= ? AND visit_first_action_time <= ?',
            [
                $this->startDate,
                $this->endDate,
            ]
        );

        // bin2hex
        foreach ($visits as &$visit) {
            $visit['idvisitor'] = bin2hex($visit['idvisitor']);
            $visit['config_id'] = bin2hex($visit['config_id']);
            $visit['location_ip'] = bin2hex($visit['location_ip']);
        }

        return $visits;
    }

    /**
     * Exports log_link_visit_action.
     *
     * @param array $visits
     * @return array
     * @throws \Exception
     */
    private function exportVisitActionLinks(array $visits)
    {
        $visitActionLinks = DB::fetchAll(
            'SELECT * FROM  ' . Common::prefixTable('log_link_visit_action')
                . '  WHERE idvisit IN ('
                . implode(', ', array_map(function($visit) { return $visit['idvisit']; }, $visits)) . ')'
        );

        // bin2hex
        foreach ($visitActionLinks as &$visitActionLink) {
            $visitActionLink['idvisitor'] = bin2hex($visitActionLink['idvisitor']);
        }

        return $visitActionLinks;
    }

    /**
     * Exports log_action.
     *
     * @param array $visitActionLinks
     * @return array
     * @throws \Exception
     */
    private function exportActions(array $visitActionLinks)
    {
        $actions = [];

        // Split IDs into chunks to keep SQL-queries short
        $chunks = array_chunk(array_filter(array_unique(
            array_merge(
                array_map(function($visitActionLink) {
                    return $visitActionLink['idaction_url_ref'];
                }, $visitActionLinks),
                array_map(function($visitActionLink) {
                    return $visitActionLink['idaction_name_ref'];
                }, $visitActionLinks),
                array_map(function($visitActionLink) {
                    return $visitActionLink['idaction_name'];
                }, $visitActionLinks),
                array_map(function($visitActionLink) {
                    return $visitActionLink['idaction_url'];
                }, $visitActionLinks)
            )
        ), function($e) { return null !== $e; }), 500);

        foreach ($chunks as $chunk) {
            $query = 'SELECT * FROM  ' . Common::prefixTable('log_action')
                . '  WHERE idaction IN (' . implode(', ', $chunk) . ')';

            $actions = array_merge($actions, DB::fetchAll($query));
        }

        return $actions;
    }

    /**
     * Exports conversions.
     *
     * @param array $visits
     * @return array
     * @throws \Exception
     */
    private function exportConversions(array $visits)
    {
        $conversions = DB::fetchAll(
            'SELECT * FROM  ' . Common::prefixTable('log_conversion')
            . '  WHERE idvisit IN ('
            . implode(', ', array_map(function($visit) { return $visit['idvisit']; }, $visits)) . ')'
        );

        // bin2hex
        foreach ($conversions as &$conversion) {
            $conversion['idvisitor'] = bin2hex($conversion['idvisitor']);
        }

        return $conversions;
    }
}
