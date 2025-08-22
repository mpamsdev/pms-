<?php

use App\Controllers\AccountController;
use App\Controllers\AccountingController;
use App\Controllers\LeaveController;
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
        $group->post('/check-compliance', ComplianceController::class . ':checkCompliance');

        //Subsistence
        $group->map(['GET','POST'], '/subsistence', ComplianceController::class . ':subsistence');
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
        $group->post('/add', PayrollController::class . ':addPayroll');
        $group->post('/update', PayrollController::class . ':updatePayroll');
        $group->post('/freeze', PayrollController::class . ':freezePayrollRecord');
        $group->post('/unfreeze', PayrollController::class . ':unfreezePayrollRecord');
        $group->post('/delete', PayrollController::class . ':deletePayrollRecord');
        $group->get('/salary', PayrollController::class . ':salary');
        $group->post('/salary/add', PayrollController::class . ':addSalary');
        $group->get('/history', PayrollController::class . ':history');
        $group->get('/payslip', PayrollController::class . ':payslip');

        $group->post('/allowance', PayrollController::class . ':allowance');
        $group->post('/allowance/update', PayrollController::class . ':updateAllowance');
        $group->get('/allowances/list', PayrollController::class . ':allowanceList');
        $group->post('/allowance/delete', PayrollController::class . ':deleteAllowanceRecord');

        $group->post('/deduction', PayrollController::class . ':deductions');
        $group->post('/deduction/update', PayrollController::class . ':updateDeductions');
        $group->post('/deduction/delete', PayrollController::class . ':deleteDeductionRecord');

        $group->get('/salary/totals/{employee_id}', PayrollController::class . ':totals');
        $group->post('/salary/update', PayrollController::class . ':updateSalary');
        $group->post('/salary/delete', PayrollController::class . ':deleteSalaryRecord');
    });

    //Leave Routes
    $app->group('leave', function ($group){
        $group->get('', LeaveController::class . ':index');
        $group->map(['GET', 'POST'],'/request', LeaveController::class . ':request');
        $group->map(['GET', 'POST'], '/my-leave', LeaveController::class . ':myLeave');
        $group->map(['GET', 'POST'], '/{uuid}/approve', LeaveController::class . ':approve');
        $group->map(['GET', 'POST'], '/{uuid}/reject', LeaveController::class . ':reject');
        $group->map(['GET', 'POST'], '/{uuid}/delete', LeaveController::class . ':delete');
        $group->map(['GET', 'POST'], '/{uuid}/update', LeaveController::class . ':update');
    });

    //Accounting Routes
    $app->group('accounting', function ($group){
        $group->get('', AccountingController::class . ':index');
        $group->map(['GET', 'POST'],'/advance', AccountingController::class . ':advance');
        $group->map(['GET', 'POST'], '/subsistence', AccountingController::class . ':subsistence');
        $group->map(['GET', 'POST'], '/petty-cash', AccountingController::class . ':pettyCash');
        $group->map(['GET', 'POST'], 'gratuity', AccountingController::class . ':gratuity');
    });


    $app->get('/manifest.json', function ($request, $response) {
        $response->getBody()->write(file_get_contents(__DIR__ . '/public/manifest.json'));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/service-worker.js', function ($request, $response) {
        $response->getBody()->write(file_get_contents(__DIR__ . '/public/service-worker.js'));
        return $response->withHeader('Content-Type', 'application/javascript');
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