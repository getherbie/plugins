<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\yce;

use Herbie;
use Twig_SimpleFunction;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class YcePlugin extends Herbie\Plugin
{

    private $templates = [];

    public function init()
    {
    }

    public function onContentSegmentLoaded(Herbie\Event $event)
    {
        $replaced = preg_replace_callback(
            '/-{2}\s+ce\s+(.+?)-{2}(.*?)-{2}\s+ce\s+-{2}/msi',
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
        $yaml = $matches[2];
        $params = ['_raw_' => $yaml];

        try {
            try {
                $params = array_merge($params, (array)Yaml::parse($yaml));
            } catch (ParseException $e) {
            }
            #echo"<pre>";print_r($params);echo"</pre>";
            $template = $this->getTemplateByKey($key);
            $twigged = $this->app['twig']->render($template, $params);
            return $twigged;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return "<pre>" . $message . "\n" . $matches[0] . "</pre>";
            #return "```\n" . $matches[0] . "\n```";
        }

        /*
        $method = $key . 'Element';
        if(method_exists($this, $method)) {

            $params = [];

            $reflect = new \ReflectionMethod($this, $method);
            foreach($reflect->getParameters() as $param) {
                if(array_key_exists($param->name, $elements)) {
                    $params[$param->name] = $elements[$param->name];
                } else {
                    $params[$param->name] = null;
                }
            }
            #echo"<pre>";print_r($params);echo"</pre>";
            #echo"<pre>";print_r($reflect->getParameters());echo"</pre>";
            return call_user_func_array([$this, $method], $params);
        }
        return;
        */
    }

    private function getTemplateByKey($key)
    {
        $config = "plugins.config.yce.template.{$key}";
        $default = "@plugin/yce/templates/{$key}.twig";
        return $this->config($config, $default);
    }

    public function imgtextElement($text, $img)
    {
        $template = $this->config(
            'plugins.config.yce.template.imgtext',
            '@plugin/yce/templates/imgtext.twig'
        );
        return $this->app['twig']->render($template, [
            'text' => $text,
            'img' => $img,
        ]);
    }
}
