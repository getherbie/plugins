<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\tracy;

use Tracy\Debugger;

class TracyPlugin extends \Herbie\Plugin
{

    public function onPluginsInitialized(\Herbie\Event $event)
    {
        Debugger::enable();
    }

}
