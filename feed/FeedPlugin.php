<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\feed;

use Herbie;
use Herbie\Loader\FrontMatterLoader;
use Herbie\Menu\Page\Item;

class FeedPlugin extends Herbie\Plugin
{

    /**
     * @var Herbie\Menu\Page
     */
    private $menu;

    /**
     * @param Herbie\Event $event
     */
    public function onPluginsInitialized(Herbie\Event $event)
    {
        if($this->config->isEmpty('plugins.config.feed.no_page')) {
            $this->config->push('pages.extra_paths', '@plugin/feed/pages');
        }
    }

}
