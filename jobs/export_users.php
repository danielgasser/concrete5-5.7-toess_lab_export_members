<?php
namespace Concrete\Package\ToessLabExportMembers\Job;

use Concrete\Package\ToessLabExportMembers\Controller\SinglePage\Dashboard\Users\ToessLabExportMembers;
use Concrete\Package\ToessLabExportMembers\Helper\Tools;
use Concrete\Package\ToessLabExportMembers\ImportExport\ExportToCSV;
use Loader;
use Punic\Exception;
use QueueableJob;
use Group;
use User;
use Core;
use ZendQueue\Queue as ZendQueue;
use ZendQueue\Message as ZendQueueMessage;

class ExportUsers extends QueueableJob
{
    const CLEAR = "-1";
    public $jSupportsQueue = true;
    public $export;
    public $result;
    public $args;

    function __construct(ExportToCSV $export)
    {
        $this->export = $export;
    }

    public function getJobName()
    {
        return t("Export Users");
    }

    public function getJobDescription()
    {
        return t("Export users chosen at toesslab - Export Members.");
    }

    public function start(ZendQueue $q)
    {
        foreach ($this->args['ids'] as $id) {
            $q->send($id);
        }
    }

    public function setExport($args)
    {
        $this->args = $args;
    }

    public function finish(ZendQueue $q)
    {
        $q->deleteQueue();
        return  count($this->result);
    }

    public function processQueueItem(ZendQueueMessage $msg)
    {
        $this->result[] = $this->export->getUsers($msg->body, $this->args);
        Tools::setProgress('Exporting users', count($this->result), count($this->args['ids']));
    }
}
