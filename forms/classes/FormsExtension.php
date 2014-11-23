<?php
/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\forms\classes;

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
use Symfony\Component\Validator\Validation;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TwigRenderer;
use Symfony\Component\Validator\Constraints\MinLength;
use Symfony\Component\Validator\Constraints\NotBlank;
    
class FormsExtension extends \Twig_Extension
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
        $this->cachePath = $app['config']->get('forms.cachePath', 'cache');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'forms';
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('form', array($this, 'functionForm'), ['is_safe' => ['html']])
        ];
    }

    /**
     * @param string|int $segmentId
     * @param bool $wrap
     * @return string
     */
    public function functionForm($form = null, $wrap = false)
    {
        $content = $this->renderForm($form);
        if (empty($wrap)) {
            return $content;
        }
        return sprintf('<div class="form-%s">%s</div>', $path, $content);
    }

    public function renderForm($form) {
        if(!$this->app['page']->data[$form]) return;
        else $defForm = $this->app['page']->data[$form];

        // Overwrite this with your own secret
        if(!defined('CSRF_SECRET_'.$form)) define('CSRF_SECRET_'.$form, 'c2ioeEU1n48QF2WsHGWd2HmiuUUT6dxr');
        if(!defined('DEFAULT_FORM_THEME')) define('DEFAULT_FORM_THEME', 'form_div_layout.html.twig');

        if(!defined('VENDOR_DIR')) define('VENDOR_DIR', realpath($this->webPath.DS.'..'.DS.'..'.DS.'vendor'));
        if(!defined('VENDOR_FORM_DIR')) define('VENDOR_FORM_DIR', VENDOR_DIR.DS.'symfony'.DS.'form'.DS.'Symfony'.DS.'Component'.DS.'Form');
        if(!defined('VENDOR_VALIDATOR_DIR')) define('VENDOR_VALIDATOR_DIR', VENDOR_DIR.DS.'symfony'.DS.'validator'.DS.'Symfony'.DS.'Component'.DS.'Validator');
        if(!defined('VENDOR_TWIG_BRIDGE_DIR')) define('VENDOR_TWIG_BRIDGE_DIR', VENDOR_DIR.DS.'symfony'.DS.'twig-bridge'.DS.'Symfony'.DS.'Bridge'.DS.'Twig');
        if(!defined('VIEWS_DIR')) define('VIEWS_DIR', $this->app['sitePath'].'/layouts/views');

        require VENDOR_DIR . DS .'autoload.php';

        // Set up the CSRF provider
        $csrfProvider = new DefaultCsrfProvider(constant('CSRF_SECRET_'.$form));

        // Set up the Validator component
        $validator = Validation::createValidator();

        // Set up the Translation component
        $translator = new Translator('en');
        $translator->addLoader('xlf', new XliffFileLoader());
        $translator->addResource('xlf', VENDOR_FORM_DIR.DS.'Resources'.DS.'translations'.DS.'validators.de.xlf', 'en', 'validators');
        $translator->addResource('xlf', VENDOR_VALIDATOR_DIR.DS.'Resources'.DS.'translations'.DS.'validators.de.xlf', 'en', 'validators');

        // Set up Twig
        $twig = new Twig_Environment(
            new Twig_Loader_Filesystem(array(
                    VIEWS_DIR,
                    VENDOR_TWIG_BRIDGE_DIR.DS.'Resources'.DS.'views'.DS.'Form',
            )),
            [
                'debug' => $this->app['config']->get('twig.debug'),
                'cache' => $this->app['config']->get('twig.cache')
            ]
        );
        $formEngine = new TwigRendererEngine(array(DEFAULT_FORM_THEME));
        $formEngine->setEnvironment($twig);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension(new FormExtension(new TwigRenderer($formEngine, $csrfProvider)));

        // Set up the Form component
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrfProvider))
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();

        // Create our first form!
        $formBuilder = $formFactory->createNamedBuilder($form);
        foreach($defForm['elements'] as $label => $elem){
            $options = array_key_exists('options', $elem) ? $elem['options'] : array();
            $constraints = array();
            if(array_key_exists('constraints', $options)) {
                foreach($options['constraints'] as $constraint){
                    preg_match('/(.+)\((.+)?\)/', $constraint, $catom);
                    $constraintstring = "Symfony\\Component\\Validator\\Constraints\\{$catom[1]}";
                    if(count($catom)>2)
                        $constraints[] = new $constraintstring($catom[2]);
                    else
                        $constraints[] = new $constraintstring();
                }
                $options['constraints'] = $constraints;
            }
            $formBuilder->add($label, $elem['type'], $options );
        }
        $form = $formBuilder->getForm();

        if (isset($_POST[$form->getName()])) {
            $form->bind($_POST[$form->getName()]);

            if ($form->isValid()) {
//                var_dump('VALID', $form->getData());
//                var_dump($this->absUrl($this->getRoute().'/'.'Webdesign'));
                return $this->app->renderContentSegment('Danke');
            }
        }

        return $twig->render('form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

}
