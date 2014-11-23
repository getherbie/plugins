<?php
/**
 * registers the files of the current directory for later use, eg. gallery
 * @returns null
 */
return new Twig_SimpleFunction('registerPageFiles', function () {
    $currDir = dirname($this['page']->path);
    $this['page']->setData(array( 'files' => scandir($currDir) ));
});