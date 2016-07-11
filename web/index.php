<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Silex\Provider\FormServiceProvider;
//use GuzzleHttp\Psr7\Request;
use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app->register(new FormServiceProvider());
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/views',
));

$app->register(new Silex\Provider\ValidatorServiceProvider());

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'translator.domains' => array(),
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app['twig'] = $app->share($app->extend('twig', function ($twig, $app) {
    $twig->addFunction(new \Twig_SimpleFunction('asset', function ($asset) use ($app) {
        return sprintf('%s/%s', trim($app['request']->getBasePath()), ltrim($asset, '/'));
    }));
    return $twig;
}));
// Controller
$app->mount('/', new MigrationApp\MigrationClassController());

$app->match('/user-migration', function (Request $request) use ($app) {
    $data = array(
        'seller_id' => '',
        's_url'     => '',
    );

    $form = $app['form.factory']->createBuilder('form', $data)
        ->add('seller_id', 'text', array(
            'attr' => array('placeholder' => '561dfe1b71b2ec9f1b8b4567'),
        ))
        ->add('s_url', 'text', array(
            'attr' => array('placeholder' => 'https://xola.com'),
        ))
        ->add('s_user_name', 'text', array(
            'attr' => array('placeholder' => 'Admin User Name'),
        ))
        ->add('s_password', 'password', array(
            'attr' => array('placeholder' => 'Admin Password'),
        ))
        ->add('d_url', 'text', array(
            'attr' => array('placeholder' => 'https://dev.xola.com'),
        ))
        ->add('d_user_name', 'text', array(
            'attr' => array('placeholder' => 'Admin User Name'),
        ))
        ->add('d_password', 'password', array(
            'attr' => array('placeholder' => 'Admin Password'),
        ))
        ->getForm();

    $form->handleRequest($request);

    if ($request->getMethod() == 'POST') {

        $data = $form->getData();
        //var_dump($data);
        $seller_id   = $data['seller_id'];
        $s_url       = $data['s_url'];
        $s_user_name = $data['s_user_name'];
        $s_password  = $data['s_password'];
        $d_url       = $data['d_url'];
        $d_user_name = $data['d_user_name'];
        $d_password  = $data['d_password'];

        MigrationApp\MigrationClassController::xola_user_fetch_post($seller_id, $s_url, $s_user_name, $s_password, $d_url, $d_user_name, $d_password);

        // redirect somewhere
        //sleep(30);

        //return $app->redirect("/experience-migration");
      }  

    // display the form
    return $app['twig']->render('user.twig', array('form' => $form->createView()));

});

$app->match('/experience-migration', function (Request $request) use ($app) {
    $data = array(
        'seller_email' => '',
    );

    $form = $app['form.factory']->createBuilder('form', $data)
        ->add('seller_email', 'text', array(
            'attr' => array('placeholder' => 'seller@website.xyz'),
        ))
        ->add('s_exp_url', 'text', array(
            'attr' => array('placeholder' => 'https://xola.com'),
        ))
        ->add('s_user_name', 'text', array(
            'attr' => array('placeholder' => 'Admin User Name'),
        ))
        ->add('s_password', 'password', array(
            'attr' => array('placeholder' => 'Admin Password'),
        ))
        ->add('d_exp_url', 'text', array(
            'attr' => array('placeholder' => 'https://dev.xola.com'),
        ))
        ->add('d_password', 'password', array(
            'attr' => array('placeholder' => 'Seller account password'),
        ))
        ->add('d_user_name', 'text', array(
            'attr' => array('placeholder' => 'Admin User Name'),
        ))
        ->add('d_admin_password', 'password', array(
            'attr' => array('placeholder' => 'Admin Password'),
        ))
        ->getForm();

    $form->handleRequest($request);

    if ($request->getMethod() == 'POST') {

        $data_exp         = $form->getData();
        $s_exp_url        = $data_exp['s_exp_url'];
        $d_exp_url        = $data_exp['d_exp_url'];
        $s_user_name      = $data_exp['s_user_name'];
        $s_password       = $data_exp['s_password'];
        $seller_username  = $data_exp['seller_email'];
        $d_password       = $data_exp['d_password'];
        $d_user_name      = $data_exp['d_user_name'];
        $d_admin_password = $data_exp['d_admin_password'];
        if (isset($data_exp['seller_email'], $data_exp['s_exp_url'], $data_exp['s_user_name'], $data_exp['s_password'], $data_exp['d_exp_url'], $data_exp['d_user_name'], $data_exp['d_password'], $data_exp['d_admin_password'])) {
            $enabled = MigrationApp\MigrationClassController::user_enable($d_exp_url, $seller_username, $d_user_name, $d_admin_password);
            if ($enabled === true) {
                echo '<div align="center">User is enabled, now fetching experiences.<br></div>';
                MigrationApp\MigrationClassController::xola_exp_fetch_post($s_exp_url, $s_user_name, $s_password, $d_exp_url, $seller_username, $d_password, $d_user_name, $d_admin_password);
            } else {
                echo "User Is not enabled.";
            }

        } else {
            echo '<div align="center">We are unable to proceed! Please Fill in the above details.</div>';
        }
    } //var_dump($data);

    // redirect somewhere
    return $app['twig']->render('experience.twig', array('form' => $form->createView()));

});

// display the form

$app->run();
