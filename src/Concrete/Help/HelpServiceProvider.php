<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 8/8/15
 * Time: 8:40 AM
 */

namespace Concrete\Package\ToessLabExportMembers\Help;
use Concrete\Core\Foundation\Service\Provider;

class HelpServiceProvider extends Provider {

    public function register()
    {
        $this->app['help/dashboard']->registerMessageString('/dashboard/users/toess_lab_export_members',
            t('CSV-Settings:<br>If you\'re unsure about these settings, leave them as they are. In most of the cases it should be fine.')
        );
    }
}