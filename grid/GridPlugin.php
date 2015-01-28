<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\grid;

use Herbie;
use Twig_SimpleFunction;

class GridPlugin extends Herbie\Plugin
{

    private $templates = [];

    public function init()
    {
        $this->templates = $this->config('plugins.config.grid.templates', []);
    }

    public function onContentSegmentLoaded(Herbie\Event $event)
    {
        $replaced = preg_replace_callback(
            '/-{2}\s+grid\s+(.+?)-{2}(.*?)-{2}\s+grid\s+-{2}/msi',
            [$this, 'replace'],
            $event['segment']
        );
        if (!is_null($replaced)) {
            $event['segment'] = $replaced;
        }
    }

    /**
     * @param array $matches
     * @return string
     */
    private function replace($matches)
    {
        if (empty($matches) || (count($matches) <> 3)) {
            return null;
        }

        $key = trim($matches[1]);
        $content = $matches[2];

        // no template defined
        if (!array_key_exists($key, $this->templates)) {
            // return content as it is
            return $content;
        }
        $template = $this->templates[$key];

        // split cols
        $cols = preg_split('/--\r?\n/', $content);

        $html = '';

        // replace cols
        foreach ($cols as $i => $col) {
            if (isset($template['cols'][$i])) {
                $html .= str_replace(['{col}', '{index}'], [$col, $i+1], $template['cols'][$i]);
            } else {
                $html .= $col;
            }
        }

        // replace row
        if (array_key_exists('row', $template)) {
            return str_replace('{row}', $html, $template['row']);
        }

        // give it back
        return $html;
    }
}
