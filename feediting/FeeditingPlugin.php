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

use herbie\plugin\feediting\classes\FeeditableContent;
use Twig_Loader_String;

class FeeditingPlugin extends \Herbie\Plugin
{
    protected $config = [];

    protected $authenticated = false;

    protected $editableSegments = [];

    protected $replace_pairs = [];

    protected $remove_pairs = [];

    public function __construct(\Herbie\Application $app)
    {
        parent::__construct($app);

        // TODO: Implement some kind of authentication!
        $this->authenticated = true;

        // set defaults
        $this->config['contentBlockDimension'] = 100;
        $this->config['contentSegment_WrapperPrefix'] = 'placeholder-';
        $this->config['editable_prefix'] = 'editable_';
    }

    public function __call($funcname, $args)
    {
        if($this->authenticated === true){
            switch(count($args)){
                case 5:
                    return $this->{$funcname}($args[0], $args[1], $args[2], $args[3], $args[4]);
                case 4:
                    return $this->{$funcname}($args[0], $args[1], $args[2], $args[3]);
                case 3:
                    return $this->{$funcname}($args[0], $args[1], $args[2]);
                case 2:
                    return $this->{$funcname}($args[0], $args[1]);
                case 1:
                default:
                    return $this->{$funcname}($args[0]);
            }
        } else {
            return;
        }
    }

    // fetch markdown-contents for jeditable
    protected function onPageLoaded(\Herbie\Event $event )
    {
        // Disable Caching while editing
        $this->app['twig']->environment->setCache(false);

        $_alias = $this->app['alias'];

        $_path = $_alias->get($this->app['menu']->getItem($this->app['route'])->getPath());
        $_page = $event->offsetGet('page');
        $_page->setLoader(new \Herbie\Loader\PageLoader($_alias));
        $_page->load($this->app['urlMatcher']->match($this->app['route'])->getPath());

        $_segments = $_page->getSegments();
        $_content = array();
        $_cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';

        foreach($_segments as $segmentid => $content)
        {
            if(trim($content)=='')
            {
                $content = PHP_EOL.'Click to edit'.PHP_EOL;
            }
            else
            {
                $this->editableSegments[$segmentid] = new FeeditableContent($this, $_page->format);
                $this->editableSegments[$segmentid]->setContent($content);

                $_segments[$segmentid]  = implode( $this->editableSegments[$segmentid]->getEob(),  $this->editableSegments[$segmentid]->getContent() );
                $_content[$segmentid]   = array(
                    'blocks' => $this->editableSegments[$segmentid]->getContent(),
                    'format' => $this->editableSegments[$segmentid]->getFormat(),
                    'eob'    => $this->editableSegments[$segmentid]->getEob()
                );
            }
        };

        switch($_cmd)
        {
            case 'upload':
                if($_FILES){
                    $uploaddir = dirname($_path);
                    $uploadfile = $uploaddir . DS. basename($_FILES['attachment']['name']['file']);
                    if (move_uploaded_file($_FILES['attachment']['tmp_name']['file'], $uploadfile)) {
                      $sirtrevor = '{ "path": "'.$uploadfile.'"}';
                      die($sirtrevor);
                    }
                }
                die();

            case 'save':
                if(
                    $_POST
//                    && $_POST['value'] // prevents removing whole blocks!
                    && $_POST['id']
                ) {

                    list($contenturi, $elemid)      = explode('#', str_replace($this->config['editable_prefix'], '', $_POST['id']));
                    list($contenttype, $contentkey) = explode('-', $contenturi);
                    $currsegmentid                  = $elemid % $this->config['contentBlockDimension'];

                    // read page's header
                    $fh = fopen($_path, 'r');
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
                        // sir-trevor-js delivers json
                        $debug = json_decode($_POST['value']);

                        // TODO: Sanitize input, store only valid $contenttype!
                        $_content[$currsegmentid]['blocks'][$elemid] = (string) $_POST['value'].$_content[$currsegmentid]['eob'];

                        die();

                        $fh = fopen($_path, 'w');
                        fputs($fh, $fheader);
                        foreach($_content as $fsegment => $fcontent){
                            if( $fsegment > 0 ) {
                                fputs($fh, "--- {$fsegment} ---".PHP_EOL);
                            }
                            $modified = $this->renderRawContent(implode($fcontent['blocks']), $contenttype, true );
                            fputs($fh, $modified);
                        }
                        fclose($fh);
                    }

                    // reload page after saving
                    $_page->load($this->app['urlMatcher']->match($this->app['route'])->getPath());
                    $_segments  = $_page->getSegments();

                    // "blockify" reloaded content
                    $jeditable_contents = $this->setContentBlocks($contenttype, $_segments[$currsegmentid], $currsegmentid);

                    // 'placeholder' must match the actual segment-wrapper! ( see HerbieExtension::functionContent() )
                    $jeditable_segment =
                        // open wrap
                        $this->setEditableTag($currsegmentid, $currsegmentid, $placeholder=$this->config['contentSegment_WrapperPrefix'].$currsegmentid, 'wrap')
                        // wrapped segment
                        .implode($jeditable_contents['eob'], $jeditable_contents['blocks'])
                        // close wrap
                        .$this->setEditableTag($currsegmentid, $currsegmentid, $placeholder=$this->config['contentSegment_WrapperPrefix'].$currsegmentid, 'wrap')
                    ;

                    // render jeditable contents
                    $_page->setSegments(array(
                        $currsegmentid => $this->renderEditableContent($jeditable_segment, $contenttype)
                    ));
                    die($this->app->renderContentSegment($currsegmentid));
                }
                break;

            default:

                foreach($_segments as $id => $_segment){
                    $_segments[$id] = $this->renderEditableContent($_segment, 'markdown');
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

        $this->replace_pairs['<body>'] =
            '<link rel="stylesheet" href="'.$_plugin_path.'libs/sir-trevor-js/sir-trevor-icons.css" type="text/css" media="screen" title="no title" charset="utf-8">'
            .'<link rel="stylesheet" href="'.$_plugin_path.'libs/sir-trevor-js/sir-trevor.css" type="text/css" media="screen" title="no title" charset="utf-8">'
            .'<body>';

        $this->replace_pairs['</body>'] =
            '<script src="'.$_plugin_path.'libs/jquery_jeditable-master/jquery.jeditable.js" type="text/javascript" charset="utf-8"></script>'
            .'<script src="'.$_plugin_path.'libs/underscore/underscore.js" type="text/javascript" charset="utf-8"></script>'
            .'<script src="'.$_plugin_path.'libs/Eventable/eventable.js" type="text/javascript" charset="utf-8"></script>'
            .'<script src="'.$_plugin_path.'libs/sir-trevor-js/sir-trevor.js" type="text/javascript" charset="utf-8"></script>'
            .'<script src="'.$_plugin_path.'libs/sir-trevor-js/locales/de.js" type="text/javascript" charset="utf-8"></script>'
            .'<script type="text/javascript" charset="utf-8">'
            .$this->getEditablesJsConfig($_plugin_path)
            .'</script>'
            .'</body>';

        $event->offsetSet('response', $_response->setContent(
            strtr($_response->getContent(), $this->replace_pairs)
        ));
    }

    private function setEditablesJsConfig( $containerId = 0, $format, $class, $containerSelector='.', $type='sirtrevor' )
    {
        return;
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

    private function setEditableTag( $contentUid, $containerUid=0, $contentClass, $mode='auto', $eol = PHP_EOL)
    {
        $class = $contentClass;
        $id    = $contentClass.'#'.$contentUid;

        switch($mode){

            case 'start':
                $mark = '<!-- ###'.$id.'### Start -->';
                $this->replace_pairs[$mark] = '{"type":"text","data":{"text":"';
                $this->remove_pairs[$mark] = '';
                return $eol.$mark.$eol;
            break;

            case 'stop':
                $mark = '<!-- ###'.$class.'### Stop -->';
                $this->replace_pairs[$mark] = '"}},';
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
                    $this->replace_pairs[$startmark] = '{"type":"text","data":{"text":"';
                    $this->remove_pairs[$startmark] = '';
                    return $eol.$startmark.$eol;
                }
                elseif(!isset($this->replace_pairs[$stopmark]))
                {
                    $this->replace_pairs[$stopmark] = '"}},';
                    $this->remove_pairs[$stopmark] = '';
                    return $eol.$stopmark.$eol;
                }
        }
    }
    
    private function getEditablesJsConfig( $pluginPath )
    {
        return
'
      window.editor = new SirTrevor.Editor({
        el: $(".sir-trevor"),
        blockTypes: [
          "Text",
          "Heading",
          "List",
          "Quote",
          "Image",
          "Video",
          "Tweet"
        ],
        defaultType: "Text"
      });
      SirTrevor.setDefaults({
        uploadUrl: "/?cmd=upload"
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
    private function renderEditableContent( $content, $format )
    {
//        $herbieLoader = $this->app['twig']->environment->getLoader();
//        $this->app['twig']->environment->setLoader(new Twig_Loader_String());
//        $twigged = $this->app['twig']->environment->render(strtr($content, array( constant(strtoupper($format).'_EOL') => PHP_EOL )));
//        $this->app['twig']->environment->setLoader($herbieLoader);

//        $formatter = \Herbie\Formatter\FormatterFactory::create($format);
//        $ret = strtr($formatter->transform($twigged), $this->replace_pairs);
        $ret = strtr($content, $this->replace_pairs);
        $ret = strtr($content, array(PHP_EOL => ''));

        $ret = '<form><textarea class="sir-trevor">{"data":['.$ret.'{}]}</textarea><input type="submit" name="cmd" value="save" class="btn" /></form>';

        return $ret;
    }

    private function defineLineFeed($format, $eol)
    {
        $FORMAT_EOL = strtoupper($format).'_EOL';
        // used for saving
        if(!defined($FORMAT_EOL)) define($FORMAT_EOL, $eol);
        // used in in-page-editor
        if(!defined('EDITABLE_'.$FORMAT_EOL)) define('EDITABLE_'.$FORMAT_EOL, $eol);

        $this->replace_pairs[$eol] = '';
        $this->remove_pairs[$eol] = '';
    }

    public function getConfig(){
        return $this->config;
    }
}