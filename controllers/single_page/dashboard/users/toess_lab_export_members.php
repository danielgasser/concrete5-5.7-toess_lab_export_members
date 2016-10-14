<?php

namespace Concrete\Package\ToessLabExportMembers\Controller\SinglePage\Dashboard\Users;

use Concrete\Core\User\Group\Group;
use UserAttributeKey;
use Loader;
use \Concrete\Core\User\Point\Entry as UserPointEntry;
use \Concrete\Core\Page\Controller\DashboardPageController;

class ToessLabExportMembers extends DashboardPageController
{

    public function view()
    {
        $this->set('possibleGroups', self::setPossibleUserGroup());
        $this->set('columns', $this->getUserAttributeColumns());
        $this->set('csvSettingsJSON', json_encode(\Config::get('toess_lab_export_members.csv-settings')));
        $this->set('csvSettings', \Config::get('toess_lab_export_members.csv-settings'));
    }

    /**
     *
     * Sets user groups allowed to use
     *
     * @param array $key Not like $key
     * @return array
     *
     */
    private function setPossibleUserGroup($key = array('Guest'))
    {
        $db = \Core::make('database');
        $arr = array();
        $key_str = implode('" and gName not like "', $key);
        $query = 'select * from  Groups where gName not like "' . $key_str . '"';
        $res = $db->execute($query);
        while ($row = $res->fetchRow()) {
            $arr[$row['gID']] = $row['gName'];
        }
        return $arr;
    }

    /**
     *
     * Changes the group by AJAX
     * and echoes it as Json if $json is true
     *
     * @param bool $json
     * @return array
     *
     */
    public function change_user_group($json = true)
    {
        $db = \Core::make('database');
        $superuser = array();
        $users = array();
        $allUsers = array();
        $group_id = \Request::getInstance()->get('group_id');
        $adminInc = \Request::getInstance()->get('adminInc');
        $was_checked = (\Request::getInstance()->get('was_checked') == NULL) ? array() : \Request::getInstance()->get('was_checked');
        if (sizeof($group_id) == 0) {
            if ($json) {
                echo \Core::make('helper/json')->encode(NULL);
                exit;
            }
            return NULL;
        }
        foreach($group_id as $gi) {
            $userGroup = Group::getByID($gi);
            $users = $userGroup->getGroupMembers();
            if ($gi == '2') {
                $res = $db->execute('select uID, uName, uEmail,uDateAdded, uNumLogins from Users');
                while ($row = $res->fetch(\PDO::FETCH_OBJ)) {
                    $row->isChecked = in_array($row->uID, $was_checked);;
                    $users[] = $row;
                }
            }
        }
        if ($adminInc == 'true') {
            $res = $db->execute('select uID, uName, uEmail, uDateAdded, uNumLogins from Users where uID = 1');
            while ($row = $res->fetch(\PDO::FETCH_OBJ)) {
                $row->isChecked = in_array($row->uID, $was_checked);
                $superuser = $row;
            }
            $users[] = $superuser;
        }
        $i = 0;
        foreach($users as $u) {
            $allUsers[$i]['isChecked'] = in_array($u->uID, $was_checked);
            $allUsers[$i]['uID'] = $u->uID;
            $allUsers[$i]['uName'] = $u->uName;
            $allUsers[$i]['uEmail'] = $u->uEmail;
            $allUsers[$i]['uDateAdded'] = $u->uDateAdded;
            $allUsers[$i]['uNumLogins'] = $u->uNumLogins;
            $i++;
        }
        if ($json) {
            echo \Core::make('helper/json')->encode(array('res' => $allUsers, 'res_count' => count($allUsers)));
            exit;
        }
        return $users;
    }

    public function save_csv_settings()
    {
        $csvSettings = \Request::getInstance()->get('csvData');
        foreach ($csvSettings as $c) {
            if ($c['handle'] == 'csv-filename' && strlen($c['val']) == 0) {
                echo \Core::make('helper/json')->encode(array('error' => t('Please enter a CSV-Filename.')));
                exit;
            }
            if ($c['handle'] != 'csv-filename' && strlen($c['val']) > 1 || strlen($c['val']) < 1) {
                echo \Core::make('helper/json')->encode(array('error' => t('%s: Please enter 1 character only', $c['name'])));
                exit;
            }
            \Config::save('toess_lab_export_members.csv-settings.' . $c['handle'], $c['val']);
        }
        echo \Core::make('helper/json')->encode(array('success' => t('CSV-Settings have been saved')));
        exit;
    }

    public function search_users()
    {
        $keyword = \Request::getInstance()->get('keyWord');
        $db = \Core::make('database');
        $stmnt = $db->prepare('select uID, uName, uEmail, uDateAdded, uNumLogins from Users where uName like concat("%", :param, "%") or uEmail like concat("%", :param, "%")');
        $stmnt->bindParam(':param', $keyword);
        $stmnt->execute();
        $res = $stmnt->fetchAll(\PDO::FETCH_OBJ);
        echo \Core::make('helper/json')->encode(array('res' => $res, 'res_count' => count($res)));
        exit;
    }

    private function getUserAttributeColumns()
    {
        $attr = UserAttributeKey::getList();
        $attributes = array();
        $baseAttributes = array();
        $i = 0;
        foreach ($attr as $k => $a){
            $attributes[$i]['akHandle'] = $a->akHandle;
            $attributes[$i]['akName'] = $a->akName;
            $i++;
        }
        $baseAttributes[] = array(
            'akHandle' => 'uID',
            'akName' => t('User ID')
        );

        $baseAttributes[] = array(
            'akHandle' => 'uName',
            'akName' => t('Username')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uEmail',
            'akName' => t('Email Address')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uPassword',
            'akName' => t('Password')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uIsActive',
            'akName' => t('User active')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uIsValidated',
            'akName' => t('User validated')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uIsFullRecord',
            'akName' => t('Full Record')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uDateAdded',
            'akName' => t('Date created')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastPasswordChange',
            'akName' => t('Last Password Change')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uHasAvatar',
            'akName' => t('Profile Picture')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastOnline',
            'akName' => t('Last Online')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastLogin',
            'akName' => t('Last Login')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastIP',
            'akName' => t('Last IP Address')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uPreviousLogin',
            'akName' => t('Previous Login')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uNumLogins',
            'akName' => t('N° Logins')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastAuthTypeID',
            'akName' => t('Last Authorization Type')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uTimezone',
            'akName' => t('Timezone')
        );
        $baseAttributes[] = array(
            'akHandle' =>  'uDefaultLanguage',
            'akName' => t('Language')
        );
        return array('attributes' => $attributes, 'baseAttributes' => $baseAttributes);
    }

    public function export_to_csv()
    {
        $app = \Core::make('app');
        $replace = array(
            "ä",
            "ü",
            "ö",
            "Ä",
            "Ü",
            "Ö",
            "ß",
        );
        $with = array(
            "ae",
            "ue",
            "oe",
            "Ae",
            "Ue",
            "Oe",
            "ss",
        );
        $ids = \Request::getInstance()->get('ids');
        $fileName = \Request::getInstance()->get('csv_filename');
        if (strlen($fileName) == 0) {
            echo \Core::make('helper/json')->encode(array('error' => t('Please enter a CSV-Filename.')));
            exit;
        }
        $fileName = str_replace($replace, $with, $fileName);
        $fileName = preg_replace('/[^a-zA-Z]/i','',$fileName);
        $baseColumns = \Request::getInstance()->get('baseColumns');
        $columns = \Request::getInstance()->get('columns');
        $userPoints = \Request::getInstance()->get('communityPoints');
        $usersGroups = \Request::getInstance()->get('usersGroups');
        $userInfoFactory = $app->make('Concrete\Core\User\UserInfoFactory');
        $csvSettings = \Config::get('toess_lab_export_members.csv-settings');
        if(sizeof($ids) == 0) {
            echo \Core::make('helper/json')->encode(array('error' => t('Please select some Users to export.')));
            exit;
        }
        if(sizeof($baseColumns) == 0) {
            echo \Core::make('helper/json')->encode(array('error' => t('You have to select at least one Basic User Attribute.')));
            exit;
        }
        if(sizeof($columns) == 0){
            $columns = array();
        }
        $res = array();
        $db = \Core::make('database');
        $queryIDs = implode(', ', $ids);
        $stmnt = $db->prepare('select ' . implode(', ', $baseColumns) . ' from Users where uID in(' . $queryIDs . ') order by field(uID, ' . $queryIDs . ')');
        $stmnt->execute();
        $uRes = $stmnt->fetchAll(\PDO::FETCH_ASSOC);
        $csvErrors = array();
        foreach ($uRes as $u){
            foreach ($u as $k => $ua){
                $res[$u['uID']][$k] = $ua;
            }
        }
        foreach ($ids as $id) {
            $ui = $userInfoFactory->getByID($id);
            foreach ($columns as $c){
                if (is_object($ui->getAttribute($c))) {
                    $s = array();
                    foreach($ui->getAttribute($c)->getOptions() as $opt){
                        $s[] = $opt->value;
                    }
                    $res[$ui->uID][$c] = serialize($s);
                } else {
                    $res[$ui->uID][$c] = $ui->getAttribute($c);
                }
            }

            if($usersGroups == 'true') {
                $u = $ui->getUserObject();
                $ugids = array_keys($u->getUserGroups());
                foreach($ugids as $gid) {
                    $group = Group::getByID($gid);
                    if (is_object($group)) {
                        $groups[$gid]['gID'] = $group->getGroupID();
                        $groups[$gid]['gName'] = $group->getGroupName();
                        $groups[$gid]['gDescription'] = $group->getGroupDescription();
                        $groups[$gid]['gPath'] = $group->getGroupPath();
                        $groups[$gid]['gChildGroups'] = $group->getChildGroups();


                        $res[$ui->uID]['usersGroups'] = serialize($groups);
                    }
                }
            }

        }
        if($usersGroups == 'true') {
            $columns[] = 'usersGroups';
        }
        if($userPoints == 'true') {
            foreach ($ids as $id) {
                $res[$id]['communityPoints'] = serialize($this->getCommunityPoints($id));
            }
            $columns[] = 'communityPoints';
        }
        $csvFileName = DIRNAME_APPLICATION . '/files/toess_lab_export_members/' . $fileName . '.csv';
        if(!is_writable($csvFileName) && file_exists($csvFileName)) {
            echo \Core::make('helper/json')->encode(array('error' => t('File \'%s\' is not writable.', $csvFileName)));
            exit;
        }
        $csvDirName = dirname($csvFileName);
        if (!is_dir($csvDirName)) {
            if(!mkdir($csvDirName)) {
                echo \Core::make('helper/json')->encode(array('error' => t('File \'%s\' could not be created.', $csvFileName)));
                exit;
            }
        }
        $cols = array_merge($baseColumns, $columns);
        $csvFile = fopen($csvFileName, 'wb');
        if(!$csvFile) {
            echo \Core::make('helper/json')->encode(array('error' => t('Columns could not be saved.')));
            exit;
        }
        if(!fputcsv($csvFile, $cols, $csvSettings['csv-delimiter'], $csvSettings['csv-enclosure'], $csvSettings['csv-escape'])) {
            echo \Core::make('helper/json')->encode(array('error' => t('Columns could not be saved.')));
            exit;
        }
        $csvCount = 0;
        foreach ($res as $r) {
            if(!fputcsv($csvFile, $r, $csvSettings['csv-delimiter'], $csvSettings['csv-enclosure'], $csvSettings['csv-escape'])){
                $csvErrors[] = t('Record %s could not be saved.', $r['uName']);
            } else {
                $csvCount++;
            }
        }
        if(sizeof($csvErrors) > 0) {
            echo \Core::make('helper/json')->encode(array('error' => $csvErrors));
            exit;
        }
        echo \Core::make('helper/json')->encode(array('success' => t('%s record(s) have been saved. Download <a href="%s">%s</a> file', $csvCount, BASE_URL . '/' . $csvFileName, $csvFileName)));
        exit;
    }

    private function getCommunityPoints($uID)
    {
        $db = \Core::make('database');
        $uRes = $db->execute('select upID from UserPointHistory where upuID = ' . $uID);
        $cO = array();
        $i = 0;
        foreach ($uRes->fetchAll() as $r){
            $up = new UserPointEntry();
            $up->load($r['upID']);
            $cO[$i]['userPointAction'] = (array)$up->getUserPointEntryActionObject($r['upID']);
            $cO[$i]['userPointDescription'] = (array)$up->getUserPointEntryDescriptionObject($r['upID']);
            $cO[$i]['userPointDateTime'] = (array)$up->getUserPointEntryDateTime($r['upID']);
            $i++;
        }
        return $cO;
    }

    public function on_start()
    {
        $this->requireAsset('toess_lab_export_members');
        $this->requireAsset('bootstrapswitch');
        parent::on_start();
    }

}