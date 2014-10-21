<?php
/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\widgets\classes;

use Pimple;
use Pimple\Container;
use Herbie;
use Herbie\Loader;
use Herbie\Twig;
use Twig_Autoloader;
use Twig_SimpleFunction;
use Twig_Environment;
use Twig_Extension_Debug;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Twig_Loader_String;
    
class WidgetsExtension extends \Twig_Extension
{
    /**
    * @var Application
    */
    protected $app;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $webPath;

    /**
     * @var string
     */
    protected $pagePath;

    /**
     * @var string
     */
    protected $cachePath = 'cache';

    /**
     * @param Application $app
     */
    public function __construct($app)
    {
        if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

        $this->app = $app;
        $this->basePath = $app['request']->getBasePath() . DS;
        $this->webPath = rtrim(dirname($_SERVER['SCRIPT_FILENAME']), DS);
        $this->pagePath = rtrim($app['config']->get('pages.path').$_SERVER['REQUEST_URI'], DS);
        $this->cachePath = $app['config']->get('widgets.cachePath', 'cache');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'widgets';
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('widget', array($this, 'widgetFunction'), ['is_safe' => ['html']])
        ];
    }

    /**
     * @param string|int $segmentId
     * @param bool $wrap
     * @return string
     */
    public function widgetFunction($path = null, $wrap = null)
    {
        $content = $this->renderWidget($path);
        if (empty($wrap)) {
            return $content;
        }
        return sprintf('<div class="widget-%s">%s</div>', $path, $content);
    }

    public function renderWidget($widgetName) {

        # Enable configuration of hidden custom-template-containers in the pagetree
        $_subtemplateDir = false;
        $_curDir = dirname($this->app['page']->path);
        $_widgetDir = '_'.strtolower($widgetName);

        if(is_dir($_curDir.DS.$_widgetDir)) {
            $_subtemplateDir = $_curDir.DS.$_widgetDir.DS.'.layouts';
            if(!is_dir($_subtemplateDir)){
                $_subtemplateDir = false;
            }
        }

        if(!$_subtemplateDir) return null;

        $appPage = $this->app['page'];
        $widgetPageLoader = new Loader\PageLoader($this->app['parser']);
        $this->app['page'] = $widgetPageLoader->load(dirname($_subtemplateDir).DS.'index.md');

        $widgetLoader = new Twig_Loader_Filesystem($_subtemplateDir);
        $twiggedWidget = new Twig_Environment($widgetLoader, [
            'debug' => $this->app['config']->get('twig.debug'),
            'cache' => $this->app['config']->get('twig.cache')
        ]);

        $twiggedWidget->addExtension(new Twig\HerbieExtension($this->app));
        if (!$this->app['config']->isEmpty('imagine')) {
            #$twiggedWidget->addExtension(new Twig\ImagineExtension($this->app));
        }

//        $twiggedWidget->addTwigPlugins();

        $ret = strtr($twiggedWidget->render('index.html', array(
            'abspath' => dirname($_subtemplateDir).'/'
        ) ), array(
            './' => substr(dirname($_subtemplateDir), strlen($this->app['webPath'])).'/'
        ));

        $this->app['page'] = $appPage;

        return $ret;
    }

}
