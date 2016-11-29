<?php
namespace Concrete\Package\ToessLabExportMembers\ImportExport;
use Concrete\Core\File\Service\Zip;
use Concrete\Core\User\User;
use Concrete\Package\ToessLabExportMembers\Helper\Tools;
use Core;
use Concrete\Core\User\Group\Group;
use \Concrete\Core\User\Point\Entry as UserPointEntry;
use Config;
use Concrete\Core\File\Importer;
use UserAttributeKey;
use Concrete\Attribute\Select\Option;

/**
 * Created by https://toesslab.ch/
 * User: Daniel Gasser
 * Date: 28.10.16
 * Time: 11:45
 * Project: c57
 * Description:
 * File: ToesslabExportToCSV.php
 */
class ExportToCSV
{
    protected $db;
    protected $replace = array(
        "ä",
        "ü",
        "ö",
        "Ä",
        "Ü",
        "Ö",
        "ß",
    );
    protected $with = array(
        "ae",
        "ue",
        "oe",
        "Ae",
        "Ue",
        "Oe",
        "ss",
    );
    protected $res = array();
    protected $csvErrors = array();
    protected $postData = array();
    protected $userIds = array();
    protected $fileName = 'toesslab';
    protected $fileNameCleaned;
    protected $csvFileName;
    protected $baseColumns = array();
    protected $columns = array();
    protected $userPoints = false;
    protected $usersGroups = false;
    protected $userInfoFactory;
    protected $csvSettings = array();
    protected $results = array();

    function __construct($args)
    {
        $app = Core::make('app');
        $this->db = Core::make('database');
        $this->userInfoFactory = $app->make('Concrete\Core\User\UserInfoFactory');
        $this->csvSettings = Config::get('toess_lab_export_members.csv-settings');
        $this->setPostData($args);
    }

    private function setPostData($args)
    {
        $this->userIds = $args['ids'];
        $this->fileName = $args['fileName'];
        $this->baseColumns = $args['baseColumns'];
        $this->columns = $args['columns'];
        $this->userPoints = ($args['userPoints'] == 'true');
        $this->usersGroups = ($args['usersGroups'] == 'true');
        if (sizeof($this->columns) == 0) {
            $this->columns = array();
        }
        // check if at least uID, uEmail and uName are present
        $basics = array(
            '0' => 'uID',
            '1' => 'uName',
            '2' => 'uEmail'
        );

        $this->baseColumns = array_replace_recursive($this->baseColumns, $basics);
    }

    public function getFilenameCleaned()
    {
        return $this->fileNameCleaned;
    }

    public function getCSVFilename()
    {
        return $this->csvFileName;
    }

    public function getUsers()
    {
        $queryIDs = implode(', ', $this->userIds);
        $query = 'select ' . implode(', ', $this->baseColumns) . ' from Users where uID in(' . $queryIDs . ') order by field(uID, ' . $queryIDs . ')';
        $r = $this->db->executeQuery($query);
        $uRes = $r->fetchAll(\PDO::FETCH_ASSOC);
        $userInfoObjects = array();
        $userAvatars = array();
        foreach ($uRes as $u) {
            foreach ($u as $k => $ua) {
                $this->results[$u['uID']][$k] = $ua;
            }
        }
       // dd($this->columns);
        foreach ($this->userIds as $id) {
            $ui = $this->userInfoFactory->getByID($id);
            $userInfoObjects[] = $ui;
            foreach ($this->columns as $c) {
                if ($ui->getAttribute($c) == NULL) {
                    $this->results[$ui->uID][$c] = 'NULL';
                } else {
                    $testAttribute = $ui->getAttribute($c);
                    if (is_array($testAttribute)) {
                        if (get_class($testAttribute[0]) == 'Concrete\Core\Tree\Node\Type\Topic') {
                            $s = array();
                            foreach ($testAttribute as $key => $ta) {
                                foreach ((array)$ta->getTreeNodeJSON() as $k => $t) {
                                    $s[$key][$k] = $t;
                                }
                            }
                            $this->results[$ui->uID][$c] = Core::make('helper/json')->encode($s);
                        } else {
                            $this->results[$ui->uID][$c] = Core::make('helper/json')->encode($testAttribute);
                        }
                    } else {
                        $this->results[$ui->uID][$c] = str_replace("\n", '\\n', $testAttribute);
                    }
                }
            }
            if ($ui->hasAvatar()){
                $avatar = $ui->getUserAvatar($ui);
                $userAvatars[$ui->uID] = $avatar->getPath();
            }
        }
        if($this->usersGroups){
            foreach ($userInfoObjects as $ui) {
                $u = $ui->getUserObject();
                $u->refreshUserGroups();
                $userGroupIDs = $u->getUserGroups();
                if ($u->getUserID() == USER_SUPER_ID) {
                    $userGroupIDs[] = Group::getByName('Administrators')->getGroupID();
                }
                $groups = $this->getUserGroups($userGroupIDs);
                $this->results[$ui->uID]['usersGroups'] = Core::make('helper/json')->encode($groups);
            }
            $this->columns[] = 'userGroups';
        }
        if($this->userPoints) {
            foreach ($this->userIds as $id) {
                $this->results[$id]['communityPoints'] = Core::make('helper/json')->encode($this->getCommunityPoints($id));
            }
            $this->columns[] = 'communityPoints';
        }
        if(sizeof($userAvatars) > 0) {
            $this->zipUserAvatars($userAvatars);
        }
        if(sizeof($this->results) == 0) {
            return false;
        }
        return true;
    }

    private function zipUserAvatars(array $uIds)
    {
        $zip = new Zip();
        $timestamp = new \DateTime();
        foreach ($uIds as $k => $a) {
            if(strpos($a, 'avatar_none') === false){
                copy(DIRNAME_APPLICATION . '/files/avatars/' . $k . '.jpg', DIRNAME_APPLICATION . '/files/toess_lab_export_members/' . $k . '.jpg');
            }
        }
        $zip->zip(DIRNAME_APPLICATION . '/files/toess_lab_export_members/', 'userAvatars_' . $timestamp->format('Y-m-d-H-i-s') . '.zip');
    }

    private function getUserGroups(array $groupIds)
    {
        $groups = array();
        foreach ($groupIds as $k => $gid) {
            $group = Group::getByID($gid);
            if (is_object($group)) {
                $groups[$gid]['gID'] = $group->getGroupID();
                $groups[$gid]['gName'] = $group->getGroupName();
                $groups[$gid]['gDescription'] = $group->getGroupDescription();
                $groups[$gid]['gUserExpirationIsEnabled'] = $group->isGroupExpirationEnabled();
                $groups[$gid]['gUserExpirationMethod'] = $group->getGroupExpirationMethod();
                $groups[$gid]['gUserExpirationSetDateTime'] = $group->getGroupExpirationDateTime();
                $groups[$gid]['gUserExpirationInterval'] = $group->getGroupExpirationInterval();
                $groups[$gid]['gUserExpirationAction'] = $group->getGroupExpirationAction();
                $groups[$gid]['gIsBadge'] = $group->isGroupBadge();
                $groups[$gid]['gBadgeFID'] = $group->getGroupBadgeImageID();
                $groups[$gid]['gBadgeDescription'] = $group->getGroupBadgeDescription();
                $groups[$gid]['gBadgeCommunityPointValue'] = $group->getGroupBadgeCommunityPointValue();
                $groups[$gid]['gIsAutomated'] = $group->isGroupAutomated();
                $groups[$gid]['gCheckAutomationOnRegister'] = $group->checkGroupAutomationOnRegister();
                $groups[$gid]['gCheckAutomationOnLogin'] = $group->checkGroupAutomationOnLogin();
                $groups[$gid]['gCheckAutomationOnJobRun'] = $group->checkGroupAutomationOnJobRun();
                $groups[$gid]['gPath'] = $group->getGroupPath();
                $groups[$gid]['children'] = $group->getChildGroups();
            }
        }
        return $groups;
    }

    private function getCommunityPoints($uID)
    {
        $uRes = $this->db->executeQuery('select upID from UserPointHistory where upuID = :uID', array('uID' => $uID));
        $cO = array();
        $i = 0;
        foreach ($uRes->fetchAll() as $r) {
            $up = new UserPointEntry();
            $up->load($r['upID']);
            $cO[$i]['userPointAction'] = (array)$up->getUserPointEntryActionObject($r['upID']);
            $cO[$i]['userPointDescription'] = (array)$up->getUserPointEntryDescriptionObject($r['upID']);
            $cO[$i]['userPointDateTime'] = (array)$up->getUserPointEntryDateTime($r['upID']);
            $i++;
        }
        return $cO;
    }

    private function saveUserAttributes($cols, $fileHandle)
    {
        ini_set('xdebug.var_display_max_depth', 5);
        ini_set('xdebug.var_display_max_children', 256);
        ini_set('xdebug.var_display_max_data', 1024);
        $uaA = array();
        if (!fputcsv($fileHandle, array(), $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
            return false;
        }
        if (!fputcsv($fileHandle, array(), $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
            return false;
        }
        if (!fputcsv($fileHandle, array('**** User Attributes. Do not delete or edit the following lines if you want to import User Attributes again ***'), $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
            return false;
        }
        $query = 'select asID from AttributeSetKeys where akID = ?';
        foreach($cols as $c) {
            $ua = UserAttributeKey::getByHandle($c);
            if(is_object($ua)) {
                $r = $this->db->executeQuery($query, array($ua->getAttributeKeyID()));
                $res = $r->FetchRow();
                if($res) {
                    $ua->setID = $res['asID'];
                } else {
                    $ua->setID = null;
                }
                dd($ua);
                $uaA[] = Core::make('helper/json')->encode(get_object_vars($ua));
            }
        }
        foreach ($uaA as $a){
            if (!fputcsv($fileHandle, array($a), $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
                return false;
            }
        }
    }

    public function createUserExportCSVFile()
    {
        $this->fileNameCleaned = str_replace($this->replace, $this->with, $this->fileName);
        $this->csvFileName = DIRNAME_APPLICATION . '/files/incoming/' . $this->fileNameCleaned . '.csv';
        return Tools::createFilePointer($this->csvFileName, 'wb');
    }

    public function writeToCSVFile()
    {
        $fileHandle = Tools::createFilePointer($this->csvFileName, 'wb');
        $cols = array_merge($this->baseColumns, $this->columns);
        if (!$fileHandle) {
            return false;
        }
        if (!fputcsv($fileHandle, $cols, $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
            return false;
        }
        $csvCount = 0;
        foreach ($this->results as $r) {
            if (!fputcsv($fileHandle, $r, $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
                $this->csvErrors[] = t('Record %s could not be saved.', $r['uName']);
            } else {
                $csvCount++;
            }
        }
        if (sizeof($this->csvErrors) > 0) {
            return $this->csvErrors;
        }
        $this->saveUserAttributes($this->columns, $fileHandle);
        fclose($fileHandle);
        return $csvCount;
    }

    public function createCSVFileObject()
    {
        $fi = new Importer();
        return $fi->import($this->csvFileName, $this->fileName . '.csv');
    }
}