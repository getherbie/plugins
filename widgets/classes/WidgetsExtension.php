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

        $alias = $this->app['alias'];
        $twig  = $this->app['twig'];
        $path  = $this->app['menu']->getItem($this->app['route'])->getPath();

        $_subtemplateDir = false;
        $_curDir = dirname($alias->get($path));
        $_widgetDir = '_'.strtolower($widgetName);

        if(is_dir($_curDir.DS.$_widgetDir)) {
            $_subtemplateDir = $_curDir.DS.$_widgetDir.DS.'.layout';
            if(!is_dir($_subtemplateDir)){
                $_subtemplateDir = false;
            }
        }
        if(!$_subtemplateDir) return null;

        $pageLoader = $twig->environment->getLoader();
        $pageData = $this->app['page']->toArray();

        $widgetLoader = new Twig_Loader_Filesystem($_subtemplateDir);
        $widgetPage = new \Herbie\Loader\PageLoader($alias);
        $widgetPath = dirname($path).DS.$_widgetDir.DS.'index.md';
        $widgetData = $widgetPage->load($widgetPath);

        $twig->environment->setLoader($widgetLoader);
        $this->app['page']->setData($widgetData['data']);
        $this->app['page']->setSegments($widgetData['segments']);
        $twiggedWidget = strtr($twig->render('index.html', array(
            'abspath' => dirname($_subtemplateDir).'/'
        ) ), array(
            './' => substr(dirname($_subtemplateDir), strlen($this->app['webPath'])).'/'
        ));

        $twig->environment->setLoader($pageLoader);
        $this->app['page']->setData($pageData['data']);
        $this->app['page']->setSegments($pageData['segments']);

        return $twiggedWidget;
    }

}
