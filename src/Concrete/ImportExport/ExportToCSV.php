<?php
namespace Concrete\Package\ToessLabExportMembers\ImportExport;
use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Attribute\Key\UserKey;
use Concrete\Core\Entity\Attribute\Value\Value\AddressValue;
use Concrete\Core\Entity\Attribute\Value\Value\ExpressValue;
use Concrete\Core\Entity\Attribute\Value\Value\SelectedSocialLink;
use Concrete\Core\Entity\Attribute\Value\Value\SocialLinksValue;
use Concrete\Core\File\Service\Zip;
use Concrete\Core\Foundation\Queue\Queue;
use Concrete\Core\Tree\Node\Type\Topic;
use Concrete\Package\ToessLabExportMembers\Helper\Tools;
use Core;
use Concrete\Core\User\Group\Group;
use \Concrete\Core\User\Point\Entry as UserPointEntry;
use Config;
use Concrete\Core\File\Importer;
use Session;
use UserAttributeKey;
use Express;
use QueueableJob;

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
    public $csvErrors = array();
    protected $postData = array();
    public $fileName = 'toesslab';
    public $fileNameCleaned;
    public $zipName;
    public $csvFileName;
    public $timeStamp;
    protected $entityManager;
    public $userIds = array();
    public $baseColumns = array();
    public $columns = array();
    public $results = array();
    public $userInfoFactory;
    public $userPoints = false;
    public $usersGroups = false;
    public $csvSettings = array();
    public $userQueue;
    public $messages;
    public $i = 1;
    public $session;



    function __construct($args)
    {
        $this->timeStamp = new \DateTime();
        $this->timeStamp = $this->timeStamp->format('Y-m-d-H-i-s');
        $this->csvSettings = Config::get('toess_lab_export_members.csv-settings');
        $this->setPostData($args);
        $this->session = DIRNAME_APPLICATION . '/files/incoming/' . 'queue.json';
        $this->userQueue = Queue::get('userQueue');
    }

    /**
     * @param $args
     */
    public function setPostData($args)
    {
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
        $this->csvSettings = Config::get('toess_lab_export_members.csv-settings');
        $this->baseColumns = array_replace_recursive($this->baseColumns, $basics);
        $this->createUserExportCSVFile();
    }

    /**
     * @return mixed
     */
    public function getFilenameCleaned()
    {
        return str_replace($this->replace, $this->with, $this->fileName);
    }

    /**
     * @return mixed
     */
    public function getCSVFilename()
    {
        return $this->csvFileName;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->fileName;
    }

    /**
     * @return mixed
     */
    public function getZipFilename()
    {
        return $this->zipName;
    }

    public function queueUserIds()
    {
        $res = false;
        $db = Core::make('database');
        $queryIDs = implode(', ', $this->userIds);
        $query = 'select uID from Users where uID in(' . $queryIDs . ') order by uID asc';
        $r = $db->executeQuery($query);
        while($uRes = $r->fetchRow(\PDO::FETCH_ASSOC)) {
            $this->messages = $this->userQueue->receive(5);
            $this->userQueue->send($uRes['uID']);
            $res = $this->getUsers($this->messages);
        }
        return $res;
    }

    /**
     * @param $msg
     * @return bool
     */
    public function getUsers($msg)
    {
        $app = Core::make('app');
        $db = Core::make('database');
        $userInfoObjects = array();
        $time = new \DateTime();
        $this->i++;
        foreach ($msg as $k => $m) {
            $id = $m->body;
            $query = 'select ' . implode(', ', $this->baseColumns) . ' from Users where uID = ' . $id . ' order by uID asc';
            $r = $db->executeQuery($query);
            foreach ($uRes = $r->fetchAll(\PDO::FETCH_ASSOC) as $k => $u) {
                //foreach ($u as $k => $ua) {
                    $this->results[$u['uID']] = $u;
                //}
            }
            $this->userInfoFactory = $app->make('Concrete\Core\User\UserInfo');
            $ui = $this->userInfoFactory->getByID($id);
            $userInfoObjects[] = $ui;
            //$this->getUserAttributes($ui, $id);
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
                $this->results[$id]['communityPoints'] = Core::make('helper/json')->encode($this->getCommunityPoints($id));
                $this->columns[] = 'communityPoints';
            }
            //$this->zipUserAvatars();
            $this->userQueue->deleteMessage($m);
            file_put_contents($this->session, json_encode(['message' => t('Exporting users'), 'current' => $this->i, 'total' => count($this->userIds), 'time' => $time->getTimestamp()]));
        }
        if(sizeof($this->results) == 0) {
            return false;
        }

        return true;
    }

    /**
     * @param $ui
     * @param $id
     */
    public function getUserAttributes($ui, $id)
    {
        foreach ($this->columns as $c) {
            if ($ui->getAttribute($c) == NULL) {
                $this->results[$id][$c] = 'NULL';
            } else {
                $uiAttribute = $ui->getAttribute($c);
                switch (true){
                    case is_array($uiAttribute):
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
                        break;
                    case $uiAttribute  instanceof SocialLinksValue:
                        $s = array();
                        foreach($uiAttribute->getSelectedLinks()->getValues() as  $key => $value) {
                            $s[$key] = $this->getSocialLinksValues($value);
                        }
                        $this->results[$id][$c] = Core::make('helper/json')->encode($s);
                        break;
                    case $uiAttribute instanceof ExpressValue:
                        $s = $this->getExpressValue($uiAttribute);
                        $this->results[$id][$c] = Core::make('helper/json')->encode($s);
                        break;
                    case $uiAttribute instanceof AddressValue:
                        $s = $this->getAddressValue($uiAttribute);
                        $this->results[$id][$c] = Core::make('helper/json')->encode($s);
                        break;
                    case $uiAttribute instanceof \DateTime:
                        $this->results[$id][$c] = str_replace("\n", '\\n', $uiAttribute->format('Y-m-d h:s:i'));
                        break;
                    default:
                        $this->results[$id][$c] = str_replace("\n", '\\n', $uiAttribute);

                }
            }
        }
    }

    public function getExpressValue($attribute, $a = array(), $key = false)
    {
        foreach ($attribute->getSelectedEntries() as $expressValueEntry) {
            foreach($expressValueEntry->getEntity()->getAttributeKeyCategory()->getList() as $entry) {
                if ($expressValueEntry->getAttribute($entry->getAttributeKeyHandle()) instanceof ExpressValue) {
                    if (!$key) {
                        $a[$entry->getAttributeKeyHandle()] = $this->getExpressValue($expressValueEntry->getAttribute($entry->getAttributeKeyHandle()), $a, $entry->getAttributeKeyHandle());
                    } else {
                        $a[$key][$entry->getAttributeKeyHandle()] = $this->getExpressValue($expressValueEntry->getAttribute($entry->getAttributeKeyHandle()), $a, $entry->getAttributeKeyHandle());
                    }
                } else {
                    if (!$key) {
                        $a[$entry->getAttributeKeyHandle()] = $expressValueEntry->getAttribute($entry->getAttributeKeyHandle());
                    } else {
                        $a[$key][$entry->getAttributeKeyHandle()] = $expressValueEntry->getAttribute($entry->getAttributeKeyHandle());
                    }
                }
            }

            // $a[] = $this->getExpressValue($expressValueEntry);
        }
        return $a;
    }

    private function getSocialLinksValues(SelectedSocialLink $attr)
    {
        $s = array();
        $s['service'] = $attr->getService();
        $s['serviceInfo'] = $attr->getServiceInfo();
        return $s;
    }

    public function getAddressValue(AddressValue $attr)
    {
        return get_object_vars($attr);
    }

    public function zipUserAvatars()
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
        //$time = new \DateTime();
        //file_put_contents($this->session, json_encode(['message' => t('Exporting User Groups'), 'current' => $this->i, 'total' => count($this->userIds), 'time' => $time->getTimestamp()]));
        return $groups;
    }

    private function getCommunityPoints($uID)
    {
        $db = Core::make('database');
        $r = $db->executeQuery('select upID from UserPointHistory where upuID = :uID', array('uID' => $uID));
        $cO = array();
        $i = 0;
        while ($uRes = $r->fetchRow(\PDO::FETCH_ASSOC)) {
            $up = new UserPointEntry();
            $up->load($uRes['upID']);
            $cO[$i]['userPointAction'] = (array)$up->getUserPointEntryActionObject();
            $cO[$i]['userPointDescription'] = (array)$up->getUserPointEntryDescriptionObject();
            $cO[$i]['userPointDateTime'] = (array)$up->getUserPointEntryDateTime();
            $i++;
        }
        if(sizeof($cO) == 0){
            return null;
        }
        return $cO;
    }

    public function saveUserAttributes($cols, $fileHandle)
    {
        // ToDo Associations, Entities
        $db = Core::make('database');
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
        foreach($cols as $k => $c) {
            $ua = UserKey::getByHandle($c);
            $cat = $app->make(UserCategory::class)->getAttributeKeyByHandle($c);
            if (!is_object($cat)) {
                break;
            }
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
                $r = $db->executeQuery($query, array($ua->getAttributeKeyID()));
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
        foreach ($this->results as $k => $r) {
            if (!fputcsv($fileHandle, $r, $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
                $this->csvErrors[] = t('Record %s could not be saved.', $r['uName']);
            } else {
                $csvCount++;
            }
        }
        $time = new \DateTime();
        file_put_contents($this->session, json_encode(['message' => t('Writing to CSV'), 'current' => $csvCount, 'total' => count($this->userIds), 'time' => $time->getTimestamp()]));
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
