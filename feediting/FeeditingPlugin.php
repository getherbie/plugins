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

        // set defaults
        $this->config['contentBlockDimension'] = 100;
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

        foreach($_segments as $segmentid => $content){
            $content = $this->getContentBlocks($_page->getFormat(), $content, $segmentid);
            $_segments[$segmentid] = implode($content['eob'], $content['blocks']);
            $_content[$segmentid] = array(
                'blocks' => $content['blocks'],
                'format' => $content['format']
            );
        };

        switch(@$_REQUEST['cmd'])
        {
            case 'load':
                list($contenturi, $elemid) = explode('#', $_REQUEST['id']);
                list($contenttype, $contentkey) = explode('-', $contenturi);

                    // calculate pointer
                $surplus = $elemid % $this->config['contentBlockDimension'];
                $baseId = $elemid - $surplus;

                // move pointer to the requested element
                while (
                    key($_content[$contentkey]['blocks']) != $baseId
                    && current($_content[$contentkey]['blocks']) !== false
                ){
                    $request = next($_content[$contentkey]['blocks']);
                }

                    // maybe new jeditable-elements in the dom and yet no page-reload?
                $ff = $this->crossfoot($surplus);
                for($i = 0; $i < $ff; $i++){
                    $request = next($_content[$contentkey]['blocks']);
                }

                echo(trim(strtr($request, $this->nullmarks)));
                die();

            case 'save':
                if($_POST && $_POST['value'] && $_POST['id']) {
                    $this->app = $event->offsetGet('app');
                    list($contenturi, $elemid) = explode('#', $_POST['id']);
                    list($contenttype, $contentkey) = explode('-', $contenturi);

                    // save to md-file
                    // ...

                    // calculate block-dimension
                    $contentBlockDimension = ($elemid % $this->config['contentBlockDimension']==0) ? 10 : 1;

                    // "blockify" submitted content
                    $jeditables = $this->getContentBlocks($contenttype, $_POST['value'], $contentkey, $contentBlockDimension, $elemid);
                    if( count($jeditables['blocks']) > 1 ){
//                        die('<script>location.reload(true)</script>');
                    }
                    $jeditables = implode($jeditables['eob'], $jeditables['blocks']);
                    // render contents
                    echo strtr($this->renderContent($jeditables, $contenttype), $this->marks);
                }
                die();

            default:
                $_page->setSegments($_segments);
        }
    }

    /**
     * @param string $content
     * @param int $uid
     * @return array('blocks', 'eop', 'format')
     */
    private function getContentBlocks($format, $content, $contentid, $contentBlockDimension = 100, $dimensionOffset = 0)
    {
        // currently only (twitter-bootstrap)markdown supported
        return $this->{'identify'.ucfirst($format)}($content, $contentid, $contentBlockDimension, $dimensionOffset);
    }

    /**
     * @param $content
     * @param $uid
     * @return array() numbered paragraphs
     */
    private function identifyMarkdown($content, $contentid, $contentBlockDimension = 100, $dimensionOffset = 0)
    {
        $ret = array();
        $format = 'markdown';
        $eol = "\n";
        $eob = "\n"; // end of block
        $class = 'editable_'.$format;
        $openBlock = true;
        $blockId = 0;

        $this->setJSEditableConfig($format, $class);

        $lines = explode($eol, $content);
        foreach($lines as $ctr => $line)
        {
                // index + 100 so we have enough "space" to create new blocks "on-the-fly" when editing the page
            $id = $ctr * $contentBlockDimension + $dimensionOffset;

            switch($line){

                    // don't edit bootstrap-markdown...
                case "----":
                case "-- end --":
                    // ...and blank lines
                case "":
                    $ret[$id] = $line;
                    if($blockId)
                    {
                        $ret[$blockId] .= $eol.'<!-- ###'.$class.'### Stop -->'.$eol;
                        $blockId = 0;
                        $openBlock = true;
                    }
                    break;

                default:

                    // don't edit bootstrap-markdown (cont.):
                    // eg content like "-- row n1,n2,n3,..,n12 --"
                    if(substr($line, 0, strlen('-- row ')) == '-- row ')
                    {
                        $ret[$id] = $line;
                        if($blockId)
                        {
                            $ret[$blockId] .= $eol.'<!-- ###'.$class.'### Stop -->'.$eol;
                            $blockId = 0;
                            $openBlock = true;
                        }
                        continue;
                    }

                    // group some elements in their own block, eg header:
                    if(substr($line, 0, strlen('#')) == '#')
                    {
                        $ret[$id] = $line;
                        if($blockId)
                        {
                            $ret[$blockId] .= $eol.'<!-- ###'.$class.'### Stop -->'.$eol;
                            $blockId = 0;
                            $openBlock = true;
                        }
                    }

                    if($openBlock)
                    {
                        $blockId = $id;
                        $ret[$blockId] = $eol.'<!-- ###'.$class.'-'.$id.'### Start -->'.$eol;
                        $ret[$blockId] .= $line.$eol;
                        $openBlock = false;
                    }
                    else
                    {
                        $ret[$blockId] .= $eol.$line.$eol;
                    }

                    if(!isset($this->marks['<!-- ###'.$class.'-'.$blockId.'### Start -->']))
                    {
                        $this->marks['<!-- ###'.$class.'-'.$blockId.'### Start -->'] = '<div class="'.$class.'" id="'.$format.'-'.$contentid.'#'.$blockId.'">';
                        $this->nullmarks['<!-- ###'.$class.'-'.$blockId.'### Start -->'] = '';
                    }

                    if(!isset($this->marks['<!-- ###'.$class.'### Stop -->']))
                    {
                        $this->marks['<!-- ###'.$class.'### Stop -->'] = '</div>';
                        $this->nullmarks['<!-- ###'.$class.'### Stop -->'] = '';
                    }
            }
        }

        return array(
            'blocks' => $ret,
            'eob' => $eob,
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
function makeEditable() {
    $(".'.$class.'").editable("?cmd=save&renderer='.$format.'", {
        indicator : "<img src=\'###plugin_path###libs/jquery_jeditable-master/img/indicator.gif\'>",
        loadurl   : "?cmd=load&renderer='.$format.'",
        type      : "'.$type.'",
        submit    : "OK",
        cancel    : "Cancel",
        tooltip   : "Click to edit...",
        ajaxoptions : {
            replace : "with"
        }
    });
};

$(document).ready(function(){
    makeEditable();
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

    private function crossfoot ( $digits )
    {
        $strDigits = ( string ) $digits;
        for( $intCrossfoot = $i = 0; $i < strlen ( $strDigits ); $i++ ) {
            $intCrossfoot += $strDigits{$i};
        }

        return $intCrossfoot;
    }
}