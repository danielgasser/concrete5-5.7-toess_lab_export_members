<?php
namespace Concrete\Package\ToessLabExportMembers\Controller\SinglePage\Dashboard\Users;

use Concrete\Package\ToessLabExportMembers\Helper\Tools;
use Concrete\Package\ToessLabExportMembers\ImportExport\ExportToCSV;
use Core;
use Config;
use Request;
use \Concrete\Core\Page\Controller\DashboardPageController;
use Symfony\Component\HttpFoundation\Response;

class ToessLabExportMembers extends DashboardPageController
{

    public $export;
    public $write;

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

    public function export_to_csv()
    {
        Tools::setProgress('Preparing export', 0, 0);
        $postArgs['ids'] = Core::make('helper/json')->decode($this->post('h_uIds'));
        $postArgs['fileName'] = $this->post('h_csv_filename');
        $postArgs['baseColumns'] = Core::make('helper/json')->decode($this->post('h_uBaseCols'));
        $postArgs['columns'] = Core::make('helper/json')->decode($this->post('h_uCols'));
        $postArgs['userPoints'] = $this->post('h_communityPoints');
        $postArgs['usersGroups'] = $this->post('h_usersGroups');
        if (strlen($postArgs['fileName']) == 0) {
            return Response::create(Core::make('helper/json')->encode(array('error' => t('Please enter a CSV-Filename.'))));
        }
        if (sizeof($postArgs['ids']) == 0) {
            return Response::create(Core::make('helper/json')->encode(array('error' => t('Please select some Users to export.'))));
        }
        if (sizeof($postArgs['baseColumns']) == 0) {
            return Response::create(Core::make('helper/json')->encode(array('error' => t('You have to select at least one Basic User Attribute.'))));
        }
        $write = $this->export->setPostData($postArgs);
        $this->export->createFileObject();
        if(is_array($write['result'])) {
            return Response::create(Core::make('helper/json')->encode(array('error' => $write['result'])));
        } else {
            $message = t('Exporting User Avatars');
            return Response::create(Core::make('helper/json')->encode(array('success' => $message, 'zipFileName' => $write['csvFileName'], 'results' => $write)));
        }
    }

    public function zip_user_avatars()
    {
        $fileName = $this->get('fileName');
        $results = $this->get('results');
        $this->export->zipUserAvatars($fileName);
        $msg = t('%s record(s) have been exported successfully. The files <ul><li>\'%s\'</li><li>\'%s\'</li></ul>have been added to your <a href="%s">File Manager</a>', $results['result'], $results['csvFileName'] . '.csv', $fileName . '.zip', \URL::to('/dashboard/files/search'));
        return Response::create(Core::make('helper/json')->encode(array('success' => $msg)));
    }

    public function on_start()
    {
        $this->requireAsset('toess_lab_export_members');
        $this->requireAsset('bootstrapswitch');
        $this->requireAsset('jquery-ui');
        $this->export = new ExportToCSV();
        parent::on_start();
    }
}
