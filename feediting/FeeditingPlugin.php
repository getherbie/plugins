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
    // @idea: implement a jeditable-UI
    // @todo:
    // call document.ready-function in footer
    public function onOutputGenerated(\Herbie\Event $event ) {

        $_app          = $event->offsetGet('app');
        $_response     = $event->offsetGet('response');
        $_plugin_path  = str_replace($_app['webPath'], '', $_app['config']->get('plugins_path')).'/feediting/';

        $_plugin_config =
'
$(function() {
    $(".editable_textile").editable("'.$_plugin_path.'save.php?renderer=textile", {
          indicator : "<img src=\''.$_plugin_path.'libs/jquery_jeditable-master/img/indicator.gif\'>",
          loadurl   : "'.$_plugin_path.'load.php",
          type      : "textarea",
          submit    : "OK",
          cancel    : "Cancel",
          tooltip   : "Click to edit..."
    });
});
';

        $_response->setContent(strtr($_response->getContent(), array(
            '</body>' =>    '<script src="'.$_plugin_path.'libs/jquery_jeditable-master/jquery.jeditable.js" type="text/javascript" charset="utf-8"></script>'.
                            '<script type="text/javascript" charset="utf-8">'.$_plugin_config.'</script>'.
                            '</body>'
        )));
        $event->offsetSet('response', $_response);
    }

    // fetch markdown-contents for jeditable

    // parse markdown, prepend paragraphs with uid's
    public function onPageLoaded(\Herbie\Event $event ){
        $_page = $event->offsetGet('page');
        $_segments = $_page->getSegments();

        foreach($_segments as $seguid => $content){
            $_segments[$seguid] = "<code>Editable textile:</code>\n".$content;
        };

        $_page->setSegments($_segments);
    }
    // store changed markdown
}