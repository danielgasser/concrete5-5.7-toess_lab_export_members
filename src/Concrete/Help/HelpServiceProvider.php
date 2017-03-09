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
            t('
<p><strong>CSV-Settings:</strong></p>
<p>Enter a filename for the CSV-file without extension.<br>
The following settings are prefilled with the common CSV-Settings. If unsure, leave at is.<br>
Enter a CSV-Delimiter charcater (one character only):<br>
Enter a CSV-Enclosure charcater (one character only):<br>
Enter a CSV-Escape charcater (one character only):<br>
<p><strong>Basic User Attributes to export:</strong></p>
These attributes are the default concrete5 User attributes.<br>
<p><strong>User Attributes to export:</strong></p>
These attributes can be found and extended under Dashboard &gt; Members &gt; Attributes.<br>
<p><strong>Export Community Points</strong></p>
These can be found and added under Dashboard &gt; Members &gt; Community Points.<br>
<p><strong>Export Users Groups</strong></p>
The User Groups can be found and managed under Dashboard &gt; User Groups.<br>
Optionally you may save Export Settings for the next time use.<br>
<p><strong>Filter by User Groups:</strong></p>
You may choose to export User from one ore multiple groups by selecting them here. If you choose the "Administrators" group you have the possibility to include the Super User (Username admin) too.<br>
<p><strong>Filter by Username or Email:</strong></p>
Search for users by their username or email. Any result matching the username or email will be chosen.<br>
When havng selected all desired users, go to the top of the page and click "Export to CSV".<br>
The exported CSV file is saved as File in the Filemanager</p>
')
        );
    }
}