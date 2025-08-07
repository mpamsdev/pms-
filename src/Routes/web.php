<?php

use App\Controllers\AccountController;
use Slim\App;
use App\Controllers\HomeController;
use App\Controllers\JobsController;
use App\Controllers\AuthController;
use App\Controllers\ApplicationController;
use App\Controllers\LoanController;
use App\Controllers\EmployeeController;
use App\Controllers\ComplianceController;
use App\Controllers\PayrollController;

return function (App $app) {
    
    // Define routes
    $app->get('/', AuthController::class . ':login');
    $app->post('/', AuthController::class . ':getLogin');
    $app->get('/success', AccountController::class . ':success');
    $app->get('/generate-uuids', HomeController::class . ':generateUUIDs');


    //Auth routes
    $app->group('/auth', function ($group){
        $group->map(['GET', 'POST'], '/forgot-password', AuthController::class . ':forgotPassword');
        $group->map(['GET', 'POST'], '/reset-password/{token}', AuthController::class . ':resetPassword');
        $group->get('/logout', AuthController::class . ':logout');
    });

    //Registration routes individual
    $app->group('/account/ind', function ($group){
        $group->map(['GET', 'POST',], '/step-one', AccountController::class . ':stepOne');
        $group->map(['GET', 'POST',], '/step-two', AccountController::class . ':stepTwo');
        $group->map(['GET', 'POST',], '/step-three', AccountController::class . ':stepThree');
        $group->map(['GET', 'POST',], '/step-four', AccountController::class . ':stepFour');
        $group->map(['GET', 'POST',], '/step-five', AccountController::class . ':stepFive');
        $group->map(['GET', 'POST',], '/complete-registration', AccountController::class . ':completeRegistration');
    });

    //Registration routes companies
    $app->group('/account/org', function ($group){
        $group->map(['GET', 'POST'], '/step-one', AccountController::class . ':stepOneOrg');
        $group->map(['GET', 'POST'], '/step-two', AccountController::class . ':stepTwoOrg');
        $group->map(['GET', 'POST'], '/step-three', AccountController::class . ':stepThreeOrg');
    });

    //Dashboard routes
    $app->group('/dashboard', function ($group){
        $group->get('', HomeController::class . ':dashboard');
        $group->get('/profile', HomeController::class . ':profile');
        $group->get('/system-logs', HomeController::class . ':companies');
    });

    //Compliance Routes
    $app->group('/compliance', function ($group){
        $group->get('', ComplianceController::class . ':complianceTableView');
        $group->post('/add', ComplianceController::class . ':addRecord');
        $group->post('/delete', ComplianceController::class . ':deleteRecord');
        $group->post('/update', ComplianceController::class . ':updateRecord');
    });

    //Employee Routes
    $app->group('/employees', function ($group){
        $group->get('', EmployeeController::class . ':employees');
        $group->post('/add', EmployeeController::class . ':addEmployee');
        $group->post('/update', EmployeeController::class . ':updateEmployee');
        $group->post('/suspend', EmployeeController::class . ':suspendEmployee');
        $group->post('/delete', EmployeeController::class . ':deleteEmployee');
        $group->post('/activate', EmployeeController::class . ':activateEmployee');
    });

    //Payroll Routes
    $app->group('/payroll', function ($group){
        $group->get('', PayrollController::class . ':index');
        $group->post('/add', PayrollController::class . ':add');
        $group->post('/update', PayrollController::class . ':update');
        $group->post('/suspend', PayrollController::class . ':suspend');
        $group->post('/delete', PayrollController::class . ':delete');
        $group->post('/activate', PayrollController::class . ':activate');
        $group->post('/salary', PayrollController::class . ':salary');
        $group->post('/history', PayrollController::class . ':history');
    });

    //Leave


    // Serve assets
    $app->get('/assets/{path:.*}', function (Request $request, Response $response, $args) {
        $filePath = __DIR__ . '/public/assets/' . $args['path'];

        if (file_exists($filePath)) {
            $file = file_get_contents($filePath);
            $response->getBody()->write($file);
            return $response->withHeader('Content-Type', mime_content_type($filePath));
        }

        return $response->withStatus(404)->write('File not found');
    });
};