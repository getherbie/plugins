<?php
return new Twig_SimpleFunction('fa', function ($icon) {
    return '<i class="fa-icon-'.$icon.'"></i>';
});