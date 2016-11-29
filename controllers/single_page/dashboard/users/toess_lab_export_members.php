<?php
namespace Concrete\Package\ToessLabExportMembers\Controller\SinglePage\Dashboard\Users;

use Concrete\Package\ToessLabExportMembers\Helper\Tools;
use Concrete\Package\ToessLabExportMembers\ImportExport\ExportToCSV;
use Core;
use Config;
use Request;
use \Concrete\Core\Page\Controller\DashboardPageController;

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
            echo Core::make('helper/json')->encode(NULL);
            exit;
        }
        $allUsers = Tools::changeUserGroup($group_id, $adminInc, $was_checked);
        echo Core::make('helper/json')->encode(array('res' => $allUsers, 'res_count' => count($allUsers)));
        exit;
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
        echo Core::make('helper/json')->encode(array('success' => t('Export Settings have been saved.')));
        exit;
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
                    echo Core::make('helper/json')->encode(array('error' => t('Please enter a valid CSV-Filename.')));
                    exit;
                } else {

                    $s = strpos($c['val'], '.');
                    if ($s !== false) {
                        $c['val'] = substr($c['val'], 0, $s);
                    }
                    $c['val'] = $th->alphanum($c['val']);
                }
            }
            if ($c['handle'] != 'csv_filename' && strlen($c['val']) > 1 || strlen($c['val']) < 1) {
                echo Core::make('helper/json')->encode(array('error' => t('%s: Please enter 1 character only', $c['name'])));
                exit;
            }
            Config::save('toess_lab_export_members.csv-settings.' . $c['handle'], $c['val']);
        }
        echo Core::make('helper/json')->encode(Config::get('toess_lab_export_members.csv-settings'));
        exit;
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
        echo Core::make('helper/json')->encode(array('res' => $res, 'res_count' => count($res)));
        exit;
    }

    public function export_to_csv()
    {
        $postArgs['ids'] = Request::getInstance()->get('ids');
        $postArgs['fileName'] = Request::getInstance()->get('csv_filename');
        $postArgs['baseColumns'] = Request::getInstance()->get('baseColumns');
        $postArgs['columns'] = Request::getInstance()->get('columns');
        $postArgs['userPoints'] = Request::getInstance()->get('communityPoints');
        $postArgs['usersGroups'] = Request::getInstance()->get('usersGroups');
        if (strlen($postArgs['fileName']) == 0) {
            echo Core::make('helper/json')->encode(array('error' => t('Please enter a CSV-Filename.')));
            exit;
        }
        if (sizeof($postArgs['ids']) == 0) {
            echo Core::make('helper/json')->encode(array('error' => t('Please select some Users to export.')));
            exit;
        }
        if (sizeof($postArgs['baseColumns']) == 0) {
            echo Core::make('helper/json')->encode(array('error' => t('You have to select at least one Basic User Attribute.')));
            exit;
        }

        $export = new ExportToCSV($postArgs);
        if(!$export->getUsers()){
            echo Core::make('helper/json')->encode(array('error' => t('No Users found.')));
            exit;
        }
        if(!$export->createUserExportCSVFile()) {
            echo Core::make('helper/json')->encode(array('error' => t('File \'%s\' could not be created.', $export->getCSVFilename())));
            exit;
        }
        $write = $export->writeToCSVFile();
        $export->createCSVFileObject();
        if(is_array($write)) {
            echo Core::make('helper/json')->encode(array('error' => $write));
            exit;
        } else {
            echo Core::make('helper/json')->encode(array('success' => t('%s record(s) have been saved. The file \'%s\' has been added to your <a href="%s">File Manager</a>', $write, $export->getFilenameCleaned() . '.csv', \URL::to('/dashboard/files/search'))));
            exit;
        }
    }

    public function on_start()
    {
        $this->requireAsset('toess_lab_export_members');
        $this->requireAsset('bootstrapswitch');
        $this->requireAsset('jquery-ui');
        parent::on_start();
    }

}