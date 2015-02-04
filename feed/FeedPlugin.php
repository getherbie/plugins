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
        $this->menu = $event['app']['menu'];
        $this->addPage($this->config('plugins.config.feed.pages.rss', '@plugin/feed/pages/feed.rss'));
        $this->addPage($this->config('plugins.config.feed.pages.atom', '@plugin/feed/pages/feed.atom'));
    }

    /**
     * @param $alias
     */
    private function addPage($alias)
    {
        $path = $this->app['alias']->get($alias);
        $loader = new FrontMatterLoader();
        $item = $loader->load($path);
        $item['path'] = $alias;
        $this->menu->addItem(new Item($item));
    }

}
