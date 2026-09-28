<?php

/**
 *
 * 06/05/26 CB created from plg_mailman
 * 28/09/26 CB changed to process multiple sites
 * 
 * This should have an option to specify the number of users to load at a time, to avoid memory issues.
 */

namespace Ramblers\Plugin\Console\Ra_members\Command;

\defined('JPATH_PLATFORM') or die;
use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ramblers\Component\Ra_members\Site\Helper\LoadHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class LoadusersCommand extends AbstractCommand {

    /**
     * The default command name
     *
     * @var    string
     *
     * @since  4.0.0
     */
    protected static $defaultName = 'ra_members:loadusers';

    /**
     * @var InputInterface
     * @since version
     */
    private $app;
    private $cliInput;
    private $db;
    private $loadUsers;
    private $toolsHelper;
    protected $ref;
    
    /**
     * SymfonyStyle Object
     * @var SymfonyStyle
     * @since 4.0.0
     */
    private $ioStyle;

    /**
     * Instantiate the command.
     *
     * @since   4.0.0
     */
    public function __construct() {
        parent::__construct();
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->loadUsers = new LoadHelper;
        $this->app = Factory::getApplication();
    }

    /**
     * Initialise the command.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    protected function configure(): void {
        $help = "<info>%command.name%</info> Load users
            \nUsage: <info>php %command.full_name% </info>
            \nThis command loads users for specified Groups.";

        $this->setDescription('Load users from records in ra_api_sites.');
        $this->setHelp($help);

    }

    /**
     * Configures the IO
     *
     * @param   InputInterface   $input   Console Input
     * @param   OutputInterface  $output  Console Output
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function configureIO(InputInterface $input, OutputInterface $output) {
        $this->cliInput = $input;
        $this->ioStyle = new SymfonyStyle($input, $output);
    }

    /**
     * Internal function to execute the command.
     *
     * @param   InputInterface   $input   The input to inject into the command.
     * @param   OutputInterface  $output  The output to inject into the command.
     *
     * @return  integer  The command exit code
     *
     * @since   4.0.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int {
        $this->configureIO($input, $output);
        $this->logit('Processing started', '1');
        $this->ioStyle->comment('Processing started');

       
        $sql = 'SELECT id FROM #__ra_api_sites WHERE sub_system=' . $this->db->quote('RA Members') ;
        $sql .= ' ORDER BY title';
        $this->ioStyle->comment($sql);
        $rows = $this->toolsHelper->getRows($sql);
        $count = 0; 
        $overall = true;
        foreach ($rows as $row) {
            $count++;
            $this->ioStyle->comment('Loading users for api-site-id=' . $row->id);
            $this->logit('Loading users for api-site-id=' . $row->id, '3');
            $result = $this->loadUsers->loadMembers($row->id);
            if (!$result) {
                $this->ioStyle->error('Processing failed for api-site-id=' . $row->id);
                $this->logit('Processing failed for api-site-id=' . $row->id, '9');
                $overall = $false;
            }
            foreach ($this->loadUsers->messages as $message) {
                $this->ioStyle->comment($message);
                $this->logit($message, '3');
            }
        }
        if (!$overall) {
            $this->ioStyle->error('Processing errors found.');
            $this->logit('Processing Processing errors found', '8');
            return 1;
        }
        
        $this->ioStyle->comment('Processing complete');
        $this->logit('Processing complete', '9');
        return 0;
    }

        /**
     *   Store a log entry
     */
    public function logit($text, $record_type = '3') {

        $query = $this->db->getQuery(true);

        $query->insert('#__ra_logfile')
                ->set("record_type = " . $this->db->quote($record_type))
                ->set("sub_system = " . $this->db->quote('RA Members'))
                ->set("message = " . $this->db->quote($text))
                ->set("ref = " . $this->db->quote($this->ref))
        ;

        $result = $this->db->setQuery($query)->execute();
    }

}