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
use Concrete\Core\Job\Job;
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
    protected $fileName = 'toesslab';
    protected $zipName;
    protected $csvFileName;
    protected $csvSettings = array();
    protected $job;



    function __construct()
    {
        $this->csvSettings = Config::get('toess_lab_export_members.csv-settings');
    }

    /**
     * @param $args
     * @return mixed
     */
    public function setPostData($args)
    {
        $this->job = Job::getByHandle('export_users');
        $this->job->reset();
        $time = new \DateTime();
        $timeStamp = $time->format('Y-m-d-H-i-s');

        $fileName = $timeStamp . '_' . $args['fileName'];
        $args['fileNameCleaned'] = $this->getFilenameCleaned($fileName);
        // check if at least uID, uEmail and uName are present
        $basics = array(
            '0' => 'uID',
            '1' => 'uName',
            '2' => 'uEmail'
        );
        $args['baseColumns'] = array_replace_recursive($args['baseColumns'], $basics);
        if (sizeof($args['columns']) == 0) {
            $args['columns'] = array();
        }
        $this->job->setExport($args);
        $this->csvSettings = Config::get('toess_lab_export_members.csv-settings');
        $r['result'] = $this->queueUserIds($args);
        $r['csvFileName'] = $args['fileNameCleaned'];
        $r['zipFileName'] = $args['fileNameCleaned'] . '_userAvatars.zip';
        return $r;
    }

    /**
     * @param null $fileName
     * @return mixed
     */
    public function getFilenameCleaned($fileName = null)
    {
        $fn = ($fileName == null) ? $this->fileName : $fileName;
        return str_replace($this->replace, $this->with, $fn);
    }

    /**
     * @return string
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
     * @return string
     */
    public function getZipFilename()
    {
        return $this->zipName;
    }

    /**
     * @param $args
     * @return int
     */
    protected function queueUserIds($args)
    {
        $this->appendMetaDataToUserExportCSVFile($args);
        $this->job->executeJob();
        $this->saveUserAttributes($args['columns'], $args['fileNameCleaned']);
        return count($this->job->result);
    }

    /**
     * @param $ids
     * @param $args
     * @return array|bool
     */
    public function getUsers($ids, $args)
    {
        $results = array();
        $app = Core::make('app');
        $db = Core::make('database');
        $id = $ids;
        $query = 'select ' . implode(', ', $args['baseColumns']) . ' from Users where uID = ' . $id . ' order by uID asc';
        $r = $db->executeQuery($query);
        $uRes = $r->fetchAll(\PDO::FETCH_ASSOC);
        $results[$uRes[0]['uID']] = $uRes[0];
        $userInfoFactory = $app->make('Concrete\Core\User\UserInfo');
        $ui = $userInfoFactory->getByID($id);
        $results[$id]['attributes'] = Core::make('helper/json')->encode($this->getUserAttributes($ui, $id, $args));
        if($args['usersGroups']){
            $results[$id]['usersGroups'] = $this->collectUserGroups($ui);
            $args['columns'][] = 'userGroups';
        }
        if($args['userPoints']) {
            $results[$id]['communityPoints'] = Core::make('helper/json')->encode($this->getCommunityPoints($id));
            $args['columns'][] = 'communityPoints';
        }
        $this->zipUserAvatars();
        if(sizeof($results) == 0) {
            return false;
        }
        $this->writeToCSVFile($results[$id], $args['fileNameCleaned']);
        return $results;
    }

    protected function collectUserGroups($ui)
    {
        $u = $ui->getUserObject();
        $u->refreshUserGroups();
        $userGroupIDs = $u->getUserGroups();
        if ($u->getUserID() == USER_SUPER_ID) {
            $userGroupIDs[] = Group::getByName('Administrators')->getGroupID();
        }
        $groups = $this->getUserGroups($userGroupIDs);
        $results = Core::make('helper/json')->encode($groups);
        return $results;
    }

    /**
     * @param $ui
     * @param $id
     * @param $args
     * @return array
     */
    protected function getUserAttributes($ui, $id, $args)
    {
        $results = array();
        foreach ($args['columns'] as $c) {
            if ($ui->getAttribute($c) == NULL) {
                $results[$id][$c] = 'NULL';
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
                            $results[$id][$c] = $s;
                        } else {
                            $results[$id][$c] = $uiAttribute;
                        }
                        break;
                    case $uiAttribute  instanceof SocialLinksValue:
                        $s = array();
                        foreach($uiAttribute->getSelectedLinks()->getValues() as  $key => $value) {
                            $s[$key] = $this->getSocialLinksValues($value);
                        }
                        $results[$id][$c] = $s;
                        break;
                    case $uiAttribute instanceof ExpressValue:
                        $s = $this->getExpressValue($uiAttribute);
                        $results[$id][$c] = $s;
                        break;
                    case $uiAttribute instanceof AddressValue:
                        $s = $this->getAddressValue($uiAttribute);
                        $results[$id][$c] = $s;
                        break;
                    case $uiAttribute instanceof \DateTime:
                        $results[$id][$c] = str_replace("\n", '\\n', $uiAttribute->format('Y-m-d h:s:i'));
                        break;
                    default:
                        $results[$id][$c] = str_replace("\n", '\\n', $uiAttribute);

                }
            }
        }
        return $results;
    }

    /**
     * @param $attribute
     * @param array $a
     * @param bool $key
     * @return array
     */
    protected function getExpressValue($attribute, $a = array(), $key = false)
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
        }
        return $a;
    }

    /**
     * @param SelectedSocialLink $attr
     * @return array
     */
    protected function getSocialLinksValues(SelectedSocialLink $attr)
    {
        $s = array();
        $s['service'] = $attr->getService();
        $s['serviceInfo'] = $attr->getServiceInfo();
        return $s;
    }

    /**
     * @param AddressValue $attr
     * @return array
     */
    protected function getAddressValue(AddressValue $attr)
    {
        return get_object_vars($attr);
    }

    /**
     *
     */
    protected function zipUserAvatars()
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

    /**
     * @param array $groupIds
     * @return array
     */
    protected function getUserGroups(array $groupIds)
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

    /**
     * @param $uID
     * @return array|null
     */
    protected function getCommunityPoints($uID)
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

    /**
     * @param $cols
     * @param $fileName
     * @return bool
     */
    protected function saveUserAttributes($cols, $fileName)
    {
        // ToDo Associations, Entities
        $fileHandle = $this->createOrGetUserExportCSVFile($fileName);
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
            Tools::setProgress('Saving user attributes');
        }
        foreach ($uaA as $k => $a){
            if (!fputcsv($fileHandle, array($a), $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
                return false;
            }
        }
        fclose($fileHandle);
    }

    /**
     * @param null $fileName
     * @return bool|resource
     */
    protected function createOrGetUserExportCSVFile($fileName = null)
    {
        if ($fileName == null) {
            $fn = $this->getFilenameCleaned();
        } else {
            $fn = $fileName;
        }
        $this->csvFileName = DIRNAME_APPLICATION . '/files/incoming/' . $fn . '.csv';
        return Tools::createFilePointer($this->csvFileName, 'a');
    }

    /**
     * @param $args
     * @return bool
     */
    protected function appendMetaDataToUserExportCSVFile($args)
    {
        $fileHandle = $this->createOrGetUserExportCSVFile($args['fileNameCleaned']);
        $cols = array_merge($args['baseColumns'], $args['columns']);
        if (!$fileHandle) {
            return false;
        }
        if (!fputcsv($fileHandle, $cols, $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
            return false;
        }
        fclose($fileHandle);
        return true;
    }

    /**
     * @param $res
     * @param $fileName
     * @return array|int
     */
    protected function writeToCSVFile($res, $fileName)
    {
        $fileHandle = $this->createOrGetUserExportCSVFile($fileName);
        $csvCount = 0;
        $csvErrors = [];
        if (!fputcsv($fileHandle, array_values($res), $this->csvSettings['csv-delimiter'], $this->csvSettings['csv-enclosure'], $this->csvSettings['csv-escape'])) {
            $csvErrors[] = t('Record %s could not be saved.', $res['uName']);
        } else {
            $csvCount++;
        }
        if (sizeof($csvErrors) > 0) {
            return $csvErrors;
        }
        fclose($fileHandle);
        return $csvCount;
    }

    /**
     * @return number
     */
    public function createFileObject()
    {
        $fi = new Importer();

        return $fi->import($this->csvFileName, $this->fileName . '.csv');
    }
}
