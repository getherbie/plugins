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

//use herbie\plugin\feediting\classes;
use Twig_Loader_String;

class FeeditingPlugin extends \Herbie\Plugin
{
    protected $config = [];

    protected $authenticated = false;

    protected $editableSegments = [];

    protected $replace_pairs = [];

    protected $remove_pairs = [];

    protected $editableContent = [];

    private $editor = 'Feeditable';

    public function __construct(\Herbie\Application $app)
    {
        parent::__construct($app);

        // TODO: Implement some kind of authentication!
        $this->authenticated = true;

        // set defaults
        $this->config['contentSegment_WrapperPrefix'] = 'placeholder-';
        $this->config['editable_prefix'] = 'editable_';
        $this->config['contentBlockDimension'] = 100;

        // set editor
        $this->editor = 'SirTrevor';

    }

    // fetch markdown-contents for jeditable
    protected function onPageLoaded(\Herbie\Event $event )
    {
        // Disable Caching while editing
        $this->app['twig']->environment->setCache(false);

        $this->alias = $this->app['alias'];

        $this->path = $this->alias->get($this->app['menu']->getItem($this->app['route'])->getPath());

        $this->page = $event->offsetGet('page');
        $this->page->setLoader(new \Herbie\Loader\PageLoader($this->alias));
        $this->page->load($this->app['urlMatcher']->match($this->app['route'])->getPath());

        $this->segments = $this->page->getSegments();

        foreach($this->segments as $segmentid => $_staticContent)
        {
            $contentEditor = "herbie\\plugin\\feediting\\classes\\{$this->editor}Content";
            $this->editableContent[$segmentid] = new $contentEditor($this, $this->page->format, $segmentid);
            
            if(trim($_staticContent)=='')
            {
                $_staticContent = PHP_EOL.'Click to edit'.PHP_EOL;
            }
            else
            {
                $this->editableContent[$segmentid]->setContent($_staticContent);
                $this->segments[$segmentid] = $this->editableContent[$segmentid]->getSegment();
            }
        };

        $_cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';
        switch($_cmd)
        {
            case 'save':
                if( isset($_POST['id']) )
                {
                    // get $elemid, $currsegmentid(!) and $contenttype
                    extract($this->editableContent[0]->decodeEditableId($_POST['id']));

                    if( $this->editableContent[$currsegmentid]->getContentBlockById($elemid) )
                    {
                        $this->editableContent[$currsegmentid]->setContentBlockById($elemid, (string) $_POST[$elemid]);

                        $fheader = $this->getContentfileHeader();
                        $fh = fopen($this->path, 'w');
                        fputs($fh, $fheader);
                        foreach($this->segments as $segmentid => $_staticContent){
                            if( $segmentid > 0 ) {
                                fputs($fh, "--- {$segmentid} ---".PHP_EOL);
                            }
                            $_modifiedContent[$segmentid] = $this->renderRawContent($this->editableContent[$segmentid]->getSegment(false), $this->editableContent[$segmentid]->getFormat(), true );
                            fputs($fh, $_modifiedContent[$segmentid]);
                        }
                        fclose($fh);
                    }

                    // reload page after saving
                    $this->page->load($this->app['urlMatcher']->match($this->app['route'])->getPath());

//                    // 'placeholder' must match the actual segment-wrapper! ( see HerbieExtension::functionContent() )
                    $editable_segment =
//                      // open wrap
//                      $this->setEditableTag($currsegmentid, $currsegmentid, $placeholder=$this->config['contentSegment_WrapperPrefix'].$currsegmentid, 'wrap').
                        // wrapped segment
                        $this->editableContent[$segmentid]->getSegment().
//                      // close wrap
//                      .$this->setEditableTag($currsegmentid, $currsegmentid, $placeholder=$this->config['contentSegment_WrapperPrefix'].$currsegmentid, 'wrap')
                    '';

                    // render jeditable contents
                    $this->page->setSegments(array(
                        $currsegmentid => $this->renderEditableContent($currsegmentid, $editable_segment, $contenttype)
                    ));
                    die($this->app->renderContentSegment($currsegmentid));
                }
                break;

            case '':

                foreach($this->segments as $id => $_segment){
                    $this->segments[$id] = $this->renderEditableContent($id, $_segment, 'markdown');
                }

                $this->page->setSegments($this->segments);
                break;

            default:
                $this->editableContent->{$_cmd}();
        }
    }

    // call document.ready-function in footer
    protected function onOutputGenerated(\Herbie\Event $event )
    {
        $_app          = $event->offsetGet('app');
        $_response     = $event->offsetGet('response');
        $_plugin_path  = str_replace($_app['webPath'], '', $_app['config']->get('plugins_path')).'/feediting/';

        $this->getEditablesCssConfig($_plugin_path);

        $this->getEditablesJsConfig($_plugin_path);

        $event->offsetSet('response', $_response->setContent(
            strtr($_response->getContent(), $this->replace_pairs)
        ));
    }

    private function includeIntoTag($closingtag=null, $tagOrPath)
    {
        if(empty($closingtag)) return;

        if(!isset($this->replace_pairs[$closingtag]))
            $this->replace_pairs[$closingtag] = $closingtag;

        $debug = substr( $tagOrPath, 0, 1 );
        if($debug == '<'){
            $this->replace_pairs[$closingtag] = $tagOrPath.PHP_EOL.$this->replace_pairs[$closingtag];
            return;
        }

        list($path, $type) = explode('.', $tagOrPath);
        switch($type){
            case 'css':
                $tmpl = '<link rel="stylesheet" href="%s" type="text/css" media="screen" title="no title" charset="utf-8">';
                break;
            case 'js':
                $tmpl = '<script src="%s" type="text/javascript" charset="utf-8"></script>';
                break;
            default:
                return;
        }
        $this->replace_pairs[$closingtag] = sprintf($tmpl, $tagOrPath).PHP_EOL.$this->replace_pairs[$closingtag];
    }

    private function getReplacement($mark){
        return $this->replace_pairs[$mark];
    }

    private function setReplacement($mark, $replacement){
        $this->replace_pairs[$mark] = $replacement;
        $this->remove_pairs[$mark] = '';
    }
    
    private function getEditablesCssConfig( $pluginPath ){
        return $this->editableContent[0]->getEditablesCssConfig( $pluginPath );
    }

    private function getEditablesJsConfig( $pluginPath ){
        return $this->editableContent[0]->getEditablesJsConfig( $pluginPath );
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
    private function renderEditableContent( $contentId, $content, $format, $twigged=false )
    {
        if($twigged)
        {
            $herbieLoader = $this->app['twig']->environment->getLoader();
            $this->app['twig']->environment->setLoader(new Twig_Loader_String());
            $twigged = $this->app['twig']->environment->render(strtr($content, array( constant(strtoupper($format).'_EOL') => PHP_EOL )));
            $this->app['twig']->environment->setLoader($herbieLoader);

            $formatter = \Herbie\Formatter\FormatterFactory::create($format);
            $ret = strtr($formatter->transform($twigged), $this->replace_pairs);
        }
        else
        {
            //$ret = strtr($content, $this->replace_pairs);
            $ret = strtr($content, array(PHP_EOL => ''));
        }

        return $this->editableContent[$contentId]->getEditableContainer($contentId, $ret);
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

    private function getContentfileHeader()
    {
        // read page's header
        $fh = fopen($this->path, 'r');
        if($fh) {
            $currline = 0;
            $fheader = '';
            $fbody = '';
            while( ($buffer = fgets($fh))!==false )
            {
                $fpart = isset($fpart) ? $fpart : 'header';
                ${'f'.$fpart} .= $buffer;
                $currline++;
                if( $currline > 1 && strpos($buffer, '---')!==false ){
                    $fpart = 'body';
                    break; // don't break, if full body is needed!
                }
            }
        }
        fclose($fh);

        return $fheader;
    }

    public function getConfig(){
        return $this->config;
    }

    public function includeIntoHeader($tagOrPath){
        $this->includeIntoTag('</head>', $tagOrPath);
    }

    public function includeAfterBodyStarts($tagOrPath){
        $this->includeIntoTag('<body>', $tagOrPath);
    }

    public function includeBeforeBodyEnds($tagOrPath){
        $this->includeIntoTag('</body>', $tagOrPath);
    }

    public function __get($attrib){
        switch($attrib){
            case 'path':
                return $this->{$attrib};
                break;
            default:
                return false;
        }
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
}