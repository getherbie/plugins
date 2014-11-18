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

    private $app = null;

    // @idea: implement a jeditable-UI
    // @todo:
    // call document.ready-function in footer
    public function onOutputGenerated(\Herbie\Event $event )
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
    public function onPageLoaded(\Herbie\Event $event )
    {
        $_page = $event->offsetGet('page');
        $_segments = $_page->getSegments();
        $_content = array();

        foreach($_segments as $seguid => $content){
            $identified = $this->identifyParagraphs($content, $seguid);
            $_segments[$seguid] = implode($identified['eol'], $identified['paragraphs']);
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
     * @return array('paragraphs', 'eol', 'format')
     */
    private function identifyParagraphs($content, $uid)
    {
            // currently only (twitter-bootstrap)markdown supported
        return $this->identifyMarkdown($content, $uid);
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
        $class = 'editable_'.$format;

        $this->setJSEditableConfig($format, $class);

        $paragraphs = explode($eol, $content);
        foreach($paragraphs as $id => $paragraph) {

                // don't edit bootstrap-markdown:
                // eg content like "-- row 4,4,4 --"
            if(strpos($paragraph,'-- row ')!==false) {
                $ret[$id] = $paragraph;
                continue;
            }

            switch($paragraph){

                    // don't edit bootstrap-markdown...
                case "----":
                case "-- end --":
                    // ...and blank lines
                case "":
                    $ret[$format.'-'.$uid.'#'.$id] = $paragraph;
                    break;

                default:
                    $ret[$id] =
                        $eol.'<!-- ###'.$class.'-'.$id.'### Start -->'.$eol
                        .$paragraph
                        .$eol.'<!-- ###'.$class.'### Stop -->'.$eol;

                    if(!isset($this->marks['<!-- ###'.$class.'-'.$id.'### Start -->'])) {
                        $this->marks['<!-- ###'.$class.'-'.$id.'### Start -->'] = '<div class="'.$class.'" id="'.$format.'-'.$uid.'#'.$id.'">';
                        $this->nullmarks['<!-- ###'.$class.'-'.$id.'### Start -->'] = '';
                    }
                    if(!isset($this->marks['<!-- ###'.$class.'### Stop -->'])) {
                        $this->marks['<!-- ###'.$class.'### Stop -->'] = '</div>';
                        $this->nullmarks['<!-- ###'.$class.'### Stop -->'] = '';
                    }
            }
        }
        return array(
            'paragraphs' => $ret,
            'eol' => $eol,
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