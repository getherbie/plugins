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

    private $app = null;

    private $authenticated = false;

    private $replace_pairs = [];

    private $remove_pairs = [];

    public function __construct()
    {
        // TODO: Implement some kind of authentication!
        $this->authenticated = true;

        // set defaults
        $this->config['contentBlockDimension'] = 100;
        $this->config['contentSegment_WrapperPrefix'] = 'placeholder-';
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

//                var_dump($_content[$contentkey]['blocks']);

                // move pointer to the requested element
                while (
                    key($_content[$contentkey]['blocks']) != $elemid
                    && current($_content[$contentkey]['blocks']) !== false
                ){
                    $requestedBlock = next($_content[$contentkey]['blocks']);
                }

                die(trim($this->renderRawContent($requestedBlock, $contenttype)));

            case 'save':
                if(
                    $_POST
//                    && $_POST['value'] // prevents removing whole blocks!
                    && $_POST['id']
                ) {

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

                    // save modified contents
                    if( isset($_content[$currsegmentid]['blocks'][$elemid]))
                    {
                        // TODO: Sanitize input, store only valid $contenttype!
                        $_content[$currsegmentid]['blocks'][$elemid] = (string) $_POST['value'].$_content[$currsegmentid]['eob'];

                        $fh = fopen($_page->getPath(), 'w');
                        fputs($fh, $fheader);
                        foreach($_content as $fsegment => $fcontent){
                            if( $fsegment > 0 ) {
                                fputs($fh, "--- {$fsegment} ---".PHP_EOL);
                            }
//                            $modified = strtr(implode($fcontent['blocks']), $this->remove_pairs);
                            $modified = $this->renderRawContent(implode($fcontent['blocks']), $contenttype, true );
                            fputs($fh, $modified);
                        }
                        fclose($fh);
                    }

                    // reload page after saving
                    $menuItem   = $this->app['urlMatcher']->match($this->app['route']);
                    $pageLoader = new \Herbie\Loader\PageLoader();
                    $_page      = $pageLoader->load($menuItem->getPath());
                    $_segments  = $_page->getSegments();

                    // "blockify" reloaded content
                    $jeditable_contents = $this->getContentBlocks($contenttype, $_segments[$currsegmentid], $currsegmentid);

                    // 'placeholder' must match the actual segment-wrapper! ( see HerbieExtension::functionContent() )
                    $jeditable_segment =
                        // open wrap
                        $this->setJeditableTag($currsegmentid, $currsegmentid, $placeholder=$this->config['contentSegment_WrapperPrefix'].$currsegmentid, 'wrap')
                        // wrapped segment
                        .implode($jeditable_contents['eob'], $jeditable_contents['blocks'])
                        // close wrap
                        .$this->setJeditableTag($currsegmentid, $currsegmentid, $placeholder=$this->config['contentSegment_WrapperPrefix'].$currsegmentid, 'wrap')
                    ;

                    // render jeditable contents
                    die($this->renderJeditableContent($jeditable_segment, $contenttype));
                }
                break;

            default:
                foreach($_segments as $id => $_segment){
                    $_segments[$id] = $this->renderJeditableContent($_segment, 'markdown');
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

        $this->replace_pairs['</body>'] =
            '<script src="'.$_plugin_path.'libs/jquery_jeditable-master/jquery.jeditable.js" type="text/javascript" charset="utf-8"></script>'
            .'<script type="text/javascript" charset="utf-8">'
            .$this->getJSEditablesConfig($_plugin_path)
            .'</script>'
            .'</body>';

        $event->offsetSet('response', $_response->setContent(
            strtr($_response->getContent(), $this->replace_pairs)
        ));
    }

    /**
     * @param string $content
     * @param int $uid
     * @return array('blocks', 'eop', 'format')
     */
    private function getContentBlocks($format, $content, $contentid, $contentBlockDimension = 100, $dimensionOffset = 0)
    {
        $ret = [];

            // currently only (twitter-bootstrap)markdown supported!
        $ret = $this->{'identify'.ucfirst($format).'Blocks'}($content, $contentid, $contentBlockDimension, $dimensionOffset);

            // strip empty blocks
        $lastBlockUid = 0;
        $beforeLastBlockUid = 0;
        foreach($ret['blocks'] as $blockUid => $blockContents)
        {
            if(
                $lastBlockUid
                && $beforeLastBlockUid
                && $blockContents == PHP_EOL
                && $stripped[$lastBlockUid] == PHP_EOL
//                && $stripped[$beforeLastBlockUid] == PHP_EOL
            )
                continue;
            else
                $stripped[$blockUid] = $blockContents;

            $beforeLastBlockUid = $lastBlockUid;
            $lastBlockUid       = $blockUid;
        }
        $ret['blocks'] = $stripped;

        return $ret;
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
        $eol    = PHP_EOL;
        $eob    = PHP_EOL; // end of block
        $class  = $this->config['jeditable_prefix'].$format.'-'.$contentid;
        $openBlock = true;
        $blockId = 0;

        $this->defineLineFeed($format, '<!--eol-->');
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
                        $ret[$blockId] .= ($contentid === false) ? '' : $this->setJeditableTag($blockId, $contentid, $class, 'stop', MARKDOWN_EOL);
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
                            $ret[$blockId] .= ($contentid === false) ? '' : $this->setJeditableTag($blockId, $contentid, $class, 'stop', MARKDOWN_EOL);
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
                            $ret[$blockId] .= ($contentid === false) ? '' : $this->setJeditableTag($blockId, $contentid, $class, 'stop', MARKDOWN_EOL);
                            $blockId = 0;
                            $openBlock = true;
                        }
                    }

                    if($openBlock)
                    {
                        $blockId = $lineno;
                        $ret[$blockId] = ($contentid === false) ? '' : $this->setJeditableTag($blockId, $contentid, $class, 'start', MARKDOWN_EOL);
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

    private function setJSEditableConfig( $containerId = 0, $format, $class, $containerSelector='.', $type='textarea' )
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
            container : "'.$containerSelector.$this->config['contentSegment_WrapperPrefix'].$containerId.'"
        }
    });
'
        );
    }

    private function setJeditableTag( $contentUid, $containerUid=0, $contentClass, $mode='auto', $eol = PHP_EOL)
    {
        $class = $contentClass;
        $id    = $contentClass.'#'.$contentUid;

        switch($mode){

            case 'start':
                $mark = '<!-- ###'.$id.'### Start -->';
                $this->replace_pairs[$mark] = '<div class="'.$class.'" id="'.$id.'">';
                $this->remove_pairs[$mark] = '';
                return $eol.$mark.$eol;
            break;

            case 'stop':
                $mark = '<!-- ###'.$class.'### Stop -->';
                $this->replace_pairs[$mark] = '</div>';
                $this->remove_pairs[$mark] = '';
                return $eol.$mark.$eol;
            break;

            case 'wrap':
                $id     = $contentClass;
                $class  = $id;
            case 'auto':
            default:
                $startmark = '<!-- ###'.$id.'### Start -->';
                $stopmark  = '<!-- ###'.$class.'### Stop -->';
                if(!isset($this->replace_pairs[$startmark]))
                {
                    $this->replace_pairs[$startmark] = '<div class="'.$class.'" id="'.$id.'">';
                    $this->remove_pairs[$startmark] = '';
                    return $eol.$startmark.$eol;
                }
                elseif(!isset($this->replace_pairs[$stopmark]))
                {
                    $this->replace_pairs[$stopmark] = '</div>';
                    $this->remove_pairs[$stopmark] = '';
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

    /**
     * @param string $content
     * @param string $format, eg. 'markdown'
     * @return string
     */
    private function renderRawContent( $content, $format, $stripLF = false )
    {
        $ret = strtr($content, array( constant(strtoupper($format).'_EOL') => $stripLF ? '' : PHP_EOL ));
        $ret = strtr($ret, $this->remove_pairs);
        return $ret;
    }

    /**
     * @param string $content
     * @param string $format, eg. 'markdown'
     * @return string
     */
    private function renderJeditableContent( $content, $format )
    {
        $twigged = $this->app['twig']->render(strtr($content, array( constant(strtoupper($format).'_EOL') => PHP_EOL )));

        $formatter = \Herbie\Formatter\FormatterFactory::create($format);
        $ret = strtr($formatter->transform($twigged), $this->replace_pairs);

        return $ret;
    }

    private function defineLineFeed($format, $eol)
    {
        $FORMAT_EOL = strtoupper($format).'_EOL';
        // used for saving
        if(!defined($FORMAT_EOL)) define($FORMAT_EOL, $eol);
        // used in in-page-editor
        if(!defined('EDITABLE_'.$FORMAT_EOL)) define('EDITABLE_'.$FORMAT_EOL, $eol);

        $this->remove_pairs[$eol] = '';
    }
}