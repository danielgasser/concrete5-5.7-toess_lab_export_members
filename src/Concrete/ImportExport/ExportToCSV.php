<?php
namespace Concrete\Package\ToessLabExportMembers\ImportExport;
use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Attribute\Key\UserKey;
use Concrete\Core\Entity\Attribute\Key\ExpressKey;
use Concrete\Core\Entity\Attribute\Value\Value\ExpressValue;
use Concrete\Core\Entity\Attribute\Value\Value\SelectedSocialLink;
use Concrete\Core\Entity\Attribute\Value\Value\SocialLinksValue;
use Concrete\Core\Entity\Express\Entity;
use Concrete\Core\Entity\Express\Entry;
use Concrete\Core\Express\EntryList;
use Concrete\Core\File\Service\Zip;
use Concrete\Core\Logging\Logger;
use Concrete\Core\Tree\Node\Type\Topic;
use Concrete\Package\ToessLabExportMembers\Helper\Tools;
use Core;
use Concrete\Core\User\Group\Group;
use \Concrete\Core\User\Point\Entry as UserPointEntry;
use Config;
use Concrete\Core\File\Importer;
use UserAttributeKey;
use Express;
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
    protected $zipName;
    protected $csvFileName;
    protected $baseColumns = array();
    protected $columns = array();
    protected $userPoints = false;
    protected $usersGroups = false;
    protected $userInfoFactory;
    protected $csvSettings = array();
    protected $results = array();
    protected $timeStamp;
    protected $entityManager;

    function __construct($args)
    {
        // ToDo hint for DB, app change when needed

        $app = Core::make('app');
        $this->db = Core::make('database');
        $this->userInfoFactory = $app->make('Concrete\Core\User\UserInfo');
        $this->csvSettings = Config::get('toess_lab_export_members.csv-settings');
        $this->setPostData($args);
        $this->entityManager = Core::make('Doctrine\ORM\EntityManager');
    }

    private function setPostData($args)
    {
        $this->timeStamp = new \DateTime();
        $this->timeStamp = $this->timeStamp->format('Y-m-d-H-i-s');
        $this->userIds = $args['ids'];
        $this->fileName = $this->timeStamp . '_' . $args['fileName'];
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
        return str_replace($this->replace, $this->with, $this->fileName);
    }

    public function getCSVFilename()
    {
        return $this->csvFileName;
    }

    public function getFilename()
    {
        return $this->fileName;
    }

    public function getZipFilename()
    {
        return $this->zipName;
    }

    public function getUsers()
    {
        $queryIDs = implode(', ', $this->userIds);
        $query = 'select ' . implode(', ', $this->baseColumns) . ' from Users where uID in(' . $queryIDs . ') order by field(uID, ' . $queryIDs . ')';
        $r = $this->db->executeQuery($query);
        $uRes = $r->fetchAll(\PDO::FETCH_ASSOC);
        $userInfoObjects = array();
        foreach ($uRes as $u) {
            foreach ($u as $k => $ua) {
                $this->results[$u['uID']][$k] = $ua;
            }
        }
        foreach ($this->userIds as $id) {
            $ui = $this->userInfoFactory->getByID($id);
            $userInfoObjects[] = $ui;
            //Zend Queue
            foreach ($this->columns as $c) {
                if ($ui->getAttribute($c) == NULL) {
                    $this->results[$id][$c] = 'NULL';
                } else {
                    $uiAttribute = $ui->getAttribute($c);
                    if (is_array($uiAttribute)) {
                        if ($uiAttribute[0] instanceof Topic) {
                            $s = array();
                            foreach ($uiAttribute as $key => $ta) {
                                foreach ((array)$ta->getTreeNodeJSON() as $k => $t) {
                                    $s[$key][$k] = $t;
                                }
                            }
                            $this->results[$id][$c] = Core::make('helper/json')->encode($s);
                        } else {
                            $this->results[$id][$c] = Core::make('helper/json')->encode($uiAttribute);
                        }
                    } elseif($uiAttribute instanceof SocialLinksValue) {
                        $s = array();
                        foreach($uiAttribute->getSelectedLinks()->getValues() as  $key => $value) {
                            $s[$key] = $this->getSocialLinksValues($value);
                        }
                        $this->results[$id][$c] = Core::make('helper/json')->encode($s);
                    } elseif ($uiAttribute instanceof ExpressValue) {
                        $a = array();
                            $a[] = $this->getExpressObject($uiAttribute);
                            /*
                            foreach($entry->getEntity()->getAttributeKeyCategory()->getList() as $e) {
                                if ($e->getAttributeType()->getAttributeTypeHandle() == 'express') {
                                    $a[$e->getAttributeKeyHandle()] = $this->getExpressEntityEntry($e);
                                } else {
                                    $a[$e->getAttributeKeyHandle()] = $entry->getAttribute($e->getAttributeKeyHandle());
                                }
                            }
                            */
                        //}
                        $this->results[$id][$c] = Core::make('helper/json')->encode($a);
                    } elseif ($uiAttribute instanceof \DateTime) {
                        $this->results[$id][$c] = str_replace("\n", '\\n', $uiAttribute->format('Y-m-d h:s:i'));
                    } else {
                        $this->results[$id][$c] = str_replace("\n", '\\n', $uiAttribute);
                    }
                    //$r = $this->entityManager->getRepository('\Concrete\Core\Entity\Express\Entity');
                  //  dd($r->findPublicEntities()[1]);
                   /*
                   } elseif(get_class($testAttribute) == 'Concrete\Core\Entity\Attribute\Value\Value\SocialLinksValue') {
                        $this->results[$id][$c] = Core::make('helper/json')->encode(t('Social Links can\'t be exported in this version.'));
                    } elseif(get_class($testAttribute) == 'Concrete\Core\Entity\Attribute\Value\Value\ExpressValue') {
                        $this->results[$id][$c] = Core::make('helper/json')->encode(t('Express objects can\'t be exported in this version.'));
                    */
                }
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
                $this->results[$u->uID]['usersGroups'] = Core::make('helper/json')->encode($groups);
            }
            $this->columns[] = 'userGroups';
        }
        if($this->userPoints) {
            foreach ($this->userIds as $id) {
                $this->results[$id]['communityPoints'] = Core::make('helper/json')->encode($this->getCommunityPoints($id));
            }
            $this->columns[] = 'communityPoints';
        }
        $this->zipUserAvatars();
        if(sizeof($this->results) == 0) {
            return false;
        }
        return true;
    }

    private function getExpressObject($attr)
    {
        $data = array();
        foreach ($attr->getSelectedEntries() as $expressValueEntry) {
            foreach($expressValueEntry->getEntity()->getAttributeKeyCategory()->getList() as $entry) {
                if ($entry->getAttributeTypeHandle() == 'express') {
                    $data = $this->getExpressValue($entry);
               } else {
                    $data[$entry->getAttributeKeyFuckHandle()] = $expressValueEntry->getAttribute($entry->getAttributeKeyHandle());
               }
            }
        }
        return $data;
    }

    private function getExpressValue(ExpressKey $entry)
    {
        $data = array();
        $list = new EntryList($entry->getEntity());
        $aa = $list->getResults();
        foreach($aa as $subSubEntry) {
            foreach($entry->getEntity()->getAttributeKeyCategory()->getList() as $Eentry){
                $data[$entry->getAttributeKeyHandle()][$subSubEntry->getAttributeKeyHandle()][$Eentry->getAttributeKeyHandle()] = $subSubEntry->getAttribute($Eentry->getAttributeKeyHandle());
            }
        }
        return $data;
    }

    private function getSocialLinksValues(SelectedSocialLink $attr)
    {
        $s = array();
        $s['service'] = $attr->getService();
        $s['serviceInfo'] = $attr->getServiceInfo();
        return $s;
    }

    private function zipUserAvatars()
    {
        $zip = new Zip();
        $dirIncoming = DIRNAME_APPLICATION . '/files/incoming/';
        $dirAvatars = DIRNAME_APPLICATION . '/files/avatars/';
        $this->zipName = $this->getFilenameCleaned() . '_userAvatars.zip';
        if (!file_exists($dirIncoming) && !is_dir($dirIncoming)) {
            mkdir($dirIncoming);
        }
        if (!file_exists($dirAvatars) && !is_dir($dirAvatars)) {
            mkdir($dirAvatars);
        }
        if (!Tools::checkEmptyDir($dirAvatars)) {
            $zip->zip($dirAvatars, $this->zipName);
            rename($dirAvatars . $this->zipName, $dirIncoming . $this->zipName);
            $fi = new Importer();
            $fi->import($dirIncoming . $this->zipName, $this->zipName);
        } else {
            $this->zipName = null;
        }
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
        if(sizeof($cO) == 0){
            return null;
        }
        return $cO;
    }

    private function saveUserAttributes($cols, $fileHandle)
    {
        $uaA = array();
        $app = Core::make('app');
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
        $s = array();
        foreach($cols as $c) {
            $ua = UserKey::getByHandle($c);
            $cat = $app->make(UserCategory::class)->getAttributeKeyByHandle($c);
            $s['uakProfileDisplay'] = $cat->isAttributeKeyDisplayedOnProfile();
            $s['uakProfileEdit'] = $cat->isAttributeKeyEditableOnProfile();
            $s['uakProfileEditRequired'] = $cat->isAttributeKeyRequiredOnProfile();
            $s['uakRegisterEdit'] = $cat->isAttributeKeyEditableOnRegister();
            $s['uakRegisterEditRequired'] = $cat->isAttributeKeyRequiredOnRegister();
            $s['uakMemberListDisplay'] = $cat->isAttributeKeyDisplayedOnMemberList();
            $s['akIsSearchable'] = $cat->isAttributeKeySearchable();
            $s['akIsInternal'] = $cat->isAttributeKeyInternal();
            $s['akIsSearchableIndexed'] = $cat->isAttributeKeyContentIndexed();
            $s['akName'] = $cat->getAttributeKeyDisplayName();
            if(is_object($ua)) {
                $r = $this->db->executeQuery($query, array($ua->getAttributeKeyID()));
                $res = $r->FetchRow();
                if($res) {
                    $s['setID'] = $res['asID'];
                } else {
                    $s['setID'] = null;
                }
                $uaA[$c] = Core::make('helper/json')->encode($s);
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
        $this->fileNameCleaned = $this->getFilenameCleaned();
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

    public function createFileObject()
    {
        $fi = new Importer();
        return $fi->import($this->csvFileName, $this->fileName . '.csv');
    }
}
