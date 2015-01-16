<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\gist;

use Herbie;
use Twig_SimpleFunction;

class GistPlugin extends Herbie\Plugin
{

    /**
     * @param Herbie\Event $event
     */
    public function onTwigInitialized(Herbie\Event $event)
    {
        $event['twig']->addFunction(
            new Twig_SimpleFunction('gist', [$this, 'gist'], ['is_safe' => ['html']])
        );
    }

    /**
     * @param string $id
     * @param string $file
     * @return string
     */
    public function gist($id, $file = '')
    {
        return "<script src=\"http://gist.github.com/{$id}.js" . ($file == '' ? '' : '?file=' . $file) . "\"></script>";
    }

}
