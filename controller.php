<?php
namespace Concrete\Package\ToessLabExportMembers;

use AssetList;
use Package;
use Page;
use SinglePage;
use Core;
use Asset;
use Config;
use Concrete\Package\ToessLabExportMembers\Help\HelpServiceProvider;

class Controller extends Package {

    /**
     * @var string
     */
    protected $pkgHandle = 'toess_lab_export_members';
    protected $appVersionRequired = '5.7.5.9';
    protected $pkgVersion = '0.9.4';
    protected $pkgAutoloaderMapCoreExtensions = true;

    public function getPackageDescription()
    {
        return t("Export Site members to CSV (Excel).");
    }

    public function getPackageName()
    {
        return t("Export Site members to CSV");
    }


    public function install()
    {
        $pkg = parent::install();
        $this->installOrUpgrade($pkg);
        $this->setCsvSettings();
    }

    public function upgrade()
    {
        parent::upgrade();
        $pkg = self::getPackageHandle();
    }

    public function uninstall()
    {
        parent::uninstall();
    }

    public function on_start()
    {
        $app = Core::make('app');
        $pkg = $this;
        $al = AssetList::getInstance();
        $al->register(
            'css', 'toess_lab_export_members', 'css/toesslab.css', array('position' => Asset::ASSET_POSITION_HEADER), $pkg
        );
        $al->register(
            'javascript', 'toess_lab_export_members', 'js/toesslab.js', array('position' => Asset::ASSET_POSITION_FOOTER), $pkg
        );
        $al->register(
            'css', 'bootstrapswitch', 'js/libs/bootstrap_switch/bootstrap-switch.min.css', array('position' => Asset::ASSET_POSITION_HEADER), $pkg
        );
        $al->register(
            'javascript', 'bootstrapswitch', 'js/libs/bootstrap_switch/bootstrap-switch.min.js', array('position' => Asset::ASSET_POSITION_FOOTER), $pkg
        );
        $al->registerGroup('toess_lab_export_members', array(
            array('javascript', 'toess_lab_export_members'),
            array('css', 'toess_lab_export_members'),
        ));
        $al->registerGroup('bootstrapswitch', array(
            array('css', 'bootstrapswitch'),
            array('javascript', 'bootstrapswitch'),
        ));
        $provider = new HelpServiceProvider($app);
        $provider->register();
    }

    private function installOrUpgrade($pkg)
    {
        $this->getOrAddSinglePage($pkg, 'dashboard/users/toess_lab_export_members', 'toesslab - Export Members');
    }

    private function setCsvSettings()
    {
        $csvSetttings =  array(
            'csv-delimiter' => ';',
            'csv-enclosure' => '"',
            'csv-escape' => '\\'
        );
        foreach($csvSetttings as $k => $c){
            Config::save('toess_lab_export_members.csv-settings.' . $k, $c);
        }

    }

    private function getOrAddSinglePage($pkg, $cPath, $cName = '', $cDescription = '') {

        $sp = SinglePage::add($cPath, $pkg);

        if (is_null($sp)) {
            $sp = Page::getByPath($cPath);
        } else {
            $data = array();
            if (!empty($cName)) {
                $data['cName'] = $cName;
            }
            if (!empty($cDescription)) {
                $data['cDescription'] = $cDescription;
            }

            if (!empty($data)) {
                $sp->update($data);
            }
        }

        return $sp;
    }


}
