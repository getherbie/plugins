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
    private $config = [];

    private $authenticated = false;

    private $app = null;

    public function __construct()
    {
        // TODO: Implement some kind of authentication!
        $this->authenticated = true;

        // set defaults
        $this->config['contentBlockDimension'] = 100;
        $this->config['jeditable_prefix'] = 'editable_';
    }

    public function __call($funcname, $args)
    {
        if($this->authenticated === true){
            return $this->{$funcname}($args[0]);
        } else {
            return;
        }
    }

    // fetch markdown-contents for jeditable
    protected function onPageLoaded(\Herbie\Event $event )
    {
        $this->app = $event->offsetGet('app');

        $_page = $event->offsetGet('page');
        $_segments = $_page->getSegments();
        $_content = array();

        foreach($_segments as $segmentid => $content)
        {
            $contentblocks          = $this->getContentBlocks($_page->getFormat(), $content, $segmentid);
            $_segments[$segmentid]  = implode($contentblocks['eob'], $contentblocks['blocks']);
            $_content[$segmentid]   = array(
                'blocks' => $contentblocks['blocks'],
                'format' => $contentblocks['format'],
                'eob' => $contentblocks['eob'] // "end of block"
            );
            unset($contentblocks);
        };

        switch(@$_REQUEST['cmd'])
        {
            case 'load':
                list($contenturi, $elemid) = explode('#', $_REQUEST['id']);
                list($contenttype, $contentkey) = explode('-', $contenturi);

                // move pointer to the requested element
                while (
                    key($_content[$contentkey]['blocks']) != $elemid
                    && current($_content[$contentkey]['blocks']) !== false
                ){
                    $request = next($_content[$contentkey]['blocks']);
                }

                die(trim(strtr(
                    $request,
                    $this->nullmarks
                )));

            case 'save':
                if($_POST && $_POST['value'] && $_POST['id']) {

                    list($contenturi, $elemid)      = explode('#', str_replace($this->config['jeditable_prefix'], '', $_POST['id']));
                    list($contenttype, $contentkey) = explode('-', $contenturi);
                    $currsegmentid                  = $elemid % $this->config['contentBlockDimension'];

                    // read page's header
                    $fh = fopen($_page->getPath(), 'r');
                    if($fh) {
                        $currline = 0;
                        $fheader = '';
                        $fbody = '';
                        $fpart = 'header';
                        while( ($buffer = fgets($fh))!==false )
                        {
                            ${'f'.$fpart} .= $buffer;
                            $currline++;
                            if( $currline > 1 && strpos($buffer, '---')!==false ){
                                $fpart = 'body';
                                break; // don't break, if full body is needed!
                            }
                        }
                    }
                    fclose($fh);

//                    $fcontent = $this->getContentBlocks($contenttype, $_segments = $_page->getSegment($currsegmentid), $currsegmentid);

                    // save modified contents
                    if( isset($_content[$currsegmentid]['blocks'][$elemid]))
                    {
                        // TODO: Sanitize input, store only valid $contenttype!
                        $_content[$currsegmentid]['blocks'][$elemid] = (string) $_POST['value'].$_content[$currsegmentid]['eob'];

                        $fh = fopen($_page->getPath(), 'w');
                        fputs($fh, $fheader);
                        foreach($_content as $fsegment => $fcontent){
                            if( $fsegment > 0 ) {
                                fputs($fh, PHP_EOL."--- {$fsegment} ---".PHP_EOL);
                            }
                            $modified = strtr(implode($fcontent['blocks']), $this->nullmarks);
                            fputs($fh, $modified);
                        }
                        fclose($fh);
                    }

                    // reload page after saving
                    $menuItem   = $this->app['urlMatcher']->match($this->app['route']);
                    $pageLoader = new \Herbie\Loader\PageLoader();
                    $_page      = $pageLoader->load($menuItem->getPath());
                    $_segments  = $_page->getSegments();

                    // "blockify" submitted content
                    $jeditable_contents = $this->getContentBlocks($contenttype, $_segments[$currsegmentid], $currsegmentid);

                    // 'placeholder' must match the actual segment-wrapper! ( see HerbieExtension::functionContent() )
                    $jeditable_segment =
                        // open wrap
                        $this->setJeditableTag($currsegmentid, $currsegmentid, 'placeholder', 'wrap')
                        // wrapped segment
                        .implode($jeditable_contents['eob'], $jeditable_contents['blocks'])
                        // close wrap
                        .$this->setJeditableTag($currsegmentid, $currsegmentid, 'placeholder', 'wrap')
                    ;

                    // render jeditable contents
                    die(strtr(
                        $this->renderContent(strtr($jeditable_segment, array('<!--eol-->'=>PHP_EOL)), $contenttype),
                        $this->marks
                    ));
                }
                break;

            default:
                foreach($_segments as $id => $_segment){
                    $_segments[$id] = $this->renderContent(strtr($_segment, array('<!--eol-->'=>PHP_EOL)), 'markdown');
                }
                $_page->setSegments($_segments);
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

        $event->offsetSet('response', $_response->setContent(
            strtr($_response->getContent(), $this->marks)
        ));
    }

    /**
     * @param string $content
     * @param int $uid
     * @return array('blocks', 'eop', 'format')
     */
    private function getContentBlocks($format, $content, $contentid, $contentBlockDimension = 100, $dimensionOffset = 0)
    {
            // currently only (twitter-bootstrap)markdown supported
        return $this->{'identify'.ucfirst($format).'Blocks'}($content, $contentid, $contentBlockDimension, $dimensionOffset);
    }

    /**
     * @param $content
     * @param $uid
     * @return array() numbered paragraphs
     */
    private function identifyMarkdownBlocks( $content, $contentid, $contentBlockDimension = 100, $dimensionOffset = 0 )
    {
        $ret    = [];
        $format = 'markdown';
        $eol    = "\n";
        $eob    = "\n"; // end of block
        $class  = $this->config['jeditable_prefix'].$format.'-'.$contentid;
        $openBlock = true;
        $blockId = 0;

        $this->setJSEditableConfig( $contentid, $format, $class );

        $lines = explode($eol, $content);
        foreach($lines as $ctr => $line)
        {
                // index + 100 so we have enough "space" to create new blocks "on-the-fly" when editing the page
            $lineno = $ctr * $contentBlockDimension + $dimensionOffset;

                // respect content-segments
            if(preg_match('/--- (.*) ---/', $line)) $contentid++;
            $lineno = $lineno + $contentid;

            switch($line){

                    // don't edit bootstrap-markdown...
                case "----":
                case "-- end --":
                    // ...and blank lines
                case "":
                    $ret[$lineno] = ( $ctr == count($lines)-1 ) ? $line : $line.$eol;
                    if($blockId)
                    {
                        $ret[$blockId] .= ($contentid === false) ? '' : $this->setJeditableTag($blockId, $contentid, $class, 'stop');
                        $blockId = 0;
                        $openBlock = true;
                    }
                    break;

                default:

                    // don't edit bootstrap-markdown (cont.):
                    // eg content like "-- row n1,n2,n3,..,n12 --"
                    if(substr($line, 0, strlen('-- row ')) == '-- row ')
                    {
                        $ret[$lineno] = $line.$eol;
                        if($blockId)
                        {
                            $ret[$blockId] .= ($contentid === false) ? '' : $this->setJeditableTag($blockId, $contentid, $class, 'stop');
                            $blockId = 0;
                            $openBlock = true;
                        }
                        continue;
                    }

                    // group some elements in their own block, eg header:
                    if(substr($line, 0, strlen('#')) == '#')
                    {
                        $ret[$lineno] = $line.$eol;
                        if($blockId)
                        {
                            $ret[$blockId] .= ($contentid === false) ? '' : $this->setJeditableTag($blockId, $contentid, $class, 'stop');
                            $blockId = 0;
                            $openBlock = true;
                        }
                    }

                    if($openBlock)
                    {
                        $blockId = $lineno;
                        $ret[$blockId] = ($contentid === false) ? '' : $this->setJeditableTag($blockId, $contentid, $class, 'start');
                        $ret[$blockId] .= $line.$eol;
                        $openBlock = false;
                    }
                    else
                    {
                        $ret[$blockId] .= $line.$eol;
                    }
            }
        }

        return array(
            'blocks' => $ret,
            'eob' => $eob,
            'format' => $format
        );
    }

    private function setJSEditableConfig( $containerId = 0, $format, $class, $type='textarea' )
    {
        $this->config['editables'][$format.'-'.$containerId] = array(

            'identifier' => $class,
            'config'     => '
    $(".'.$class.'").editable("?cmd=save&renderer='.$format.'", {
        indicator : "<img src=\'###plugin_path###libs/jquery_jeditable-master/img/indicator.gif\'>",
        loadurl   : "?cmd=load&renderer='.$format.'",
        type      : "'.$type.'",
        submit    : "OK",
        cancel    : "Cancel",
        tooltip   : "Click to edit...",
        ajaxoptions : {
            replace : "with",
            segmentid : "placeholder-'.$containerId.'"
        }
    });
'
        );
    }

    private function setJeditableTag( $contentUid, $containerUid=0, $contentClass, $mode='auto', $eol = "\n")
    {
        $class = $contentClass;
        $id    = $contentClass.'#'.$contentUid;

        $this->marks['<!--eol-->'] = '<!--eol-->';
        $this->nullmarks['<!--eol-->'] = '';
        $eol = '<!--eol-->';

        switch($mode){

            case 'start':
                $mark = '<!-- ###'.$id.'### Start -->';
                $this->marks[$mark] = '<div class="'.$class.'" id="'.$id.'">';
                $this->nullmarks[$mark] = '';
                return $eol.$mark.$eol;
            break;

            case 'stop':
                $mark = '<!-- ###'.$class.'### Stop -->';
                $this->marks[$mark] = '</div>';
                $this->nullmarks[$mark] = '';
                return $eol.$mark.$eol;
            break;

            case 'wrap':
                $id     = $contentClass.'-'.$containerUid;
                $class  = $id;
            case 'auto':
            default:
                $startmark = '<!-- ###'.$id.'### Start -->';
                $stopmark  = '<!-- ###'.$class.'### Stop -->';
                if(!isset($this->marks[$startmark]))
                {
                    $this->marks[$startmark] = '<div class="'.$class.'" id="'.$id.'">';
                    $this->nullmarks[$startmark] = '';
                    return $eol.$startmark.$eol;
                }
                elseif(!isset($this->marks[$stopmark]))
                {
                    $this->marks[$stopmark] = '</div>';
                    $this->nullmarks[$stopmark] = '';
                    return $eol.$stopmark.$eol;
                }
        }
    }
    
    private function getJSEditablesConfig( $pluginPath )
    {
        $ret = [];

        if(!isset($this->config['editables'])) return;
        foreach($this->config['editables'] as $type){
            $ret[] = strtr($type['config'], array(
                '###plugin_path###' => $pluginPath
            ));
        }

        return
'function makeJeditable() {
'.implode($ret).'
};

$(document).ready(function(){
    makeJeditable();
});
';
    }
        
    // store changed markdown

    /**
     * @param string $content
     * @param string $format, eg. 'markdown'
     * @return string
     */
    private function renderContent( $content, $format )
    {
        $twigged = $this->app['twig']->render($content);

        $formatter = \Herbie\Formatter\FormatterFactory::create($format);
        return $formatter->transform($twigged);
    }
}