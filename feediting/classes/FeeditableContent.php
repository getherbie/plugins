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
    
    public function __construct(FeeditingPlugin $plugin, $format, $segmentid = null, $eob = null)
    {
        $this->plugin = $plugin;
        $this->format = $format;
        $this->pluginConfig = $this->plugin->getConfig();

        if($segmentid) $this->segmentid = $segmentid;
        if($eob) $this->eob = $eob;
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

    public function getFormat(){
        return $this->format;
    }

    public function getEob(){
        return $this->eob;
    }

    /**
     * @param $content
     * @param $uid
     * @return array() numbered paragraphs
     */
    private function identifyMarkdownBlocks( $content, $dimensionOffset = 0 )
    {
        $ret        = [];
        $eol        = PHP_EOL;
        $eob        = $this->eob;
        $class      = $this->pluginConfig['editable_prefix'].$this->format.'-'.$this->segmentid;
        $openBlock  = true;
        $blockId    = 0;

        $this->plugin->defineLineFeed($this->getFormat(), '<!--eol-->');
        $this->plugin->setEditablesJsConfig( $this->segmentid, $this->format, $class );

        $lines = explode($eol, $content);
        foreach($lines as $ctr => $line)
        {
            // index + 100 so we have enough "space" to create new blocks "on-the-fly" when editing the page
            $lineno = $ctr * $this->blockDimension + $dimensionOffset;

//            // respect content-segments (TODO: do we still need this?)
//            if(preg_match('/--- (.*) ---/', $line)) $this->segmentid++;
            $lineno = $lineno + $this->segmentid;

            switch($line){

                // don't edit bootstrap-markdown...
                case "----":
                case "-- end --":
                    // ...and blank lines
                case "":
                    $ret[$lineno] = ( $ctr == count($lines)-1 ) ? $line : $line.$eol;
                    if($blockId)
                    {
                        $ret[$blockId] .= ($this->segmentid === false) ? '' : $this->plugin->setEditableTag($blockId, $this->segmentid, $class, 'stop', MARKDOWN_EOL);
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
                            $ret[$blockId] .= ($this->segmentid === false) ? '' : $this->plugin->setEditableTag($blockId, $this->segmentid, $class, 'stop', MARKDOWN_EOL);
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
                            $ret[$blockId] .= ($this->segmentid === false) ? '' : $this->plugin->setEditableTag($blockId, $this->segmentid, $class, 'stop', MARKDOWN_EOL);
                            $blockId = 0;
                            $openBlock = true;
                        }
                    }

                    if($openBlock)
                    {
                        $blockId = $lineno;
                        $ret[$blockId] = ($this->segmentid === false) ? '' : $this->plugin->setEditableTag($blockId, $this->segmentid, $class, 'start', MARKDOWN_EOL);
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
} 