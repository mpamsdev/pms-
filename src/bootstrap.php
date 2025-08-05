<?php


require __DIR__ . '/../vendor/autoload.php';

// bootstrap.php
use DI\Container;
use Slim\Factory\AppFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
//use Illuminate\Pagination\Paginator;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\RegistrationStepMiddleware;

use Slim\Views\Twig;


$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();


// Register Cors middleware
$app->add(CorsMiddleware::class);
// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Set up Twig-View
$container->set('view', function() {
    return Twig::create(__DIR__ . '/../templates', ['cache' => false]);
});

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Eloquent ORM setup
$capsule = new Capsule();
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'],
    'database'  => $_ENV['DB_NAME'],
    'username'  => $_ENV['DB_USER'],
    'password'  => $_ENV['DB_PASS'],
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();


$stepRequirements = [

    //Individual Steps
    '/account/ind/step-two'   => ['step1'],
    '/account/ind/step-three' => ['step1', 'step2'],
    '/account/ind/step-four'  => ['step1', 'step2', 'step3'],
    '/account/ind/step-five'  => ['step1', 'step2', 'step3', 'step4'],
    '/account/ind/complete-registration'   => ['step1', 'step2', 'step3', 'step4', 'step5'],

    // Organization steps
    '/account/org/step-two' => ['stepOne'],
    '/account/org/step-three' => ['stepOne','stepTwo'],
];

// Middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->add(new RegistrationStepMiddleware($stepRequirements));


//Create an instance for the home controller
$container->set(\App\Controllers\HomeController::class, function ($c){
    return new \App\Controllers\HomeController($c->get('view'));
});

//Create an instance for the auth controller
$container->set(\App\Controllers\AuthController::class, function ($c){
    return new \App\Controllers\AuthController($c->get('view'));
});

//Create an instance for the  account controller
$container->set(\App\Controllers\AccountController::class, function ($c){
    return new \App\Controllers\AccountController($c->get('view'));
});


//Create an instance for the  Account Controller
$container->set(\App\Controllers\ApplicationController::class, function ($c){
    return new \App\Controllers\ApplicationController($c->get('view'));
});

//Create an instance for the  Account Controller
$container->set(\App\Controllers\ComplianceController::class, function ($c){
    return new \App\Controllers\ComplianceController($c->get('view'));
});

//Create an instance for the  Account Controller
$container->set(\App\Controllers\EmployeeController::class, function ($c){
    return new \App\Controllers\EmployeeController($c->get('view'));
});

// Register routes
(require __DIR__ . '/../src/Routes/web.php')($app);


$app->run();
