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

class SirTrevorContent extends FeeditableContent {

    public $reloadPageAfterSave = true;

    protected $contentBlocks = [
        "headerBlock" => [
            "template" => '{"type":"text","data":{"text":"%s"}},',
            "mdregex" => '/^#/',
            "dataregex" => '/.*/'
        ],
        "imageBlock" => [
            "template" => '{"type":"image","data":{"file":{"url":"%s"}}},',
            "mdregex" => '/^\!\[/',
            "dataregex" => '/\((.*)\)/'
        ],
        "textBlock" => [
            "template" => '{"type":"text","data":{"text":"%s"}},',
            "mdregex" => '/.*/',
            "dataregex" => '/.*/'
        ],
    ];
    protected $contentContainer = '{"data":[%s{}]}';

    public function getEditablesCssConfig($path=null){
        $this->plugin->includeIntoHeader($path.'libs/sir-trevor-js/sir-trevor.css');
        $this->plugin->includeIntoHeader($path.'libs/sir-trevor-js/sir-trevor-icons.css');
    }

    public function getEditablesJsConfig( $path=null )
    {
        $this->plugin->includeAfterBodyStarts('<input type="hidden" name="cmd" value="save">');
        $this->plugin->includeAfterBodyStarts('<form method="post">');
        $this->plugin->includeBeforeBodyEnds('</form>');

        foreach($this->plugin->segments as $segmentid => $segment)
        {
            //$this->plugin->includeAfterBodyStarts('<input type="submit" name="id" value="sirtrevor-'.$segmentid.'">');
            $this->plugin->includeBeforeBodyEnds(
'<script type="text/javascript" charset="utf-8">'.
'
      window.editor'.$segmentid.' = new SirTrevor.Editor({
        el: $(".sirtrevor-'.$segmentid.'"),
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
'.
'</script>'
            );
        }
        $this->plugin->includeBeforeBodyEnds($path.'libs/sir-trevor-js/locales/de.js');
        $this->plugin->includeBeforeBodyEnds($path.'libs/sir-trevor-js/sir-trevor.js');
        $this->plugin->includeBeforeBodyEnds($path.'libs/Eventable/eventable.js');
        $this->plugin->includeBeforeBodyEnds($path.'libs/underscore/underscore.js');
    }

    public function getEditableContainer($contentId, $content){
        return
            '<div class="st-submit"><input type="submit" name="id" value="sirtrevor-'.$contentId.'" class="btn btn-small" ></input></div>'.
            '<textarea name="sirtrevor-'.$contentId.'" class="sirtrevor-'.$contentId.'">'.sprintf($this->contentContainer, $content).'</textarea>'.
            '<div class="st-submit"><input type="submit" name="id" value="sirtrevor-'.$contentId.'" class="btn btn-small"></input></div>';
    }

    public function decodeEditableId($elemId)
    {
        list($contenttype, $currsegmentid) = explode('-', $elemId);

        return array(
            'elemid' => $elemId,
            'currsegmentid' => $currsegmentid,
            'contenttype' => 'sirtrevor'
        );
    }

    public function getSegment($json_encode=true){
        if($json_encode)
            return implode( $this->getEob(),  $this->getContent() );
        else
            return implode( $this->getContent($json_decode=true) );
    }

    public function getContent($json_decode=false){

        if($json_decode){
            $ret = $this->json2array($this->getContentBlockById($this->segmentid));
        } else {
            $ret = $this->blocks;
        }
        return $ret;
    }

    public function getContentBlockById($id){
        return $this->blocks ? sprintf($this->contentContainer, implode($this->blocks)) : null;
    }

    public function setContentBlockById($id, $json){

        if($this->blocks)
        {
            // replace current segment
            $this->blocks = $this->json2array($json);

            // Reindex all blocks
            $modified = $this->plugin->renderRawContent(implode($this->getContent()), $this->getFormat(), true );
            $this->setContent($modified);

            return true;
        }
        return false;
    }

    private function json2array($json){

        $content = json_decode(strtr($json, array(
            '\\n' => '',
            '\\' => '',
        )));
        if(isset($content->data))
        {
            foreach($content->data as $block)
            {
                $_blocks[] = PHP_EOL;
                if(isset($block->type))
                {
                    switch($block->type)
                    {
                        case 'image':
                            $_blocks[] = '!['.basename($block->data->file->url).']('.$block->data->file->url.')'.PHP_EOL;
                            break;
                        case 'text':
                        default:
                            $_blocks[] = strtr($block->data->text, array(PHP_EOL => '')).PHP_EOL;
                            break;
                    }
                }

            }
        }
        return $_blocks;
    }

    public function upload(){

        if($_FILES)
        {
            $uploaddir = dirname($this->plugin->path);
            $uploadfile = $uploaddir . DS. basename($_FILES['attachment']['name']['file']);
            if (move_uploaded_file($_FILES['attachment']['tmp_name']['file'], $uploadfile))
            {
                $sirtrevor = '{ "path": "'.$uploadfile.'"}';
                die($sirtrevor);
            }
        }
    }

} 