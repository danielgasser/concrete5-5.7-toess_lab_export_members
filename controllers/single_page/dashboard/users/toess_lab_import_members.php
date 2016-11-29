<?php
namespace Concrete\Package\ToessLabExportMembers\Controller\SinglePage\Dashboard\Users;
use Concrete\Core\File\File;
use Concrete\Core\File\Importer;
use Concrete\Core\User\Group\Group;
use Concrete\Package\ToessLabExportMembers\ImportExport\ImportFromCSV;
use UserAttributeKey;
use Core;
use Config;
use Request;
use \Concrete\Core\User\Point\Entry as UserPointEntry;
use \Concrete\Core\Page\Controller\DashboardPageController;

class ToessLabImportMembers extends DashboardPageController
{

    public function view()
    {
        $this->set('test', 'test');
    }

    public function get_csv_file()
    {
        $csvFileId = \Core::make('helper/security')->sanitizeInt(intval($this->post('fID')));
        $csvFile = File::getByID($csvFileId);
        if(is_object($csvFile)) {
            $fv = $csvFile->getApprovedVersion();
            $import = new ImportFromCSV($csvFile);
            $import->checkExistentAttributes();
            $import->checkExistentUsers();
        }

    }

    public function on_start()
    {
        $this->requireAsset('toess_lab_import_members');
        $this->requireAsset('bootstrapswitch');
        parent::on_start();
    }

}