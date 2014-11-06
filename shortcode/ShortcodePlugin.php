<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\shortcode;

use herbie\plugin\shortcode\classes\Shortcode;

class ShortcodePlugin extends \Herbie\Plugin
{
    public function onContentSegmentLoaded(\Herbie\Event $event)
    {
        $tags = $event['app']['config']->get('plugins.shortcode', []);

        foreach($tags as $tag => $_callable){
            $tags[$tag] = create_function('$atts, $content', $_callable.';');
        }
        $shortcode = new Shortcode($tags);

        $event['segment'] = $shortcode->parse($event['segment']);
    }
}
