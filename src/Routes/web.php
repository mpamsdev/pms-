<?php

use App\Controllers\AccountController;
use Slim\App;
use App\Controllers\HomeController;
use App\Controllers\JobsController;
use App\Controllers\AuthController;
use App\Controllers\ApplicationController;
use App\Controllers\LoanController;
use App\Controllers\ComplianceController;

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


    $app->group('/dashboard/application', function ($group){
        $group->map(['GET', 'POST'], '/apply', ApplicationController::class . ':apply');
        $group->map(['GET', 'POST'], '/document-upload', ApplicationController::class .':uploadDocuments');
    });

    //Dashboard routes
    $app->group('/dashboard', function ($group){
        $group->get('', HomeController::class . ':dashboard');

        $group->get('/compliance-table', ComplianceController::class . ':complianceTableView');
        $group->post('/compliance-table/add', ComplianceController::class . ':addRecord');
        $group->post('/compliance-table/delete/{id}', ComplianceController::class . ':deleteRecord');
        $group->post('/compliance-table/update', ComplianceController::class . ':updateRecord');

        $group->post('/loan/restore/{uuid}', LoanController::class . ':restoreLoan');
        $group->post('/loan/restore-all', LoanController::class . ':restoreAllLoans');

        $group->get('/loan/{uuid}', HomeController::class . ':viewLoan');
        $group->get('/loan/pdf/{uuid}', HomeController::class . ':downloadPdf');
        $group->get('/due-loans', HomeController::class . ':dueLoans');
        $group->delete('/loan/delete/{uuid}', LoanController::class . ':deleteLoan');
        $group->get('/cancelled-loans', HomeController::class . ':cancelledLoans');
        $group->get('/rejected-loans', HomeController::class . ':rejectedLoans');
        $group->get('/paid-loans', HomeController::class . ':paidLoans');
        $group->get('/deleted-loans', HomeController::class . ':deletedLoans');
        $group->get('/loan-repayments', HomeController::class . ':repaymentLoans');
        $group->get('/profile', HomeController::class . ':profile');
        $group->get('/my-applications', HomeController::class . ':myApplications');
        $group->get('/deep-search', HomeController::class . ':search');
        $group->get('/search', LoanController::class . ':search');
        $group->get('/receipts', HomeController::class . ':receipts');
        $group->get('/generate-reports', HomeController::class . ':reports');
        $group->get('/clients', HomeController::class . ':clients');
        $group->get('/clients/client/{uuid}', HomeController::class . ':viewClient' );
        $group->get('/companies', HomeController::class . ':companies');
        $group->get('/company/{uuid}', HomeController::class . ':viewCompany');
        $group->get('/manage-users', HomeController::class . ':manageUsers');
        $group->get('/manage-products', HomeController::class . ':manageProducts');
        $group->get('/system-logs', HomeController::class . ':companies');
    });

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