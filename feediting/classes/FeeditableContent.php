<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\feediting\classes;


use herbie\plugin\feediting\FeeditingPlugin;

class FeeditableContent {

    protected $blocks = [];

    protected $format = '';

    protected $plugin;

    protected $segmentid = 0;

    protected $blockDimension = 100;

    protected $pluginConfig = [];

    protected $eob = PHP_EOL;

    protected $openImageBlock = '{"type":"image","data":{"file":{"url":"';

    protected $closeImageBlock = '"}}},';

    protected $openTextBlock = '{"type":"text","data":{"text":"';

    protected $closeTextBlock = '"}},';
    
    public function __construct(FeeditingPlugin &$plugin, $format, $segmentid = null, $eob = null)
    {
        $this->plugin = $plugin;
        $this->format = $format;
        $this->pluginConfig = $this->plugin->getConfig();

        if($segmentid) $this->segmentid = $segmentid;
        if($eob) $this->eob = $eob;
    }

    public function getFormat(){
        return $this->format;
    }

    public function getEob(){
        return $this->eob;
    }

    public function getSegment($eob=true){
        if($eob)
            return implode( $this->getEob(),  $this->getContent() );
        else
            return implode( $this->getContent() );
    }

    /**
     * @param string $content
     * @param int $uid
     * @return array('blocks', 'eop', 'format')
     */
    public function setContent($content)
    {
        // currently only (twitter-bootstrap)markdown supported!
        $this->{'identify'.ucfirst($this->format).'Blocks'}($content);

        // strip empty blocks
        $this->stripEmptyContentblocks();
    }

    public function getContent(){
        return $this->blocks;
    }

    public function getContentBlockById($id){
        return $this->blocks[$id] ? $this->blocks[$id] : false;
    }

    public function setContentBlockById($id, $content){
        if($this->blocks[$id]) {
            // TODO: jason_decode!
            $this->blocks[$id] = $content.$this->eob;

            // Reindex all blocks
            $modified = $this->plugin->renderRawContent(implode($this->getContent()), $this->getFormat(), true );
            $this->setContent($modified);

            return true;
        }
        return false;
    }

    public function encodeEditableId($elemId)
    {
        if(!($this->pluginConfig['editable_prefix'] && $this->format && $this->segmentid))
            return false;
        else
            return $this->pluginConfig['editable_prefix'].$this->format.'-'.$this->segmentid.'#'.$elemId;
    }

    public function decodeEditableId($elemId)
    {
        list($contenturi, $elemid)      = explode('#', str_replace($this->config['editable_prefix'], '', $elemId));
        list($contenttype, $contentkey) = explode('-', $contenturi);
        $currsegmentid                  = $elemid % $this->config['contentBlockDimension'];

        return array(
            'elemid' => $elemId,
            'currsegmentid' => $currsegmentid,
            'contenttype' => $contenttype
        );
    }

    public function getEditablesCssConfig($path=null){
        $this->plugin->includeIntoHeader($path.'libs/sir-trevor-js/sir-trevor-icons.css');
        $this->plugin->includeIntoHeader($path.'libs/sir-trevor-js/sir-trevor.css');
    }

    public function getEditablesJsConfig( $path=null )
    {
        $this->plugin->includeBeforeBodyEnds($path.'libs/jquery_jeditable-master/jquery.jeditable.js');
        $this->plugin->includeBeforeBodyEnds($path.'libs/underscore/underscore.js');
        $this->plugin->includeBeforeBodyEnds($path.'libs/Eventable/eventable.js');
        $this->plugin->includeBeforeBodyEnds($path.'libs/sir-trevor-js/sir-trevor.js');
        $this->plugin->includeBeforeBodyEnds($path.'libs/sir-trevor-js/locales/de.js');
        $this->plugin->includeBeforeBodyEnds(
'<script type="text/javascript" charset="utf-8">'.
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
'
.'</script>'
        );

    }

    public function upload(){
        if($_FILES){
            $uploaddir = dirname($this->plugin->path);
            $uploadfile = $uploaddir . DS. basename($_FILES['attachment']['name']['file']);
            if (move_uploaded_file($_FILES['attachment']['tmp_name']['file'], $uploadfile)) {
                $sirtrevor = '{ "path": "'.$uploadfile.'"}';
                die($sirtrevor);
            }
        }
    }

    private function identifyMarkdownBlocks( $content, $dimensionOffset = 0 )
    {
        $ret        = [];
        $eol        = PHP_EOL;
        $class      = $this->pluginConfig['editable_prefix'].$this->format.'-'.$this->segmentid;
        $openBlock  = true;
        $blockId    = 0;

        $this->plugin->defineLineFeed($this->getFormat(), '<!--eol-->');

        $lines = explode($eol, $content);
        foreach($lines as $ctr => $line)
        {
            // index + 100 so we have enough "space" to create new blocks "on-the-fly" when editing the page
            $lineno = $ctr * $this->blockDimension + $dimensionOffset;

//            // respect content-segments (TODO: do we still need this?)
//            if(preg_match('/--- (.*) ---/', $line)) $this->segmentid++;
            $lineno = $lineno + $this->segmentid;

            switch($line)
            {
                // don't edit bootstrap-markdown...
                case "----":
                case "-- end --":
                    // ...and blank lines
                case "":

                    // close previous block
                    if($blockId)
                    {
                        $ret[$blockId] .= ($this->segmentid === false) ? '' : $this->setEditableTag($blockId, $class, 'stop', MARKDOWN_EOL);
                        $blockId = 0;
                        $openBlock = true;
                    }

                    // continue reading
                    $ret[$lineno] = ( $ctr == count($lines)-1 ) ? $line : $line.$eol;
                    break;

                default:

                    // don't edit bootstrap-markdown (cont.):
                    // eg content like "-- row n1,n2,n3,..,n12 --"
                    if(substr($line, 0, strlen('-- row ')) == '-- row ')
                    {
                        // close previous block
                        if($blockId)
                        {
                            $ret[$blockId] .= ($this->segmentid === false) ? '' : $this->setEditableTag($blockId, $class, 'stop', MARKDOWN_EOL);
                            $blockId = 0;
                            $openBlock = true;
                        }

                        // continue reading
                        $ret[$lineno] = $line.$eol;
                        continue;
                    }

                    // group some elements in their own block, eg header:
                    if(substr($line, 0, strlen('#')) == '#')
                    {
                        // close previous block
                        if($blockId)
                        {
                            $ret[$blockId] .= ($this->segmentid === false) ? '' : $this->setEditableTag($blockId, $class, 'stop', MARKDOWN_EOL);
                            $blockId = 0;
                            $openBlock = true;
                        }

                        // continue reading
                        $ret[$lineno] = $line.$eol;
                        continue;

                    }

                    // group some elements in their own block, eg. SirTrevor-ImageBlock
                    if(substr($line, 0, strlen('![')) == '![')
                    {
                        // close previous block
                        if($blockId)
                        {
                            $ret[$blockId] .= ($this->segmentid === false) ? '' : $this->setEditableTag($blockId, $class, 'stop', MARKDOWN_EOL);
                            $blockId = 0;
                            $openBlock = true;
                        }

                        // just testing
                        $ret[$lineno] = '{"type":"image","data":{"file":{"url":"http://netzweberei.getherbie.localhost/site/pages/Bildschirmfoto.png"}}},';
                        continue;
                    }

                    if($openBlock)
                    {
                        $blockId = $lineno;
                        $ret[$blockId] = ($this->segmentid === false) ? '' : $this->setEditableTag($blockId, $class, 'start', MARKDOWN_EOL);
                        $ret[$blockId] .= $line.$eol;
                        $openBlock = false;
                    }
                    else
                    {
                        $ret[$blockId] .= $line.$eol;
                    }
            }
        }

        $this->blocks = $ret;
    }

    private function stripEmptyContentblocks()
    {
        if(!is_array($this->blocks) || count($this->blocks)==0) return;

        $stripped = [];
        $lastBlockUid = 0;
        $beforeLastBlockUid = 0;
        
        foreach($this->blocks as $blockUid => $blockContents)
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
        $this->blocks = $stripped;
    }

    private function setEditableTag( $contentUid, $contentClass, $mode='auto', $eol = PHP_EOL, $blockType='text')
    {
        $class = $contentClass;
        $id    = $contentClass.'#'.$contentUid;

        switch($blockType)
        {
            case 'text':
            case 'image':
                $openBlock = $this->{'open'.ucfirst($blockType).'Block'};
                $stopBlock = $this->{'close'.ucfirst($blockType).'Block'};;
                break;
            default:
                $openBlock = $this->openTextBlock;
                $stopBlock = $this->stopTextBlock;
        }

        switch($mode){

            case 'start':
                $mark = '<!-- ###'.$id.'### Start -->';
                $this->plugin->setReplacement($mark,$openBlock);
                return $eol.$mark.$eol;
                break;

            case 'stop':
                $mark = '<!-- ###'.$class.'### Stop -->';
                $this->plugin->setReplacement($mark,$stopBlock);
                return $eol.$mark.$eol;
                break;

            case 'wrap':
                $id     = $contentClass;
                $class  = $id;
            case 'auto':
            default:
                $startmark = '<!-- ###'.$id.'### Start -->';
                $stopmark  = '<!-- ###'.$class.'### Stop -->';
                if($this->plugin->getReplacement($startmark)=='')
                {
                    $this->plugin->setReplacement($startmark,$openBlock);
                    return $eol.$startmark.$eol;
                }
                elseif($this->plugin->getReplacement($stopmark)=='')
                {
                    $this->plugin->setReplacement($stopmark,$stopBlock);
                    return $eol.$stopmark.$eol;
                }
        }
    }

} 