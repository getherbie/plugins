<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\feediting;

class FeeditingPlugin extends \Herbie\Plugin
{
    private $config = array();

    private $authenticated = false;

    private $app = null;

    public function __construct()
    {
        // TODO: Implement some kind of authentication!
        $this->authenticated = true;
    }

    public function __call($funcname, $args)
    {
        if($this->authenticated === true){
            return $this->{$funcname}($args[0]);
        } else {
            return;
        }
    }

    // call document.ready-function in footer
    protected function onOutputGenerated(\Herbie\Event $event )
    {

        $_app          = $event->offsetGet('app');
        $_response     = $event->offsetGet('response');
        $_plugin_path  = str_replace($_app['webPath'], '', $_app['config']->get('plugins_path')).'/feediting/';

        $this->marks['</body>'] =
            '<script src="'.$_plugin_path.'libs/jquery_jeditable-master/jquery.jeditable.js" type="text/javascript" charset="utf-8"></script>'
            .'<script type="text/javascript" charset="utf-8">'
            .$this->getJSEditablesConfig($_plugin_path)
            .'</script>'
            .'</body>';

        $_response->setContent(strtr($_response->getContent(), $this->marks));
        $event->offsetSet('response', $_response);
    }

    // fetch markdown-contents for jeditable
    protected function onPageLoaded(\Herbie\Event $event )
    {
        $_page = $event->offsetGet('page');
        $_segments = $_page->getSegments();
        $_content = array();

        foreach($_segments as $seguid => $content){
            $identified = $this->getParagraphs($_page->getFormat(), $content, $seguid);
            $_segments[$seguid] = implode($identified['eop'], $identified['paragraphs']);
            $_content[$seguid] = array(
                'paragraphs' => $identified['paragraphs'],
                'format' => $identified['format']
            );
        };

        switch(@$_REQUEST['cmd'])
        {
            case 'load':
                list($contenturi, $elemid) = explode('#', $_REQUEST['id']);
                list($contenttype, $contentkey) = explode('-', $contenturi);
                if($request = $_content[$contentkey]['paragraphs'][$elemid]){
                    echo(trim(strtr($request, $this->nullmarks)));
                }
                die();

            case 'save':
                if($_POST && $_POST['value'] && $_POST['id']) {
                    $this->app = $event->offsetGet('app');
                    list($contenturi, $elemid) = explode('#', $_POST['id']);
                    list($contenttype, $contentkey) = explode('-', $contenturi);
                    // save to md-file
                    // render contents
                    echo $this->renderContent($_POST['value'], $contenttype);
                }
                die();

            default:
                $_page->setSegments($_segments);
        }
    }

    /**
     * @param string $content
     * @param int $uid
     * @return array('paragraphs', 'eop', 'format')
     */
    private function getParagraphs($format, $content, $uid)
    {
        // currently only (twitter-bootstrap)markdown supported
        $ret = $this->{'identify'.ucfirst($format)}($content, $uid);
//        var_dump($ret);
        return $ret;
    }

    /**
     * @param $content
     * @param $uid
     * @return array() numbered paragraphs
     */
    private function identifyMarkdown($content, $uid)
    {
        $ret = array();
        $format = 'markdown';
        $eol = "\n";
        $eop = "\n";
        $class = 'editable_'.$format;
        $openBlock = true;
        $closeBlock = false;
        $blockId = 0;

        $this->setJSEditableConfig($format, $class);

//        var_dump($content);

        $lines = explode($eop, $content);
        foreach($lines as $id => $line) {

//            var_dump($line);

                // don't edit bootstrap-markdown:
                // eg content like "-- row 4,4,4 --"
            if(strpos($line,'-- row ')!==false) {
                $ret[$id] = $line;
                if($blockId) {
                    $ret[$blockId] .= $eol.'<!-- ###'.$class.'### Stop -->'.$eol;
                    $blockId = 0;
                    $openBlock = true;
                }
                continue;
            }

            switch($line){

                    // don't edit bootstrap-markdown...
                case "----":
                case "-- end --":
                    // ...and blank lines
                case "":
                    $ret[$id] = $line;
                    if($blockId) {
                        $ret[$blockId] .= $eol.'<!-- ###'.$class.'### Stop -->'.$eol;
                        $blockId = 0;
                        $openBlock = true;
                    }
                    break;

                default:
                    if($openBlock)
                    {
                        $blockId = $id;
                        $ret[$blockId] = $eol.'<!-- ###'.$class.'-'.$id.'### Start -->'.$eol.$line.$eol;
                        $openBlock = false;
                    } else {
                        $ret[$blockId] .= $line.$eol;
                    }
//                    $ret[$id] =
//                        $eol.'<!-- ###'.$class.'-'.$id.'### Start -->'.$eol
//                        .$line
//                        .$eol.'<!-- ###'.$class.'### Stop -->'.$eol;

                    if(!isset($this->marks['<!-- ###'.$class.'-'.$blockId.'### Start -->'])) {
                        $this->marks['<!-- ###'.$class.'-'.$blockId.'### Start -->'] = '<div class="'.$class.'" id="'.$format.'-'.$uid.'#'.$blockId.'">';
                        $this->nullmarks['<!-- ###'.$class.'-'.$blockId.'### Start -->'] = '';
                    }
                    if(!isset($this->marks['<!-- ###'.$class.'### Stop -->'])) {
                        $this->marks['<!-- ###'.$class.'### Stop -->'] = '</div>';
                        $this->nullmarks['<!-- ###'.$class.'### Stop -->'] = '';
                    }
            }
        }

//        var_dump($ret);

        return array(
            'paragraphs' => $ret,
            'eop' => $eop,
            'format' => $format
        );
    }
    
    private function getJSEditablesConfig($pluginPath)
    {
        $ret = array();

        foreach($this->config['editables'] as $type){
            $ret[] = strtr($type['config'], array(
                '###plugin_path###' => $pluginPath
            ));
        }
        return implode($ret);
    }

    private function setJSEditableConfig($format, $class, $type='textarea')
    {
        $this->config['editables'][$format] = array(

            'identifier' => $class,
            'config'     => '
$(function() {
    $(".'.$class.'").editable("?cmd=save&renderer='.$format.'", {
        indicator : "<img src=\'###plugin_path###libs/jquery_jeditable-master/img/indicator.gif\'>",
        loadurl   : "?cmd=load&renderer='.$format.'",
        type      : "'.$type.'",
        submit    : "OK",
        cancel    : "Cancel",
        tooltip   : "Click to edit..."
    });
});
'
        );
    }
        
    // store changed markdown

    /**
     * @param string $content
     * @param string $format, eg. 'markdown'
     * @return string
     */
    private function renderContent($content, $format)
    {
        $twigged = $this->app['twig']->render($content);

        $formatter = \Herbie\Formatter\FormatterFactory::create($format);
        return $formatter->transform($twigged);
    }
}