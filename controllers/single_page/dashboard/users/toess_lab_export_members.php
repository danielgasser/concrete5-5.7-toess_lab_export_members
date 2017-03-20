<?php
namespace Concrete\Package\ToessLabExportMembers\Controller\SinglePage\Dashboard\Users;

use Concrete\Core\Url\Url;
use Concrete\Package\ToessLabExportMembers\Helper\Tools;
use Concrete\Package\ToessLabExportMembers\ImportExport\ExportToCSV;
use Core;
use Config;
use Request;
use \Concrete\Core\Page\Controller\DashboardPageController;
use Symfony\Component\HttpFoundation\Response;

class ToessLabExportMembers extends DashboardPageController
{

    public function view()
    {
        $this->set('possibleGroups', Tools::setPossibleUserGroup());
        $this->set('columns', Tools::getUserAttributeColumns());
        $this->set('csvSettingsJSON', json_encode(Config::get('toess_lab_export_members.csv-settings')));
        $this->set('csvSettings', Config::get('toess_lab_export_members.csv-settings'));
        $this->set('csvExportSettings', Config::get('toess_lab_export_members.export-settings'));
    }

    public function change_user_group()
    {
        $group_id = Request::getInstance()->get('group_id');
        $adminInc = (Request::getInstance()->get('adminInc') == 'true');
        $was_checked = (Request::getInstance()->get('was_checked') == NULL) ? array() : Request::getInstance()->get('was_checked');
        if (sizeof($group_id) == 0) {
            return Response::create(Core::make('helper/json')->encode(NULL));
        }
        $allUsers = Tools::changeUserGroup($group_id, $adminInc, $was_checked);
        return Response::create(Core::make('helper/json')->encode(array('res' => $allUsers, 'res_count' => count($allUsers))));
    }

    /**
     *
     */
    public function save_export_settings()
    {
        $exportSettings = Request::getInstance()->get('exportSettings');
        Config::clear('toess_lab_export_members.export-settings');
        foreach ($exportSettings as $e) {
            Config::save('toess_lab_export_members.export-settings.' . $e['handle'], ($e['val'] == 'true'));
        }
        return Response::create(Core::make('helper/json')->encode(array('success' => t('Export Settings have been saved.'))));
    }

    /**
     *
     */
    public function save_csv_settings()
    {
        $th = Core::make('helper/text');
        $csvSettings = Request::getInstance()->get('csvData');
        foreach ($csvSettings as $c) {
            if ($c['handle'] == 'csv_filename') {
                if (strlen($c['val']) == 0) {
                    return Response::create(Core::make('helper/json')->encode(array('error' => t('Please enter a valid CSV-Filename.'))));
                } else {

                    $s = strpos($c['val'], '.');
                    if ($s !== false) {
                        $c['val'] = substr($c['val'], 0, $s);
                    }
                    $c['val'] = $th->alphanum($c['val']);
                }
            }
            if ($c['handle'] != 'csv_filename' && strlen($c['val']) > 1 || strlen($c['val']) < 1) {
                return Response::create(Core::make('helper/json')->encode(array('error' => t('%s: Please enter 1 character only', $c['name']))));
            }
            Config::save('toess_lab_export_members.csv-settings.' . $c['handle'], $c['val']);
        }
        return Response::create(Core::make('helper/json')->encode(Config::get('toess_lab_export_members.csv-settings')));
    }

    /**
     *
     */
    public function search_users()
    {
        $keyword = Request::getInstance()->get('keyWord');
        $db = Core::make('database');
        $query = 'select uID, uName, uEmail, uDateAdded, uNumLogins from Users where uName like concat("%", :param, "%") or uEmail like concat("%", :param, "%")';
        $r = $db->executeQuery($query, array('param' => $keyword));
        $res = $r->fetchAll(\PDO::FETCH_OBJ);
        return Response::create(Core::make('helper/json')->encode(array('res' => $res, 'res_count' => count($res))));
    }

    public function get_progress_queue()
    {
        $session = @fopen(DIRNAME_APPLICATION . '/files/incoming/' . 'queue.txt', 'r');
        if ($session === false)
            return Response::create(Core::make('helper/json')->encode(null));
        $progress = fgets($session);
        return Response::create(Core::make('helper/json')->encode($progress));
    }

    public function export_to_csv()
    {
        $postArgs['ids'] = Core::make('helper/json')->decode($this->post('h_uIds'));
        $postArgs['fileName'] = $this->post('h_csv_filename');
        $postArgs['baseColumns'] = Core::make('helper/json')->decode($this->post('h_uBaseCols'));
        $postArgs['columns'] = Core::make('helper/json')->decode($this->post('h_uCols'));
        $postArgs['userPoints'] = $this->post('h_communityPoints');
        $postArgs['usersGroups'] = $this->post('h_usersGroups');
        $this->set('uIds', $postArgs['ids']);
        $this->set('csv_filename', $postArgs['fileName']);
        $this->set('uBaseCols', $postArgs['baseColumns']);
        $this->set('uCols', $postArgs['columns']);
        $this->set('communityPoints', $postArgs['userPoints']);
        $this->set('usersGroups', $postArgs['usersGroups']);
        $this->set('updateBar', '');
        if (strlen($postArgs['fileName']) == 0) {
            $this->error->add(t('Please enter a CSV-Filename.'));
            //return Response::create(Core::make('helper/json')->encode(array('error' => t('Please enter a CSV-Filename.'))));
        }
        if (sizeof($postArgs['ids']) == 0) {
            $this->error->add(t('Please select some Users to export.'));
            //return Response::create(Core::make('helper/json')->encode(array('error' => t('Please select some Users to export.'))));
        }
        if (sizeof($postArgs['baseColumns']) == 0) {
            $this->error->add(t('You have to select at least one Basic User Attribute.'));
            //return Response::create(Core::make('helper/json')->encode(array('error' => t('You have to select at least one Basic User Attribute.'))));
        }
        if ($this->error->has()) {
            $this->view();
            return false;
        }
        $export = new ExportToCSV($postArgs);
        if(!$export->queueUserIds()){
            return Response::create(Core::make('helper/json')->encode(array('error' => t('No Users found.'))));
        }
        if(!$export->createUserExportCSVFile()) {
            return Response::create(Core::make('helper/json')->encode(array('error' => t('File \'%s\' could not be created.', $export->getCSVFilename()))));
        }
        $write = $export->writeToCSVFile();
        $export->createFileObject();
        if(is_array($write)) {
            return Response::create(Core::make('helper/json')->encode(array('error' => $write)));
        } else {
            return Response::create(Core::make('helper/json')->encode(array('success' => t('%s record(s) have been saved. The files \'%s\' and \'%s\' have been added to your <a href="%s">File Manager</a>', $write, $export->getFilenameCleaned() . '.csv', $export->getZipFilename(), \URL::to('/dashboard/files/search')))));
        }
    }

    public function on_start()
    {
        $this->requireAsset('toess_lab_export_members');
        $this->requireAsset('bootstrapswitch');
        $this->requireAsset('jquery-ui');
        $this->requireAsset('zend_progress');
        parent::on_start();
    }
}
