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
 * ./console visit-import
 */
class VisitImport extends ConsoleCommand
{
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
            ->setName('visit-import')
            ->setDescription('Import Piwik visit data from "visit-export.json".');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '1024M');

        $data = @json_decode(@file_get_contents('visit-export.json'), true);
        if (!$data) {
            $this->logger->critical('No data found in "visit-export.json".');
            return;
        }

        $this->importVisits($data['log_visit']);
        $this->logger->info('Imported visits.');

        $this->importActions($data['log_action']);
        $this->logger->info('Imported actions.');

        $this->importVisitActionLinks($data['log_link_visit_action']);
        $this->logger->info('Imported visit-action-links.');

        $this->importConversions($data['log_conversion']);
        $this->logger->info('Imported conversions.');

        $this->logger->info('Import successful.');
    }

    /**
     * Fills log_visit.
     *
     * @param array $visits
     * @throws \Exception
     */
    private static function importVisits(array $visits)
    {
        foreach ($visits as $visit) {

            // Delete existing record to prevent duplicate key errors
            Db::query('DELETE FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ' . $visit['idvisit']);

            // hex2bin
            $visit['idvisitor'] = hex2bin($visit['idvisitor']);
            $visit['config_id'] = hex2bin($visit['config_id']);
            $visit['location_ip'] = hex2bin($visit['location_ip']);

            Db::query(self::buildSqlQuery('log_visit', $visit), array_values($visit));
        }
    }

    /**
     * Fills log_action.
     *
     * @param array $actions
     * @throws \Exception
     */
    private static function importActions(array $actions)
    {
        foreach ($actions as $action) {

            // Delete existing record to prevent duplicate key errors
            Db::query('DELETE FROM ' . Common::prefixTable('log_action') . ' WHERE idaction = ' . $action['idaction']);

            Db::query(self::buildSqlQuery('log_action', $action), array_values($action));
        }
    }

    /**
     * Fills log_link_visit_action.
     *
     * @param array $visitActionLinks
     * @throws \Exception
     */
    private static function importVisitActionLinks(array $visitActionLinks)
    {
        foreach ($visitActionLinks as $visitActionLink) {

            // Delete existing record to prevent duplicate key errors
            Db::query('DELETE FROM ' . Common::prefixTable('log_link_visit_action')
                . ' WHERE idlink_va = ' . $visitActionLink['idlink_va']);

            // hex2bin
            $visitActionLink['idvisitor'] = hex2bin($visitActionLink['idvisitor']);

            Db::query(self::buildSqlQuery('log_link_visit_action', $visitActionLink), array_values($visitActionLink));
        }
    }

    /**
     * Fills log_conversion.
     *
     * @param array $conversions
     * @throws \Exception
     */
    private static function importConversions(array $conversions)
    {
        foreach ($conversions as $conversion) {

            // Delete existing records to prevent duplicate key errors
            Db::query('DELETE FROM ' . Common::prefixTable('log_conversion')
                . ' WHERE idvisit = ' . $conversion['idvisit']
                . ' AND server_time = "' . $conversion['server_time'] . '"');

            // hex2bin
            $conversion['idvisitor'] = hex2bin($conversion['idvisitor']);

            Db::query(self::buildSqlQuery('log_conversion', $conversion), array_values($conversion));
        }
    }

    /**
     * @param string $table The table's name without prefix.
     * @param array $entity The entity to be imported.
     * @return string
     */
    private static function buildSqlQuery($table, array $entity)
    {
        return 'INSERT INTO ' . Common::prefixTable($table)
            . ' (' . implode(', ', array_keys($entity)) . ') '
            . 'VALUE (' . implode(',', array_fill(0, count(array_keys($entity)), '?')) . ')';
    }
}
